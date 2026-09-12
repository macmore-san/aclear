<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('employees/index', [
            'employees' => Employee::query()->orderBy('name')->get(),
        ]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        Employee::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee created.')]);

        return to_route('employees.index');
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee updated.')]);

        return to_route('employees.index');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Employee deleted.')]);

        return to_route('employees.index');
    }
}
