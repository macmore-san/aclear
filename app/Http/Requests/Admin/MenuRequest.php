<?php

namespace App\Http\Requests\Admin;

use App\Models\Menu;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string|\Closure>
     */
    public function rules(): array
    {
        /** @var Menu|null $menu */
        $menu = $this->route('menu');

        return [
            'title' => ['required', 'string', 'max:255'],
            'icon' => ['nullable', 'string', Rule::in(Menu::ICONS)],
            'route' => ['nullable', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if ($value !== null && ! Route::has($value)) {
                    $fail('The selected route does not exist.');
                }
            }],
            'permission' => ['nullable', 'string', 'max:255', 'alpha_dash'],
            // Only 2 levels deep: a parent must itself be top-level, and a menu with
            // children can't be nested under another one.
            'parent_id' => [
                'nullable',
                Rule::exists('menus', 'id')->where('parent_id', null),
                function (string $attribute, mixed $value, \Closure $fail) use ($menu) {
                    if ($value === null || $menu === null) {
                        return;
                    }

                    if ((int) $value === $menu->id) {
                        $fail('A menu cannot be its own parent.');
                    } elseif ($menu->children()->exists()) {
                        $fail('A menu with sub-items cannot be nested under another menu.');
                    }
                },
            ],
        ];
    }
}
