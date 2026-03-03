<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Models\Service;
use App\Models\Staff;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function create()
    {
        $serviceLabelCol = $this->firstExistingColumn('services', [
            'name',
            'service_name',
            'title',
        ]);

        $staffLabelCol = $this->firstExistingColumn('staff', [
            'name',
            'full_name',
            'staff_name',
            'display_name',
        ]);

        $services = Service::query()
            ->select(['id', 'duration_minutes'])
            ->selectRaw("`{$serviceLabelCol}` as label")
            ->orderBy('label')
            ->get();

        $staff = Staff::query()
            ->select(['id'])
            ->selectRaw("`{$staffLabelCol}` as label")
            ->orderBy('label')
            ->get();

        return view('booking.create', [
            'services' => $services,
            'staff' => $staff,
        ]);
    }

    /**
     * Return time slots for a given date/service/staff.
     * Query: ?date=YYYY-mm-dd&service_id=...&staff_id=... (optional)
     */
    public function slots(Request $request, BookingService $bookingService)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'service_id' => ['required', 'exists:services,id'],
            'staff_id' => ['nullable', 'exists:staff,id'],
        ]);

        $service = Service::query()->findOrFail($validated['service_id']);
        $duration = (int) $service->duration_minutes;

        // Clinic hours (adjust later in config)
        $day = CarbonImmutable::createFromFormat('Y-m-d', $validated['date']);
        $workStart = $day->setTime(9, 0);
        $workEnd   = $day->setTime(17, 0);

        // Slot step (15 min is typical)
        $stepMinutes = 15;

        $slots = [];
        $lastPossibleStart = $workEnd->subMinutes($duration);

        for ($t = $workStart; $t <= $lastPossibleStart; $t = $t->addMinutes($stepMinutes)) {
            $start = $t;
            $end = $t->addMinutes($duration);

            $available = false;

            if (!empty($validated['staff_id'])) {
                $available = $bookingService->isStaffAvailable($validated['staff_id'], $start, $end);
            } else {
                // Any staff: if at least one staff is free, the slot is available
                $available = $bookingService->pickAvailableStaffId($start, $end) !== null;
            }

            $slots[] = [
                'time' => $start->format('H:i'),
                'available' => $available,
            ];
        }

        return response()->json([
            'duration_minutes' => $duration,
            'slots' => $slots,
        ]);
    }

    public function store(StoreBookingRequest $request, BookingService $bookingService)
    {
        try {
            $appt = $bookingService->book($request->validated());

            return back()->with('success', sprintf(
                'Booking confirmed! Appointment ID: %s',
                $appt->id
            ));
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    private function firstExistingColumn(string $table, array $candidates): string
    {
        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        abort(500, "No suitable label column found for table '{$table}'. Add one of: " . implode(', ', $candidates));
    }
}