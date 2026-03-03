<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $week = $request->get('week');
        $weekDate = $week ? Carbon::parse($week) : now();
        $start = $weekDate->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        $staffList = Staff::query()
            ->select('id', 'full_name', 'role')
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $staffId = $request->get('staff_id');

        // NOTE: app login uses users table; schedule staff are separate (staff table).
        // For dev phase, we don't auto-map logged-in user to staff record.

        $appointments = Appointment::query()
            ->with(['customer', 'service', 'staff'])
            ->whereBetween('starts_at', [$start, $end])
            ->when($staffId, fn($q) => $q->where('staff_id', $staffId))
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn($a) => $a->starts_at->toDateString());

        return view('app.calendar.index', [
            'start' => $start,
            'end' => $end,
            'appointmentsByDay' => $appointments,
            'hours' => range(9, 17),
            'staffList' => $staffList,
            'staffId' => $staffId,
            'isStaffUser' => false,
        ]);
    }
}
