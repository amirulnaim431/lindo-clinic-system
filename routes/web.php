<?php

use App\Http\Controllers\App\AppointmentController;
use App\Http\Controllers\App\CalendarController;
use App\Http\Controllers\App\CustomerController;
use App\Http\Controllers\App\CustomerImportController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\HrScheduleController;
use App\Http\Controllers\App\LogViewerController;
use App\Http\Controllers\App\ServiceController;
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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/booking', [BookingController::class, 'index'])->name('booking.index');
Route::get('/booking/slots', [BookingController::class, 'slots'])->name('booking.slots');
Route::post('/booking', [BookingController::class, 'store'])->name('booking.store');

Route::middleware(['auth', 'staff_or_admin'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('app_permission:dashboard.view')
            ->name('dashboard');

        Route::get('/appointments', [AppointmentController::class, 'index'])
            ->middleware('app_permission:appointments.view')
            ->name('appointments.index');
        Route::get('/appointments/customer-search', [AppointmentController::class, 'customerSearch'])
            ->middleware('app_permission:appointments.manage')
            ->name('appointments.customer-search');
        Route::post('/appointments', [AppointmentController::class, 'store'])
            ->middleware('app_permission:appointments.manage')
            ->name('appointments.store');
        Route::get('/services', [ServiceController::class, 'index'])
            ->middleware('app_permission:appointments.manage')
            ->name('services.index');
        Route::get('/services/create', [ServiceController::class, 'create'])
            ->middleware('app_permission:appointments.manage')
            ->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])
            ->middleware('app_permission:appointments.manage')
            ->name('services.store');
        Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])
            ->middleware('app_permission:appointments.manage')
            ->name('services.edit');
        Route::put('/services/{service}', [ServiceController::class, 'update'])
            ->middleware('app_permission:appointments.manage')
            ->name('services.update');
        Route::patch('/appointments/items/{appointmentItem}/reschedule', [AppointmentController::class, 'rescheduleItem'])
            ->middleware('app_permission:appointments.manage')
            ->name('appointments.items.reschedule');
        Route::patch('/appointments/{appointmentGroup}/reschedule', [AppointmentController::class, 'reschedule'])
            ->middleware('app_permission:appointments.manage')
            ->name('appointments.reschedule');
        Route::patch('/appointments/{appointmentGroup}/status', [AppointmentController::class, 'updateStatus'])
            ->middleware('app_permission:appointments.manage')
            ->name('appointments.status');
        Route::patch('/appointments/{appointmentGroup}', [AppointmentController::class, 'updateFromCalendar'])
            ->middleware('app_permission:appointments.manage')
            ->name('appointments.update');

        Route::get('/calendar', [CalendarController::class, 'index'])
            ->middleware('app_permission:calendar.view')
            ->name('calendar');

        Route::get('/hr/staff-schedule', [HrScheduleController::class, 'index'])
            ->name('hr.schedule');
        Route::post('/hr/staff-schedule/leaves', [HrScheduleController::class, 'storeLeave'])
            ->name('hr.schedule.leaves.store');
        Route::patch('/hr/staff-schedule/leaves/{staffLeave}', [HrScheduleController::class, 'reviewLeave'])
            ->name('hr.schedule.leaves.review');

        Route::get('/staff', [StaffController::class, 'index'])
            ->middleware('app_permission:staff.view')
            ->name('staff.index');
        Route::get('/staff/create', [StaffController::class, 'create'])
            ->middleware('app_permission:staff.manage')
            ->name('staff.create');
        Route::post('/staff', [StaffController::class, 'store'])
            ->middleware('app_permission:staff.manage')
            ->name('staff.store');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])
            ->middleware('app_permission:staff.manage')
            ->name('staff.edit');
        Route::put('/staff/{staff}', [StaffController::class, 'update'])
            ->middleware('app_permission:staff.manage')
            ->name('staff.update');
        Route::post('/staff/{staff}/access/invite', [StaffController::class, 'sendAccessInvite'])
            ->middleware('app_permission:staff.manage')
            ->name('staff.access.invite');
        Route::patch('/staff/{staff}/access/status', [StaffController::class, 'updateAccessStatus'])
            ->middleware('app_permission:staff.manage')
            ->name('staff.access.status');
        Route::patch('/staff/{staff}/status', [StaffController::class, 'updateStatus'])
            ->middleware('app_permission:staff.manage')
            ->name('staff.status');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])
            ->middleware('app_permission:staff.manage')
            ->name('staff.destroy');

        Route::get('/customers/import', [CustomerImportController::class, 'index'])
            ->middleware('app_permission:customers.import')
            ->name('customers.import.index');
        Route::post('/customers/import', [CustomerImportController::class, 'store'])
            ->middleware('app_permission:customers.import')
            ->name('customers.import.store');

        Route::get('/customers', [CustomerController::class, 'index'])
            ->middleware('app_permission:customers.view')
            ->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])
            ->middleware('app_permission:customers.view')
            ->name('customers.show');
        Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
            ->middleware('app_permission:customers.manage')
            ->name('customers.edit');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])
            ->middleware('app_permission:customers.manage')
            ->name('customers.update');

        Route::get('/logs/laravel', [LogViewerController::class, 'laravel'])
            ->name('logs.laravel');
    });

require __DIR__.'/auth.php';
