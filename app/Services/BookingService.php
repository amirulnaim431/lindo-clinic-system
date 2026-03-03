<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    /**
     * Create a booking with conflict detection.
     * Staff is optional: if null, we auto-pick an available staff.
     */
    public function book(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            /** @var Service $service */
            $service = Service::query()->findOrFail($data['service_id']);

            $start = CarbonImmutable::parse($data['start_at']); // expects "Y-m-d H:i"
            $end = $start->addMinutes((int) $service->duration_minutes);

            // Basic customer handling for now (scales later to profile/points/history)
            /** @var Customer $customer */
            $customer = Customer::query()->firstOrCreate(
                ['phone' => $data['customer_phone']],
                ['name' => $data['customer_name']]
            );

            // If staff chosen, validate availability; else pick any available
            $staffId = $data['staff_id'] ?? null;

            if ($staffId) {
                $staff = Staff::query()->findOrFail($staffId);

                if (!$this->isStaffAvailable($staff->id, $start, $end)) {
                    throw ValidationException::withMessages([
                        'start_at' => 'Selected staff is not available for that time.',
                    ]);
                }
            } else {
                $staffId = $this->pickAvailableStaffId($start, $end);
                if (!$staffId) {
                    throw ValidationException::withMessages([
                        'start_at' => 'No staff available for that time. Please choose another slot.',
                    ]);
                }
            }

            // Final safety: avoid race conditions (double-submit)
            if (!$this->isStaffAvailable($staffId, $start, $end)) {
                throw ValidationException::withMessages([
                    'start_at' => 'That slot was just taken. Please pick another time.',
                ]);
            }

            /** @var Appointment $appt */
            $appt = Appointment::query()->create([
                'customer_id' => $customer->id,
                'staff_id'    => $staffId,
                'service_id'  => $service->id,
                'start_at'    => $start,
                'end_at'      => $end,
                'status'      => 'booked', // adjust if your schema uses different statuses
                'notes'       => $data['notes'] ?? null,
            ]);

            return $appt;
        });
    }

    public function isStaffAvailable(string $staffId, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        // Overlap rule: existing.start < newEnd AND existing.end > newStart
        return !Appointment::query()
            ->where('staff_id', $staffId)
            ->whereNotIn('status', ['cancelled']) // adjust to your statuses
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->exists();
    }

    public function pickAvailableStaffId(CarbonImmutable $start, CarbonImmutable $end): ?string
    {
        $staffIds = Staff::query()->pluck('id');

        foreach ($staffIds as $id) {
            if ($this->isStaffAvailable($id, $start, $end)) {
                return $id;
            }
        }

        return null;
    }
}