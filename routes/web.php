<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BookingController;

use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\CalendarController;
use App\Http\Controllers\App\StaffController;
use App\Http\Controllers\App\AppointmentController;

/*
|--------------------------------------------------------------------------
| Public booking
|--------------------------------------------------------------------------
*/
Route::get('/', [BookingController::class, 'index'])->name('booking.index');
Route::post('/book', [BookingController::class, 'store'])->name('booking.store');

/*
|--------------------------------------------------------------------------
| Authenticated area
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    // Default after login
    Route::get('/dashboard', fn () => redirect('/app/dashboard'))->name('dashboard');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    /*
    |--------------------------------------------------------------------------
    | Internal app
    |--------------------------------------------------------------------------
    */
    Route::prefix('app')->name('app.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

        // Staff CRUD
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');

        // Appointments (appointment_groups + appointment_items)
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::patch('/appointments/{appointmentGroup}/status', [AppointmentController::class, 'updateStatus'])
            ->name('appointments.status');
    });
});

require __DIR__ . '/auth.php';