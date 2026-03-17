<?php

use App\Http\Controllers\App\AppointmentController;
use App\Http\Controllers\App\CalendarController;
use App\Http\Controllers\App\CustomerController;
use App\Http\Controllers\App\CustomerImportController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\StaffController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('app.dashboard');
    }

    return redirect()->route('login');
});

Route::middleware('auth')->get('/dashboard', function () {
    return redirect()->route('app.dashboard');
})->name('dashboard');

/*
|--------------------------------------------------------------------------
| Breeze Profile routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| Public booking
|--------------------------------------------------------------------------
*/

Route::get('/booking', [BookingController::class, 'index'])->name('booking.index');
Route::get('/booking/slots', [BookingController::class, 'slots'])->name('booking.slots');
Route::post('/booking', [BookingController::class, 'store'])->name('booking.store');

/*
|--------------------------------------------------------------------------
| Internal app area (Staff/Admin)
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

        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');

        Route::get('/customers/import', [CustomerImportController::class, 'index'])->name('customers.import.index');
        Route::post('/customers/import', [CustomerImportController::class, 'store'])->name('customers.import.store');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    });

require __DIR__.'/auth.php';