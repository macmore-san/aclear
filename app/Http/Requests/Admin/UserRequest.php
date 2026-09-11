<?php

namespace App\Http\Requests\Admin;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            ...$this->profileRules($user?->id),
            // Password is required on create, optional on update.
            'password' => $user === null
                ? $this->passwordRules()
                : ['nullable', 'string', Password::default(), 'confirmed'],
            'roles' => ['array'],
            'roles.*' => [Rule::exists('roles', 'name')],
        ];
    }
}
