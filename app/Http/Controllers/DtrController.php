<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\DtrPdfService;
use App\Services\DtrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DtrController extends Controller
{
    public function __construct(
        private DtrService $dtrService,
        private DtrPdfService $pdfService,
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('dtr/index', [
            'employees' => Employee::where('is_active', true)
                ->orderBy('name')
                ->get(['emp_code', 'name', 'position', 'department']),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'emp_code' => 'required|string',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        return response()->json(
            $this->dtrService->build($request->emp_code, $request->date_from, $request->date_to)
        );
    }

    /** One employee, or every active employee when emp_codes is omitted — one PDF, one page per employee. */
    public function pdf(Request $request): Response
    {
        $request->validate([
            'emp_codes' => 'nullable|array',
            'emp_codes.*' => 'string',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $empCodes = $request->filled('emp_codes')
            ? $request->emp_codes
            : Employee::where('is_active', true)->orderBy('name')->pluck('emp_code')->all();

        abort_if($empCodes === [], 404, 'No employees to print.');

        $company = config('dtr.company');
        $pdf = $this->pdfService->newDocument();

        foreach ($empCodes as $empCode) {
            $dtr = $this->dtrService->build($empCode, $request->date_from, $request->date_to);
            $this->pdfService->appendPage($pdf, $dtr, $company);
        }

        $bytes = $pdf->Output('dtr.pdf', 'S');
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"DTR_{$request->date_from}_{$request->date_to}.pdf\"",
        ]);
    }
}
