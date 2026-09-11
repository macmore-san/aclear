<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:255',
                $role === null ? Rule::unique('roles', 'name') : Rule::unique('roles', 'name')->ignore($role->id),
            ],
            'permissions' => ['array'],
            'permissions.*' => [Rule::exists('permissions', 'name')],
        ];
    }
}
