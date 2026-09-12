<?php

use App\Http\Controllers\DtrController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PunchImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'menu.permission'])->group(function () {
    Route::resource('employees', EmployeeController::class)->except(['create', 'edit', 'show']);

    Route::get('punches', [PunchImportController::class, 'index'])->name('punches.index');
    Route::post('punches', [PunchImportController::class, 'store'])->name('punches.store');

    Route::get('dtr', [DtrController::class, 'index'])->name('dtr.index');
    Route::get('dtr/show', [DtrController::class, 'show'])->name('dtr.show');
    Route::get('dtr/pdf', [DtrController::class, 'pdf'])->name('dtr.pdf');
});
