<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Enums\AppointmentStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'date' => $request->input('date') ?: now()->format('Y-m-d'),
            'service_ids' => $request->input('service_ids', []),
            'staff_id' => $request->input('staff_id'),
            'status' => $request->input('status'),
        ];

        if (!is_array($filters['service_ids'])) {
            $filters['service_ids'] = [$filters['service_ids']];
        }

        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // IMPORTANT: staff table column is role_key (not role)
        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role_key']);

        $appointmentGroupsQuery = AppointmentGroup::query()
            ->with(['customer', 'items.staff', 'items.service'])
            ->whereDate('starts_at', $filters['date'])
            ->orderBy('starts_at');

        if (!empty($filters['staff_id'])) {
            $appointmentGroupsQuery->whereHas('items', function ($q) use ($filters) {
                $q->where('staff_id', $filters['staff_id']);
            });
        }

        if (!empty($filters['status'])) {
            $appointmentGroupsQuery->where('status', $filters['status']);
        }

        $appointmentGroups = $appointmentGroupsQuery
            ->paginate(20)
            ->withQueryString();

        // Availability for create form
        $availability = null;

        if (!empty($filters['service_ids'])) {
            $selected = $services->whereIn('id', $filters['service_ids'])->values();

            $requiredRoles = [];
            foreach ($selected as $svc) {
                $roleKey = $this->resolveRoleKeyFromServiceName($svc->name);
                $requiredRoles[$roleKey] ??= ['services' => collect()];
                $requiredRoles[$roleKey]['services']->push($svc);
            }

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

                foreach ($requiredRoles as $roleKey => $info) {
                    $staff = Staff::query()
                        ->where('is_active', true)
                        ->where('role_key', $roleKey)
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

                    $staffOptionsByRoleAndSlot[$roleKey][$timeKey] = $staff
                        ->map(fn ($s) => ['id' => (string) $s->id, 'full_name' => $s->full_name])
                        ->values()
                        ->all();
                }

                if ($slotOk) {
                    $viableSlots[] = $timeKey;
                }

                $slotStart->addHour();
            }

            $availability = [
                'requiredRoles' => $requiredRoles,
                'viableSlots' => $viableSlots,
                'staffOptionsByRoleAndSlot' => $staffOptionsByRoleAndSlot,
            ];
        }

        $statusOptions = AppointmentStatus::cases();

        return view('app.appointments.index', compact(
            'filters',
            'services',
            'staffList',
            'availability',
            'appointmentGroups',
            'statusOptions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'slot' => ['required', 'date_format:H:i'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'string', Rule::exists('services', 'id')],
            'customer_full_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $date = Carbon::parse($validated['date']);
        $start = $date->copy()->setTimeFromTimeString($validated['slot'] . ':00');
        $end = $start->copy()->addHour();

        $selectedServices = Service::query()
            ->whereIn('id', $validated['service_ids'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($selectedServices->isEmpty()) {
            return back()->withErrors(['service_ids' => 'Selected services are invalid or inactive.'])->withInput();
        }

        $requiredRoleKeys = [];
        foreach ($selectedServices as $svc) {
            $roleKey = $this->resolveRoleKeyFromServiceName($svc->name);
            $requiredRoleKeys[$roleKey] ??= collect();
            $requiredRoleKeys[$roleKey]->push($svc);
        }

        try {
            DB::transaction(function () use ($validated, $start, $end, $selectedServices, $requiredRoleKeys) {
                $phone = trim($validated['customer_phone']);

                $customer = Customer::query()->firstOrCreate(
                    ['phone' => $phone],
                    ['full_name' => $validated['customer_full_name']]
                );

                if ($customer->full_name !== $validated['customer_full_name']) {
                    $customer->full_name = $validated['customer_full_name'];
                    $customer->save();
                }

                // Race-safe staff selection per role_key
                $pickedStaffByRoleKey = [];

                foreach ($requiredRoleKeys as $roleKey => $svcList) {
                    $staff = Staff::query()
                        ->where('is_active', true)
                        ->where('role_key', $roleKey)
                        ->whereDoesntHave('appointmentItems', function ($q) use ($start, $end) {
                            $q->where('starts_at', '<', $end)
                              ->where('ends_at', '>', $start);
                        })
                        ->orderBy('full_name')
                        ->first();

                    if (!$staff) {
                        throw new \RuntimeException("No available staff for role: {$roleKey}");
                    }

                    $pickedStaffByRoleKey[$roleKey] = $staff;
                }

                $group = AppointmentGroup::query()->create([
                    'customer_id' => $customer->id,
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'status' => AppointmentStatus::Booked,
                    'source' => 'admin',
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($selectedServices as $svc) {
                    $roleKey = $this->resolveRoleKeyFromServiceName($svc->name);
                    $staff = $pickedStaffByRoleKey[$roleKey] ?? null;

                    AppointmentItem::query()->create([
                        'appointment_group_id' => $group->id,
                        'service_id' => $svc->id,
                        'staff_id' => $staff?->id,
                        'required_role' => $roleKey,
                        'starts_at' => $start,
                        'ends_at' => $end,
                    ]);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['slot' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->to('/app/appointments?date=' . $validated['date'])
            ->with('success', 'Appointment created.');
    }

    public function updateStatus(Request $request, AppointmentGroup $appointmentGroup)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(AppointmentStatus::values())],
        ]);

        $appointmentGroup->status = $validated['status'];
        $appointmentGroup->save();

        return back()->with('success', 'Status updated.');
    }

    /**
     * Use the staff.role_key values that actually exist in your DB.
     * (You previously added role_key with default 'therapist'.)
     */
    private function resolveRoleKeyFromServiceName(string $serviceName): string
    {
        $n = strtolower($serviceName);

        // If you use these role_keys in your staff table, keep them.
        if (str_contains($n, 'nail')) return 'beautician';
        if (str_contains($n, 'inject')) return 'nurse';

        // Safe default that matches your migration default
        return 'therapist';
    }
}