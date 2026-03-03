<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\AppointmentController;
use App\Http\Controllers\App\CalendarController;
use App\Http\Controllers\App\StaffController;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return redirect('/login');
});

/*
|--------------------------------------------------------------------------
| Auth routes
|--------------------------------------------------------------------------
*/
require __DIR__ . '/auth.php';

/*
|--------------------------------------------------------------------------
| Post-login redirect compatibility
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->get('/dashboard', function () {
    return redirect('/app/dashboard');
})->name('dashboard');

/*
|--------------------------------------------------------------------------
| Profile (Breeze-compatible)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('profile')->name('profile.')->group(function () {

    Route::get('/', function () {
        return response()->view('profile.edit', [], 200);
    })->name('edit');

    Route::patch('/', function (Request $request) {
        return redirect()->route('profile.edit')->with('status', 'profile-updated');
    })->name('update');

    Route::delete('/', function () {
        return redirect('/login');
    })->name('destroy');
});

/*
|--------------------------------------------------------------------------
| App area (Staff/Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'staff_or_admin'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {

        // REAL controllers (no more placeholder)
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');

        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

        // Staff CRUD
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    });