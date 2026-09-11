<?php

namespace App\Http\Requests\Admin;

use App\Models\Menu;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MenuReorderRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array'],
            'items.*.id' => ['required', Rule::exists('menus', 'id')],
            'items.*.parent_id' => ['nullable', Rule::exists('menus', 'id')],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Reject a payload that would create 3+ levels of nesting: a parent must
     * itself be top-level, and an item with children may not be nested.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var array<int, array{id: int, parent_id: int|null, sort: int}> $items */
            $items = $this->input('items', []);
            $byId = collect($items)->keyBy('id');
            $childCounts = collect($items)
                ->filter(fn (array $item) => $item['parent_id'] !== null)
                ->countBy('parent_id');

            foreach ($items as $index => $item) {
                if ($item['parent_id'] === null) {
                    continue;
                }

                // Eloquent models implement ArrayAccess, so $parent['parent_id'] works whether
                // the parent is also in this payload (array) or only exists in the database (model).
                $parent = $byId->get($item['parent_id']) ?? Menu::find($item['parent_id']);

                if ($parent && $parent['parent_id'] !== null) {
                    $validator->errors()->add("items.{$index}.parent_id", 'Menus can only nest two levels deep.');
                }

                if ($childCounts->get($item['id'], 0) > 0) {
                    $validator->errors()->add("items.{$index}.parent_id", 'A menu with sub-items cannot be nested under another menu.');
                }
            }
        });
    }
}
