<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\AppointmentController;
use App\Http\Controllers\App\CalendarController;
use App\Http\Controllers\App\StaffController;

/*
|--------------------------------------------------------------------------
| Public booking
|--------------------------------------------------------------------------
*/
Route::get('/', [BookingController::class, 'index'])->name('booking.index');
Route::get('/booking', [BookingController::class, 'index'])->name('booking.create');
Route::get('/booking/slots', [BookingController::class, 'slots'])->name('booking.slots');
Route::post('/booking', [BookingController::class, 'store'])->name('booking.store');

/*
|--------------------------------------------------------------------------
| Auth landing
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    return redirect('/app/dashboard');
})->middleware(['auth'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Profile (Breeze-compatible placeholders)
|--------------------------------------------------------------------------
| You previously used lightweight routes to avoid missing views.
*/
Route::middleware(['auth'])
    ->prefix('profile')
    ->name('profile.')
    ->group(function () {
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

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointmentGroup}/status', [AppointmentController::class, 'updateStatus'])
            ->name('appointments.status');

        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

        // Staff CRUD
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
    });

require __DIR__ . '/auth.php';