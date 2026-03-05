<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\Staff;
use App\Enums\AppointmentStatus;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date') ?: now()->format('Y-m-d');
        $staffId = $request->input('staff_id');

        // staff table uses role_key in your project
        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role_key']);

        $baseQuery = AppointmentGroup::query()
            ->with(['customer', 'items.staff', 'items.service'])
            ->whereDate('starts_at', $date);

        if (!empty($staffId)) {
            $baseQuery->whereHas('items', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            });
        }

        $appointments = (clone $baseQuery)
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        // Build KPI dynamically from enum cases (no hardcoded CheckedIn etc.)
        $statusCases = AppointmentStatus::cases(); // works even if cases differ
        $kpiByStatus = [];

        foreach ($statusCases as $case) {
            $kpiByStatus[$case->value] = (clone $baseQuery)->where('status', $case->value)->count();
        }

        $kpi = [
            'total' => (clone $baseQuery)->count(),
            'by_status' => $kpiByStatus,
        ];

        return view('app.dashboard', [
            'date' => $date,
            'staffId' => $staffId,
            'staffList' => $staffList,
            'appointments' => $appointments,
            'kpi' => $kpi,
            'statusCases' => $statusCases,
        ]);
    }
}