<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        // 1) Filters used by your Blade
        $filters = [
            'date' => $request->input('date') ?: now()->format('Y-m-d'),
            'service_ids' => $request->input('service_ids', []),
            'staff_id' => $request->input('staff_id'),
        ];

        // Ensure service_ids is always array
        if (!is_array($filters['service_ids'])) {
            $filters['service_ids'] = [$filters['service_ids']];
        }

        // 2) Services dropdown (you already seeded)
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // 3) Appointment list (bottom table)
        $appointmentGroupsQuery = AppointmentGroup::query()
            ->with(['customer', 'items.staff'])
            ->whereDate('starts_at', $filters['date'])
            ->orderBy('starts_at');

        if (!empty($filters['staff_id'])) {
            $appointmentGroupsQuery->whereHas('items', function ($q) use ($filters) {
                $q->where('staff_id', $filters['staff_id']);
            });
        }

        $appointmentGroups = $appointmentGroupsQuery->paginate(20)->withQueryString();

        // 4) Status options (your Blade calls label())
        // If you already have an enum, keep it. If not, this still works.
        $statusOptions = collect([
            (object)['value' => 'booked', 'label' => fn() => 'Booked'],
            (object)['value' => 'checked_in', 'label' => fn() => 'Checked In'],
            (object)['value' => 'completed', 'label' => fn() => 'Completed'],
            (object)['value' => 'cancelled', 'label' => fn() => 'Cancelled'],
        ])->map(function ($o) {
            return new class($o) {
                public string $value;
                private $lab;
                public function __construct($o) { $this->value = $o->value; $this->lab = $o->label; }
                public function label() { $fn = $this->lab; return $fn(); }
            };
        });

        // 5) Availability engine (this is the missing piece)
        $availability = null;

        if (!empty($filters['service_ids'])) {
            $selected = $services->whereIn('id', $filters['service_ids'])->values();

            // Group by required role: doctor/nurse/beautician
            $requiredRoles = [];
            foreach ($selected as $svc) {
                $role = $this->resolveRoleFromServiceName($svc->name);
                if (!isset($requiredRoles[$role])) {
                    $requiredRoles[$role] = ['services' => collect()];
                }
                $requiredRoles[$role]['services']->push($svc);
            }

            // Build viable 1-hour slots 09:00–16:00 (end at 17:00)
            $date = Carbon::parse($filters['date']);
            $slotStart = $date->copy()->setTime(9, 0, 0);
            $slotEndLimit = $date->copy()->setTime(17, 0, 0);

            $viableSlots = [];
            $staffOptionsByRoleAndSlot = [];

            while ($slotStart->copy()->addHour()->lte($slotEndLimit)) {
                $start = $slotStart->copy();
                $end = $slotStart->copy()->addHour();
                $timeKey = $start->format('H:i');

                $slotOk = true;

                foreach ($requiredRoles as $role => $info) {
                    // DEV PHASE: "for first test should be all"
                    // so we treat ALL active staff of that role as qualified.
                    $staff = Staff::query()
                        ->where('is_active', true)
                        ->where('role', $role)
                        // availability check: overlap condition (correct)
                        ->whereDoesntHave('appointmentItems', function ($q) use ($start, $end) {
                            $q->where('starts_at', '<', $end)
                              ->where('ends_at', '>', $start);
                        })
                        ->orderBy('full_name')
                        ->get(['id', 'full_name']);

                    if ($staff->isEmpty()) {
                        $slotOk = false;
                        break;
                    }

                    $staffOptionsByRoleAndSlot[$role][$timeKey] = $staff->map(fn($s) => [
                        'id' => (string)$s->id,
                        'full_name' => $s->full_name,
                    ])->values()->all();
                }

                if ($slotOk) {
                    $viableSlots[] = $timeKey;
                }

                $slotStart->addHour();
            }

            $availability = [
                'requiredRoles' => $requiredRoles, // Blade expects: $availability['requiredRoles'] as $role => $info
                'viableSlots' => $viableSlots,     // Blade expects: $availability['viableSlots']
                'staffOptionsByRoleAndSlot' => $staffOptionsByRoleAndSlot, // JS uses this
            ];
        }

        return view('app.appointments.index', compact(
            'filters',
            'services',
            'availability',
            'appointmentGroups',
            'statusOptions'
        ));
    }

    /**
     * DEV placeholder mapping (replace later with real service requirements table)
     */
    private function resolveRoleFromServiceName(string $serviceName): string
    {
        $n = strtolower($serviceName);

        if (str_contains($n, 'nail')) return 'beautician';
        if (str_contains($n, 'inject')) return 'nurse';

        // liver detox / facial / consultation / weight loss -> doctor (dev phase)
        return 'doctor';
    }
}