<?php

namespace App\Http\Controllers;

use App\Exceptions\CsvFormatException;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Services\CsvPunchParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PunchImportController extends Controller
{
    public function __construct(
        private CsvPunchParser $punchParser,
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('punches/index', [
            'totalPunches' => AttendancePunch::count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1|max:7',
            'files.*' => 'file|mimes:csv,txt|max:20480',
        ]);

        $fileResults = [];
        $totalImported = 0;
        $totalSkipped = 0;

        foreach ($request->file('files') as $file) {
            $result = $this->processCsvFile($file);
            $fileResults[] = $result;
            $totalImported += $result['imported'];
            $totalSkipped += $result['skipped'];
        }

        $count = count($fileResults);

        return response()->json([
            'message' => "Import complete: {$totalImported} record(s) saved across {$count} file(s).",
            'files' => $fileResults,
            'imported' => $totalImported,
            'skipped' => $totalSkipped,
            'totalPunches' => AttendancePunch::count(),
        ]);
    }

    /** @return array<string, mixed> */
    private function processCsvFile(UploadedFile $file): array
    {
        $fileName = $file->getClientOriginalName();
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return $this->csvFileError($fileName, 'Could not open the uploaded file.');
        }

        $rawHeader = fgetcsv($handle);
        if (! $rawHeader) {
            fclose($handle);

            return $this->csvFileError($fileName, 'File is empty.');
        }

        $header = array_map(fn ($value) => trim((string) $value), $rawHeader);
        // Excel writes a UTF-8 BOM ahead of the first header cell.
        $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);

        $acIdx = array_search('Ac-No', $header);
        $stIdx = array_search('sTime', $header);
        $nameIdx = array_search('Name', $header); // optional — used to seed the employees table

        if ($acIdx === false || $stIdx === false) {
            fclose($handle);

            return $this->csvFileError(
                $fileName,
                'Missing required columns (Ac-No, sTime). Found: '.implode(', ', $header),
            );
        }

        // ── Pass 1: read every row, so the date format is decided from the whole file ──
        $rows = [];
        $sTimeSamples = [];
        $namesByCode = [];
        $skippedBlank = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $empCode = trim($row[$acIdx] ?? '');
            $sTime = trim($row[$stIdx] ?? '');

            if ($empCode === '' || $sTime === '') {
                $skippedBlank++;

                continue;
            }

            $rows[] = [$empCode, $sTime];
            $sTimeSamples[] = $sTime;

            if ($nameIdx !== false) {
                $name = trim($row[$nameIdx] ?? '');
                if ($name !== '') {
                    $namesByCode[$empCode] = $name;
                }
            }
        }

        fclose($handle);

        if ($rows === []) {
            return $this->csvFileError($fileName, 'No data rows found.');
        }

        try {
            [$format, $assumed] = $this->punchParser->detectFormat($sTimeSamples);
        } catch (CsvFormatException $e) {
            // Nothing is inserted: a file with no recognisable date at all can't be
            // filed under any month, so it's still refused outright.
            return $this->csvFileError($fileName, $e->getMessage());
        }

        // ── Pass 2: parse with the detected format ────────────────────────────────
        $records = [];
        $skippedNoTime = 0;
        $skippedUnparsable = 0;
        $now = now()->toDateTimeString();

        foreach ($rows as [$empCode, $sTime]) {
            try {
                $dt = $this->punchParser->parse($sTime, $format);
            } catch (CsvFormatException $e) {
                if ($e->getCode() === CsvPunchParser::CODE_NO_TIME) {
                    $skippedNoTime++;
                } else {
                    $skippedUnparsable++;
                }

                continue;
            }

            $records[] = [
                'emp_code' => $empCode,
                'punch_time' => $dt->toDateTimeString(),
                'source_file' => $fileName,
                'uploaded_at' => $now,
            ];
        }

        $imported = 0;
        $duplicates = 0;

        foreach (array_chunk($records, 500) as $chunk) {
            $inserted = DB::table('attendance_punches')->insertOrIgnore($chunk);
            $imported += $inserted;
            $duplicates += count($chunk) - $inserted;
        }

        // Seed new employees from the Name column, if present. Existing employees
        // (name, position, department, start_time set via the Employees page) are
        // never overwritten — this only fills in ones the CSV introduces for the
        // first time so DTRs can be printed without a separate setup step.
        if ($namesByCode !== []) {
            $existing = Employee::whereIn('emp_code', array_keys($namesByCode))
                ->pluck('emp_code')->all();
            $newCodes = array_diff(array_keys($namesByCode), $existing);

            foreach ($newCodes as $code) {
                Employee::create(['emp_code' => $code, 'name' => $namesByCode[$code]]);
            }
        }

        $punchTimes = array_column($records, 'punch_time');
        sort($punchTimes);

        return [
            'name' => $fileName,
            'detected_format' => CsvPunchParser::label($format),
            'date_format_assumed' => $assumed,
            'imported' => $imported,
            'duplicates' => $duplicates,
            'skipped_blank' => $skippedBlank,
            'skipped_no_time' => $skippedNoTime,
            'skipped_unparsable' => $skippedUnparsable,
            'skipped' => $duplicates + $skippedBlank + $skippedNoTime + $skippedUnparsable,
            'first_punch' => $punchTimes[0] ?? null,
            'last_punch' => end($punchTimes) ?: null,
        ];
    }

    /**
     * A rejected file, in the same shape as a successful one so the UI can render it uniformly.
     *
     * @return array<string, mixed>
     */
    private function csvFileError(string $fileName, string $message): array
    {
        return [
            'name' => $fileName,
            'detected_format' => null,
            'date_format_assumed' => false,
            'imported' => 0,
            'duplicates' => 0,
            'skipped_blank' => 0,
            'skipped_no_time' => 0,
            'skipped_unparsable' => 0,
            'skipped' => 0,
            'first_punch' => null,
            'last_punch' => null,
            'error' => $message,
        ];
    }
}
