<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Staff;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookingService)
    {
    }

    /**
     * Public booking page.
     * Route: GET / and GET /booking
     */
    public function index(Request $request)
    {
        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'duration_minutes']);

        $staff = Staff::query()
            ->where('is_active', true)
            ->get(['id', 'full_name', 'role_key']);
        $staff = Staff::sortForPicSelector($staff);

        return view('booking.index', [
            'services' => $services,
            'staff' => $staff,
            'today' => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Slots endpoint used by the booking UI.
     * Route: GET /booking/slots?date=YYYY-MM-DD&service_id=...&staff_id=...
     */
    public function slots(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'service_id' => ['required', 'string', 'exists:services,id'],
            'staff_id' => ['nullable', 'string', 'exists:staff,id'],
        ]);

        $date = Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay();

        $slots = $this->bookingService->getAvailableSlots(
            date: $date,
            serviceId: $validated['service_id'],
            staffId: $validated['staff_id'] ?? null
        );

        return response()->json([
            'date' => $validated['date'],
            'slots' => $slots,
        ]);
    }

    /**
     * Create a booking.
     * Route: POST /booking
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => ['required', 'string', 'exists:services,id'],
            'staff_id' => ['nullable', 'string', 'exists:staff,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->bookingService->bookFromPublicForm(
            serviceId: $validated['service_id'],
            staffId: $validated['staff_id'] ?? null,
            date: $validated['date'],
            time: $validated['time'],
            customerName: $validated['customer_name'],
            customerPhone: $validated['customer_phone'],
            notes: $validated['notes'] ?? null,
        );

        if (!$result['ok']) {
            return back()->withErrors(['time' => $result['message']])->withInput();
        }

        return redirect()->to('/booking')->with('success', 'Booking created.');
    }
}
