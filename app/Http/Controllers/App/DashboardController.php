<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\Staff;
use App\Enums\AppointmentStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date') ?: now()->format('Y-m-d');

        $staffId = $request->input('staff_id');

        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role']);

        $query = AppointmentGroup::query()
            ->with(['customer', 'items.staff', 'items.service'])
            ->whereDate('starts_at', $date)
            ->orderBy('starts_at');

        if (!empty($staffId)) {
            $query->whereHas('items', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            });
        }

        $appointments = $query->limit(50)->get();

        // Simple KPI counts for the selected date
        $kpi = [
            'total' => (clone $query)->count(),
            'booked' => (clone $query)->where('status', AppointmentStatus::Booked)->count(),
            'checked_in' => (clone $query)->where('status', AppointmentStatus::CheckedIn)->count(),
            'completed' => (clone $query)->where('status', AppointmentStatus::Completed)->count(),
            'cancelled' => (clone $query)->where('status', AppointmentStatus::Cancelled)->count(),
        ];

        return view('app.dashboard', [
            'date' => $date,
            'staffId' => $staffId,
            'staffList' => $staffList,
            'appointments' => $appointments,
            'kpi' => $kpi,
        ]);
    }
}