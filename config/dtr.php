<?php

return [
    'company' => [
        'name' => env('DTR_COMPANY_NAME', config('app.name')),
        'address' => env('DTR_COMPANY_ADDRESS', ''),
        'logo' => env('DTR_COMPANY_LOGO'),
    ],

    // Flat schedule: no company-wide time in/out, just a required duration per shift.
    'required_minutes' => 600, // 10:00

    // Tardy only counts once arrival is this many minutes past the employee's start_time.
    // Below this it is not tardy at all; at or above it the FULL minutes late count (no grace).
    'tardy_threshold_minutes' => 60,

    // Punches within this many hours of a shift's Time In are paired into the same shift,
    // so an overnight shift (e.g. 22:00 -> 06:00) is not split into two incomplete days.
    'max_shift_hours' => 16,

    // A Time Out closer than this to Time In is treated as a double-tap, not a real shift.
    'min_shift_minutes' => 10,

    // Format assumed when a CSV file's sTime column is ambiguous (dd/mm vs mm/dd cannot be
    // told apart because every value is <= 12) or mixes both — imported instead of rejected.
    'ambiguous_date_format' => env('DTR_CSV_DATE_FORMAT', 'dmy'),

    // `php artisan app:backup` — read here, not via env() in the command, because
    // production caches config and env() returns null outside config files.
    'backup' => [
        'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
        'mysqldump' => env('MYSQLDUMP_PATH') ?: 'mysqldump',
    ],
];
