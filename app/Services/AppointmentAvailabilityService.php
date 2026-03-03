<?php

namespace App\Services;

use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AppointmentAvailabilityService
{
    public function __construct(
        private DevServiceRoleResolver $roleResolver
    ) {}

    /**
     * Returns:
     * - requiredRoles: array<string, array{services: Collection<Service>}>
     * - slotTimes: Collection<string> like ['09:00', ...]
     * - viableSlots: Collection<string> times where each required role has >=1 staff free
     * - staffOptionsByRoleAndSlot: array[role][time] => array<array{id:string,name:string}>
     */
    public function buildAvailability(string $date, Collection $services): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $slotTimes = $this->buildHourSlots();

        // Group selected services by required role.
        $servicesByRole = $services->groupBy(fn(Service $s) => $this->roleResolver->requiredRoleFor($s));

        $requiredRoles = [];
        foreach ($servicesByRole as $role => $svc) {
            $requiredRoles[$role] = ['services' => $svc->values()];
        }

        // Preload staff by role (dev: all active staff of that role are considered qualified)
        $staffByRole = [];
        foreach (array_keys($requiredRoles) as $role) {
            $staffByRole[$role] = Staff::query()
                ->where('is_active', true)
                ->where('role', $role)
                ->orderBy('full_name')
                ->get();
        }

        // Busy windows for staff for that date
        $busyByStaffId = $this->busyMapForDate($day);

        $staffOptionsByRoleAndSlot = [];
        $viableSlots = collect();

        foreach ($slotTimes as $time) {
            $start = Carbon::parse($day->toDateString().' '.$time);
            $end = (clone $start)->addHour();

            $slotOk = true;

            foreach (array_keys($requiredRoles) as $role) {
                $available = $staffByRole[$role]->filter(function (Staff $st) use ($busyByStaffId, $start, $end) {
                    $busy = $busyByStaffId[$st->id] ?? [];
                    foreach ($busy as [$bs, $be]) {
                        // overlap: start < busyEnd AND end > busyStart
                        if ($start->lt($be) && $end->gt($bs)) return false;
                    }
                    return true;
                })->values();

                // Convert to simple arrays for Blade/JS
                $staffOptionsByRoleAndSlot[$role][$time] = $available->map(fn(Staff $s) => [
                    'id' => $s->id,
                    'name' => $s->full_name,
                ])->all();

                if ($available->isEmpty()) $slotOk = false;
            }

            if ($slotOk) $viableSlots->push($time);
        }

        return [
            'requiredRoles' => $requiredRoles,
            'slotTimes' => $slotTimes,
            'viableSlots' => $viableSlots,
            'staffOptionsByRoleAndSlot' => $staffOptionsByRoleAndSlot,
        ];
    }

    private function buildHourSlots(): Collection
    {
        // 09:00–17:00, last start is 16:00 for a 1-hour slot
        $times = [];
        for ($h = 9; $h <= 16; $h++) {
            $times[] = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00';
        }
        return collect($times);
    }

    private function busyMapForDate(Carbon $day): array
    {
        $startDay = (clone $day)->startOfDay();
        $endDay = (clone $day)->endOfDay();

        $items = AppointmentItem::query()
            ->select('appointment_items.*')
            ->join('appointment_groups', 'appointment_groups.id', '=', 'appointment_items.appointment_group_id')
            ->whereNotNull('appointment_items.staff_id')
            ->whereBetween('appointment_groups.starts_at', [$startDay, $endDay])
            ->whereNull('appointment_groups.deleted_at')
            ->whereNull('appointment_items.deleted_at')
            ->get();

        $busy = [];
        foreach ($items as $it) {
            $g = AppointmentGroup::find($it->appointment_group_id);
            if (!$g) continue;
            $busy[$it->staff_id][] = [$g->starts_at, $g->ends_at];
        }
        return $busy;
    }
}
