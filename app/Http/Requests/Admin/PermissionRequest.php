<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Permission|null $permission */
        $permission = $this->route('permission');

        return [
            'name' => [
                'required', 'string', 'max:255',
                $permission === null
                    ? Rule::unique('permissions', 'name')
                    : Rule::unique('permissions', 'name')->ignore($permission->id),
            ],
        ];
    }
}
