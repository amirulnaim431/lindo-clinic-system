<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Dev-phase scheduler:
 * - Clinic hours 09:00–17:00
 * - Fixed 60-minute session block
 * - Multiple services allowed; services with same role can share ONE staff
 *   IF that staff is qualified for ALL those services.
 */
class AppointmentScheduler
{
    public const SLOT_MINUTES = 60;
    public const OPEN_HOUR = 9;
    public const CLOSE_HOUR = 17; // last booking starts at 16:00

    /**
     * Returns slots with suggested staff assignments.
     *
     * @return array<int, array{label:string,start_at:Carbon,end_at:Carbon,assignments:array<string,array{id:string,name:string,role:string}>,service_ids:array<int,string>}>
     */
    public function availableSlots(Carbon $date, Collection $services): array
    {
        $services = $services->values();
        if ($services->isEmpty()) return [];

        $roleGroups = $this->groupServicesByRole($services);

        $slots = [];
        for ($hour = self::OPEN_HOUR; $hour <= (self::CLOSE_HOUR - 1); $hour++) {
            $start = $date->copy()->setTime($hour, 0, 0);
            $end = $start->copy()->addMinutes(self::SLOT_MINUTES);

            $assignments = [];
            $ok = true;

            foreach ($roleGroups as $role => $serviceIds) {
                $staff = $this->pickQualifiedAvailableStaff($role, $serviceIds, $start, $end);
                if (!$staff) {
                    $ok = false;
                    break;
                }
                $assignments[$role] = [
                    'id' => $staff->id,
                    'name' => $staff->full_name,
                    'role' => $staff->role,
                ];
            }

            if ($ok) {
                $slots[] = [
                    'label' => $start->format('H:i') . ' – ' . $end->format('H:i'),
                    'start_at' => $start,
                    'end_at' => $end,
                    'assignments' => $assignments,
                    'service_ids' => $services->pluck('id')->all(),
                ];
            }
        }

        return $slots;
    }

    /**
     * @param Collection<int,Service> $services
     * @return array<string,array<int,string>> role => [service_id...]
     */
    private function groupServicesByRole(Collection $services): array
    {
        // Dev rule: role mapping is based on service name.
        // Later, replace with a proper table (service_requirements).
        $map = [];
        foreach ($services as $s) {
            $role = $this->inferRoleForService($s);
            $map[$role] ??= [];
            $map[$role][] = $s->id;
        }
        return $map;
    }

    private function inferRoleForService(Service $service): string
    {
        $name = mb_strtolower($service->name);

        if (str_contains($name, 'nail')) return 'beautician';
        if (str_contains($name, 'injection')) return 'nurse';
        if (str_contains($name, 'detox')) return 'doctor';
        if (str_contains($name, 'consult')) return 'doctor';
        if (str_contains($name, 'weight')) return 'doctor';
        if (str_contains($name, 'facial')) return 'doctor';

        // default fallback (safe-ish): doctor
        return 'doctor';
    }

    /**
     * Staff must:
     * - be active
     * - match role
     * - be qualified for ALL services in the role group
     * - not be busy in the time window
     */
    private function pickQualifiedAvailableStaff(string $role, array $serviceIds, Carbon $start, Carbon $end): ?Staff
    {
        $serviceIds = array_values(array_unique($serviceIds));
        if (empty($serviceIds)) return null;

        $candidates = Staff::query()
            ->where('role', $role)
            ->where('is_active', true)
            ->whereHas('services', function ($q) use ($serviceIds) {
                $q->whereIn('services.id', $serviceIds);
            }, '=', count($serviceIds))
            ->get();
        $candidates = Staff::sortForPicSelector($candidates);

        foreach ($candidates as $staff) {
            if ($this->isStaffAvailable($staff->id, $start, $end)) {
                return $staff;
            }
        }
        return null;
    }

    private function isStaffAvailable(string $staffId, Carbon $start, Carbon $end): bool
    {
        // Busy if staff is assigned to ANY appointment item overlapping [start,end)
        return !Appointment::query()
            ->whereHas('items', fn($q) => $q->where('staff_id', $staffId))
            ->where(function ($q) use ($start, $end) {
                $q->where('starts_at', '<', $end)
                  ->where('ends_at', '>', $start);
            })
            ->exists();
    }
}
