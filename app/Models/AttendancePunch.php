<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $emp_code
 * @property Carbon $punch_time
 * @property string|null $source_file
 */
#[Fillable(['emp_code', 'punch_time', 'source_file'])]
class AttendancePunch extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'punch_time' => 'datetime',
        ];
    }
}
