<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function seedCheck()
    {
        return response()->json([
            'services' => Service::count(),
            'staff' => Staff::count(),
        ]);
    }

    public function availability(Request $request)
    {
        $data = $request->validate([
            'staff_id' => ['nullable','string'],
            'start' => ['required','date'],
            'end' => ['required','date','after:start'],
        ]);

        $staffId = $data['staff_id'] ?? null;
        $start = Carbon::parse($data['start']);
        $end = Carbon::parse($data['end']);

        $q = Appointment::query()
            ->whereIn('status', ['booked','checked_in','completed'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);

        if ($staffId) $q->where('staff_id', $staffId);

        return response()->json([
            'success' => true,
            'available' => !$q->exists(),
        ]);
    }

    public function book(Request $request)
    {
        $data = $request->validate([
            'customer_name' => ['required','string','max:255'],
            'phone' => ['required','string','max:50'],
            'service_id' => ['required','string'],
            'staff_id' => ['nullable','string'],
            'start' => ['required','date'],
            'notes' => ['nullable','string'],
            'source' => ['nullable','string','max:50'],
        ]);

        $service = Service::where('id', $data['service_id'])->firstOrFail();

        $start = Carbon::parse($data['start']);
        $end = (clone $start)->addMinutes((int) $service->duration_minutes);

        // upsert customer by phone (paper data reality)
        $customer = Customer::updateOrCreate(
            ['phone' => $data['phone']],
            ['full_name' => $data['customer_name']]
        );

        // conflict check (if staff specified)
        if (!empty($data['staff_id'])) {
            $conflict = Appointment::query()
                ->where('staff_id', $data['staff_id'])
                ->whereIn('status', ['booked','checked_in','completed'])
                ->where('starts_at', '<', $end)
                ->where('ends_at', '>', $start)
                ->exists();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected staff is not available for that time.',
                ], 409);
            }
        }

        $appt = Appointment::create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'staff_id' => $data['staff_id'] ?? null,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => 'booked',
            'source' => $data['source'] ?? 'online',
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'appointment' => $appt->load(['customer','service','staff']),
        ]);
    }
}