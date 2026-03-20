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
        $period = $request->input('period', 'day');
        $staffId = $request->input('staff_id');
        $anchorDate = Carbon::parse($date);

        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role_key']);

        $baseQuery = AppointmentGroup::query()
            ->with(['customer', 'items.staff', 'items.service']);

        [$periodStart, $periodEnd, $periodLabel] = $this->resolvePeriodWindow($anchorDate, $period);

        $baseQuery->whereBetween('starts_at', [$periodStart, $periodEnd]);

        if (!empty($staffId)) {
            $baseQuery->whereHas('items', function ($q) use ($staffId) {
                $q->where('staff_id', $staffId);
            });
        }

        $appointments = (clone $baseQuery)
            ->orderBy('starts_at')
            ->limit(50)
            ->get();

        $statusCases = AppointmentStatus::cases();
        $kpiByStatus = [];

        foreach ($statusCases as $case) {
            $kpiByStatus[$case->value] = (clone $baseQuery)->where('status', $case->value)->count();
        }

        $kpi = [
            'total' => (clone $baseQuery)->count(),
            'by_status' => $kpiByStatus,
            'future_revenue' => null,
        ];

        return view('app.dashboard', [
            'date' => $date,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'staffId' => $staffId,
            'staffList' => $staffList,
            'appointments' => $appointments,
            'kpi' => $kpi,
            'statusCases' => $statusCases,
        ]);
    }

    protected function resolvePeriodWindow(Carbon $anchorDate, string $period): array
    {
        return match ($period) {
            'week' => [
                $anchorDate->copy()->startOfWeek(),
                $anchorDate->copy()->endOfWeek(),
                'Week of '.$anchorDate->copy()->startOfWeek()->format('d M Y'),
            ],
            'month' => [
                $anchorDate->copy()->startOfMonth(),
                $anchorDate->copy()->endOfMonth(),
                $anchorDate->format('F Y'),
            ],
            'year' => [
                $anchorDate->copy()->startOfYear(),
                $anchorDate->copy()->endOfYear(),
                $anchorDate->format('Y'),
            ],
            default => [
                $anchorDate->copy()->startOfDay(),
                $anchorDate->copy()->endOfDay(),
                $anchorDate->format('d M Y'),
            ],
        };
    }
}
