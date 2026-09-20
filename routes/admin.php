<?php

use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'menu.permission'])
    ->prefix('admin')
    ->group(function () {
        Route::resource('users', UserController::class)->except(['create', 'edit', 'show']);
        Route::resource('roles', RoleController::class)->except(['create', 'edit', 'show']);
        Route::resource('permissions', PermissionController::class)->except(['create', 'edit', 'show']);

        Route::put('menus/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');
        Route::resource('menus', MenuController::class)->except(['create', 'edit', 'show']);

        Route::get('updates', [UpdateController::class, 'index'])->name('updates.index');
        Route::post('updates', [UpdateController::class, 'store'])->name('updates.store');
    });
