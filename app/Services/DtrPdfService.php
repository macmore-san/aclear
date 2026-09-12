<?php

namespace App\Services;

class DtrPdfService
{
    // Column widths in mm. Must sum to: 210 (A4 width) - 12 (left) - 12 (right) = 186 mm
    private const COL = [
        'date' => 26.0, // "* 06/01/2026 Mon"
        'time_in' => 20.0, // "Time In" + "18:00 (+1)"
        'time_out' => 20.0, // "Time Out"
        'work' => 17.0, // "Work Hrs"
        'tardy' => 15.0, // "Tardy"
        'ut' => 17.0, // "Undertime"
        'ot' => 17.0, // "Overtime"
        'remarks' => 54.0, // Remarks (always one line — see drawDataRow)
    ];

    /** Smallest legible size the Remarks text may shrink to before condensing */
    private const REMARKS_MIN_PT = 6.0;

    /**
     * Single-employee PDF — returns raw bytes.
     *
     * @param  array<string, mixed>  $dtr
     * @param  array<string, mixed>  $company
     */
    public function generate(array $dtr, array $company): string
    {
        $pdf = $this->newDocument();
        $this->appendPage($pdf, $dtr, $company);

        return $pdf->Output('dtr.pdf', 'S');
    }

    /** Create a configured TCPDF document ready to receive pages */
    public function newDocument(): \TCPDF
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('DTR System');
        $pdf->SetTitle('Daily Time Record');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(12, 5, 12);
        $pdf->SetAutoPageBreak(true, 5);

        return $pdf;
    }

    /**
     * Append one employee's DTR as a new page into an existing document.
     *
     * @param  array<string, mixed>  $dtr
     * @param  array<string, mixed>  $company
     */
    public function appendPage(\TCPDF $pdf, array $dtr, array $company): void
    {
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);

        $pdf->writeHTML($this->buildHeaderHtml($dtr, $company), true, false, true, false, '');

        $this->drawTable($pdf, $dtr);

        $pdf->SetFont('helvetica', '', 10);
        $pdf->writeHTML($this->buildSummaryHtml($dtr), true, false, true, false, '');

        $this->drawSignatories($pdf, $dtr);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->writeHTML($this->buildReminderHtml(), true, false, true, false, '');
    }

    // ── Native-cell table ────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $dtr */
    private function drawTable(\TCPDF $pdf, array $dtr): void
    {
        $c = self::COL;
        $hdr = 6.0; // header row height (mm) — one line, no sub-header row needed
        $dat = 4.8; // data row height (mm) — exact, not a minimum, so a 31-day month always fits

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.2);

        // ── Header row ──────────────────────────────────────────────────────
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(188, 204, 210);

        $pdf->Cell($c['date'], $hdr, 'Date', 1, 0, 'C', true, '', 1);
        $pdf->Cell($c['time_in'], $hdr, 'Time In', 1, 0, 'C', true, '', 1);
        $pdf->Cell($c['time_out'], $hdr, 'Time Out', 1, 0, 'C', true, '', 1);
        $pdf->Cell($c['work'], $hdr, 'Work Hrs', 1, 0, 'C', true, '', 1);
        $pdf->Cell($c['tardy'], $hdr, 'Tardy', 1, 0, 'C', true, '', 1);
        $pdf->Cell($c['ut'], $hdr, 'Undertime', 1, 0, 'C', true, '', 1);
        $pdf->Cell($c['ot'], $hdr, 'Overtime', 1, 0, 'C', true, '', 1);
        $pdf->Cell($c['remarks'], $hdr, 'Remarks', 1, 1, 'C', true, '', 1);

        // ── Data rows ─────────────────────────────────────────────────────
        $pdf->SetFillColor(255, 255, 255);

        foreach ($dtr['rows'] as $row) {
            $this->drawDataRow($pdf, $row, $dat);
        }

        // ── Total row ─────────────────────────────────────────────────────
        $pdf->SetFont('helvetica', 'B', 8.5);
        $pdf->SetFillColor(188, 204, 210);

        $spanW = $c['date'] + $c['time_in'] + $c['time_out'];
        $pdf->Cell($spanW, $dat, 'Total', 1, 0, 'R', true);
        $pdf->Cell($c['work'], $dat, $dtr['total_work_hrs'], 1, 0, 'C', true);
        $pdf->Cell($c['tardy'], $dat, $dtr['total_tardy_hrs'], 1, 0, 'C', true);
        $pdf->Cell($c['ut'], $dat, $dtr['total_ut_hrs'], 1, 0, 'C', true);
        $pdf->Cell($c['ot'], $dat, $dtr['total_ot_hrs'], 1, 0, 'C', true);
        $pdf->Cell($c['remarks'], $dat, '', 1, 1, 'C', true);

        $pdf->Ln(2);
    }

    /**
     * Draw one day's row at exactly $rowH — the row must never grow, or a long
     * month stops fitting on one page. Every cell is a Cell (not MultiCell) so
     * nothing can wrap onto a second line.
     *
     * @param  array<string, mixed>  $row
     */
    private function drawDataRow(\TCPDF $pdf, array $row, float $rowH): void
    {
        $c = self::COL;

        $asterisk = $row['is_incomplete'] ? '* ' : '  ';
        $dateText = $asterisk.$row['day_label'];
        $remarks = $row['remarks'];

        $timeOutText = $row['time_out'] ? $row['time_out'].($row['out_next_day'] ? ' (+1)' : '') : '';

        $dataSize = 8.5;
        $pdf->SetFont('helvetica', '', $dataSize);

        $pdf->Cell($c['date'], $rowH, $dateText, 1, 0, 'L', false, '', 1);
        $pdf->Cell($c['time_in'], $rowH, $row['time_in'] ?? '', 1, 0, 'C');
        $pdf->Cell($c['time_out'], $rowH, $timeOutText, 1, 0, 'C');
        $pdf->Cell($c['work'], $rowH, $row['work_hrs'], 1, 0, 'C');
        $pdf->Cell($c['tardy'], $rowH, $row['tardy'], 1, 0, 'C');
        $pdf->Cell($c['ut'], $rowH, $row['undertime'], 1, 0, 'C');
        $pdf->Cell($c['ot'], $rowH, $row['overtime'], 1, 0, 'C');

        // Remarks is the only variable-length value. Shrink the font until it fits on
        // one line; stretch=1 condenses as a last resort so nothing is ever truncated.
        $pdf->SetFont('helvetica', '', $this->fitFontSize($pdf, $remarks, $c['remarks'], $dataSize));
        $pdf->Cell($c['remarks'], $rowH, $remarks, 1, 1, 'L', false, '', 1);

        $pdf->SetFont('helvetica', '', $dataSize);
    }

    /**
     * Largest size from $maxSize down to REMARKS_MIN_PT at which $text fits on a
     * single line within $width. Returns the floor if nothing fits — Cell's
     * stretch parameter then condenses the glyphs to close the gap.
     */
    private function fitFontSize(\TCPDF $pdf, string $text, float $width, float $maxSize): float
    {
        if ($text === '') {
            return $maxSize;
        }

        $padding = $pdf->getCellPaddings();
        $usable = $width - $padding['L'] - $padding['R'];
        $original = $pdf->getFontSizePt();

        for ($size = $maxSize; $size >= self::REMARKS_MIN_PT; $size -= 0.5) {
            $pdf->SetFontSize($size);
            if ($pdf->GetStringWidth($text) <= $usable) {
                $pdf->SetFontSize($original);

                return $size;
            }
        }

        $pdf->SetFontSize($original);

        return self::REMARKS_MIN_PT;
    }

    // ── HTML sections ────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $dtr
     * @param  array<string, mixed>  $company
     */
    private function buildHeaderHtml(array $dtr, array $company): string
    {
        $emp = $dtr['employee'] ?? [];
        $empName = $this->e($emp['full_name'] ?? $dtr['emp_code']);
        $position = $this->e($emp['position'] ?? '');
        $dept = $this->e($emp['department'] ?? '');
        $companyName = $this->e($company['name'] ?? '');
        $address = $this->e($company['address'] ?? '');
        $dateFrom = $this->fmtDate($dtr['date_from']);
        $dateTo = $this->fmtDate($dtr['date_to']);

        $logoHtml = '';
        if (! empty($company['logo'])) {
            $path = public_path(ltrim($company['logo'], '/'));
            if (file_exists($path)) {
                $logoHtml = "<img src=\"{$path}\" width=\"80\" height=\"80\" />";
            }
        }

        return <<<HTML
<table width="100%" cellpadding="2" cellspacing="0" border="0">
  <tr>
    <td width="13%" align="center" valign="middle">{$logoHtml}</td>
    <td width="74%" align="center" valign="middle">
      <b><span style="font-size:12pt;">{$companyName}</span></b><br/>
      <span style="font-size:9pt;">{$address}</span><br/>
      <b><span style="font-size:14pt;">DAILY TIME RECORD</span></b>
    </td>
    <td width="13%"></td>
  </tr>
</table>
<br/>
<table width="100%" cellpadding="2" cellspacing="0" border="0" style="font-size:10pt;">
  <tr>
    <td width="70%">Name: <b>{$empName}</b></td>
    <td width="30%">From: <b>{$dateFrom}</b></td>
  </tr>
  <tr>
    <td>Department: <b>{$dept}</b></td>
    <td>To: <b>{$dateTo}</b></td>
  </tr>
  <tr>
    <td>Position: <b>{$position}</b></td>
    <td></td>
  </tr>
</table>
<br/>
HTML;
    }

    /** @param  array<string, mixed>  $dtr */
    private function buildSummaryHtml(array $dtr): string
    {
        $tardy = $this->e($dtr['total_tardy_hrs']);
        $ut = $this->e($dtr['total_ut_hrs']);
        $ot = $this->e($dtr['total_ot_hrs']);

        return <<<HTML
<table width="100%" border="0" cellpadding="2" style="font-size:10pt;">
  <tr><td><b>Summary:</b></td></tr>
  <tr><td>Tardy: {$tardy} &nbsp;&nbsp;&nbsp;&nbsp; Undertime: {$ut} &nbsp;&nbsp;&nbsp;&nbsp; Overtime: {$ot}</td></tr>
</table>
<br/>
<p style="font-size:9pt; font-style: italic;">
I certify on my honor that the above is a true and correct report of the hours work performed,
record of which was made daily at the time of arrival and departure from office.
</p>
HTML;
    }

    /** @param  array<string, mixed>  $dtr */
    private function drawSignatories(\TCPDF $pdf, array $dtr): void
    {
        $emp = $dtr['employee'] ?? [];
        $empName = $emp['full_name'] ?? $dtr['emp_code'];

        $lm = 12.0;
        $w1 = 58.0;   // Employee
        $gap = 6.0;
        $w2 = 62.0;   // Immediate Supervisor
        $w3 = 186.0 - $w1 - $gap - $w2 - $gap; // Received by

        $x1 = $lm;
        $x2 = $x1 + $w1 + $gap;
        $x3 = $x2 + $w2 + $gap;

        $labelRowY = $pdf->GetY() + 4;

        $pdf->SetFont('helvetica', '', 7.5);

        $pdf->SetXY($x2, $labelRowY);
        $pdf->Cell($w2, 4, 'VERIFIED as to the prescribed office hours:', 0, 0, 'L');

        $pdf->SetXY($x3, $labelRowY);
        $pdf->Cell($w3, 4, 'Received by:', 0, 0, 'L');

        $verifiedH = $pdf->getStringHeight($w2, 'VERIFIED as to the prescribed office hours:');

        $lineY = $labelRowY + $verifiedH + 7;

        $pdf->SetLineWidth(0.3);
        $pdf->Line($x1, $lineY, $x1 + $w1, $lineY);
        $pdf->Line($x2, $lineY, $x2 + $w2, $lineY);
        $pdf->Line($x3, $lineY, $x3 + $w3, $lineY);

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($x1, $lineY - 5);
        $pdf->Cell($w1, 5, $empName, 0, 0, 'C');

        $roleY = $lineY + 0.5;

        $pdf->SetXY($x1, $roleY);
        $pdf->Cell($w1, 5, 'Employee', 0, 0, 'C');

        $pdf->SetXY($x2, $roleY);
        $pdf->Cell($w2, 5, 'Immediate Supervisor', 0, 0, 'C');

        $pdf->SetXY($x3, $roleY);
        $pdf->Cell($w3, 5, 'Received by', 0, 0, 'C');

        $pdf->SetXY($lm, $roleY + 7);
    }

    private function buildReminderHtml(): string
    {
        return <<<'HTML'
<p style="font-size:8pt; font-style: italic;">
REMINDER: Kindly review the details indicated in this time record, for any discrepancies please
proceed to the office for corrections.
</p>
HTML;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function fmtDate(string $iso): string
    {
        [$y, $m, $d] = explode('-', $iso);

        return "{$m}/{$d}/{$y}";
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
