<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');

        return [
            'emp_code' => [
                'required', 'string', 'max:50',
                $employee === null ? Rule::unique('employees', 'emp_code') : Rule::unique('employees', 'emp_code')->ignore($employee->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'is_active' => ['boolean'],
        ];
    }
}
