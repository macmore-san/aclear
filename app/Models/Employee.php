<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $emp_code
 * @property string $name
 * @property string|null $position
 * @property string|null $department
 * @property string|null $start_time
 * @property bool $is_active
 */
#[Fillable(['emp_code', 'name', 'position', 'department', 'start_time', 'is_active'])]
class Employee extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
