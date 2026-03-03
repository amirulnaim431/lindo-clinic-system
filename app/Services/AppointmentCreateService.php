<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentCreateService
{
    public function __construct(
        private DevServiceRoleResolver $roleResolver
    ) {}

    public function create(array $data): AppointmentGroup
    {
        return DB::transaction(function () use ($data) {

            $date = $data['date'];
            $time = $data['time'];

            $serviceIds = collect($data['service_ids'] ?? [])->filter()->values();
            if ($serviceIds->isEmpty()) {
                throw ValidationException::withMessages(['service_ids' => 'Please select at least 1 service.']);
            }

            /** @var Collection<int,Service> $services */
            $services = Service::query()->whereIn('id', $serviceIds)->get();
            if ($services->count() !== $serviceIds->count()) {
                throw ValidationException::withMessages(['service_ids' => 'Some services are invalid.']);
            }

            $start = Carbon::parse($date.' '.$time);
            $end = (clone $start)->addHour();

            // Customer by phone
            $customer = Customer::query()->where('phone', $data['customer_phone'])->first();

            if (!$customer) {
                $customer = Customer::create([
                    'full_name' => $data['customer_name'] ?: $data['customer_phone'],
                    'phone' => $data['customer_phone'],
                    'email' => $data['customer_email'] ?? null,
                ]);
            } else {
                $updates = [];
                if (!empty($data['customer_name']) && $customer->full_name !== $data['customer_name']) {
                    $updates['full_name'] = $data['customer_name'];
                }
                if (!empty($data['customer_email']) && $customer->email !== $data['customer_email']) {
                    $updates['email'] = $data['customer_email'];
                }
                if ($updates) $customer->update($updates);
            }

            // Group services by role (same-role can be covered by one staff)
            $servicesByRole = $services->groupBy(fn(Service $s) => $this->roleResolver->requiredRoleFor($s));

            $chosenStaffByRole = collect($data['staff_by_role'] ?? []);
            foreach ($servicesByRole as $role => $svcs) {
                if (!$chosenStaffByRole->has($role) || empty($chosenStaffByRole->get($role))) {
                    throw ValidationException::withMessages(['staff_by_role' => "Missing staff selection for role: {$role}"]);
                }

                $staffId = $chosenStaffByRole->get($role);

                $staff = Staff::query()
                    ->where('id', $staffId)
                    ->where('role', $role)
                    ->where('is_active', true)
                    ->first();

                if (!$staff) {
                    throw ValidationException::withMessages(['staff_by_role' => "Invalid staff for role: {$role}"]);
                }

                // Overlap check: items (staff busy)
                $conflict = AppointmentItem::query()
                    ->where('staff_id', $staffId)
                    ->whereNull('deleted_at')
                    ->where('starts_at', '<', $end)
                    ->where('ends_at', '>', $start)
                    ->exists();

                if ($conflict) {
                    throw ValidationException::withMessages([
                        'time' => "{$staff->full_name} is no longer available at {$start->format('H:i')}.",
                    ]);
                }
            }

            $summary = $services->pluck('name')->implode(' + ');

            $group = AppointmentGroup::create([
                'customer_id' => $customer->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => AppointmentStatus::Booked,
                'source' => 'frontdesk',
                'notes' => $data['notes'] ?? null,
                'services_summary' => $summary,
            ]);

            // Create one item per ROLE (dev phase)
            foreach ($servicesByRole as $role => $svcs) {
                $representativeServiceId = $svcs->first()->id;

                AppointmentItem::create([
                    'appointment_group_id' => $group->id,
                    'service_id' => $representativeServiceId,
                    'staff_id' => $chosenStaffByRole->get($role),
                    'required_role' => $role,
                    'starts_at' => $start,
                    'ends_at' => $end,
                ]);
            }

            return $group;
        });
    }
}
