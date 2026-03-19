<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'slot' => $this->sanitizeSlot($request->input('slot')),
        ];

        if (! is_array($filters['service_ids'])) {
            $filters['service_ids'] = [$filters['service_ids']];
        }

        $filters['service_ids'] = collect($filters['service_ids'])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $services = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role_key']);

        $appointmentGroupsQuery = AppointmentGroup::query()
            ->with(['customer', 'items.staff', 'items.service'])
            ->whereDate('starts_at', $filters['date'])
            ->orderBy('starts_at');

        if (! empty($filters['staff_id'])) {
            $appointmentGroupsQuery->whereHas('items', function ($query) use ($filters) {
                $query->where('staff_id', $filters['staff_id']);
            });
        }

        if (! empty($filters['status'])) {
            $appointmentGroupsQuery->where('status', $filters['status']);
        }

        $appointmentGroups = $appointmentGroupsQuery
            ->paginate(20)
            ->withQueryString();

        $availability = null;

        if (! empty($filters['service_ids'])) {
            $selectedServices = Service::query()
                ->with(['staff' => function ($query) {
                    $query->where('is_active', true)->orderBy('full_name');
                }])
                ->whereIn('id', $filters['service_ids'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            if ($selectedServices->isNotEmpty()) {
                $availability = $this->buildAvailability(
                    $selectedServices,
                    Carbon::parse($filters['date'])
                );
            }
        }

        $quickCreate = [
            'prefilled_slot' => $filters['slot'],
            'slot_is_available' => false,
            'slot_combinations' => [],
            'message' => null,
        ];

        if ($filters['slot'] !== null) {
            if ($availability === null) {
                $quickCreate['message'] = 'Time slot selected from calendar. Choose one or more services, then check availability to continue.';
            } else {
                $slotDetails = $availability['slots'][$filters['slot']] ?? null;

                if ($slotDetails && ! empty($slotDetails['combinations'])) {
                    $quickCreate['slot_is_available'] = true;
                    $quickCreate['slot_combinations'] = $slotDetails['combinations'];
                } else {
                    $quickCreate['message'] = 'The selected calendar slot is no longer available for the chosen services. Please pick another time.';
                }
            }
        }

        $statusOptions = AppointmentStatus::cases();

        return view('app.appointments.index', compact(
            'filters',
            'services',
            'staffList',
            'availability',
            'appointmentGroups',
            'statusOptions',
            'quickCreate'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'slot' => ['required', 'date_format:H:i'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'string', Rule::exists('services', 'id')],
            'selected_combination' => ['required', 'string'],
            'customer_full_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $selectedServices = Service::query()
            ->with(['staff' => function ($query) {
                $query->where('is_active', true)->orderBy('full_name');
            }])
            ->whereIn('id', $validated['service_ids'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($selectedServices->count() !== count($validated['service_ids'])) {
            return back()
                ->withErrors(['service_ids' => 'One or more selected services are invalid or inactive.'])
                ->withInput();
        }

        $decodedCombination = json_decode($validated['selected_combination'], true);

        if (! is_array($decodedCombination) || empty($decodedCombination['service_staff_map']) || empty($decodedCombination['duration_minutes'])) {
            return back()
                ->withErrors(['selected_combination' => 'Invalid staff combination selected.'])
                ->withInput();
        }

        $serviceStaffMap = $decodedCombination['service_staff_map'];
        $durationMinutes = max(30, (int) $decodedCombination['duration_minutes']);

        $selectedServiceIds = $selectedServices->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
        $combinationServiceIds = collect(array_keys($serviceStaffMap))->map(fn ($id) => (string) $id)->sort()->values()->all();

        if ($selectedServiceIds !== $combinationServiceIds) {
            return back()
                ->withErrors(['selected_combination' => 'Selected staff combination does not match the chosen services.'])
                ->withInput();
        }

        $date = Carbon::parse($validated['date']);
        $start = $date->copy()->setTimeFromTimeString($validated['slot'].':00');
        $end = $start->copy()->addMinutes($durationMinutes);

        $chosenStaffIds = array_values($serviceStaffMap);

        if (count($chosenStaffIds) !== count(array_unique($chosenStaffIds))) {
            return back()
                ->withErrors(['selected_combination' => 'The same staff member cannot be assigned to multiple concurrent services.'])
                ->withInput();
        }

        $resolvedAssignments = [];

        foreach ($selectedServices as $service) {
            $serviceId = (string) $service->id;
            $staffId = $serviceStaffMap[$serviceId] ?? null;

            if (! $staffId) {
                return back()
                    ->withErrors(['selected_combination' => "Missing staff selection for {$service->name}."])
                    ->withInput();
            }

            $staff = $service->staff()
                ->where('staff.id', $staffId)
                ->where('staff.is_active', true)
                ->first(['staff.id', 'staff.full_name', 'staff.role_key']);

            if (! $staff) {
                return back()
                    ->withErrors(['selected_combination' => "Selected staff is not eligible for {$service->name}."])
                    ->withInput();
            }

            $hasConflict = Staff::query()
                ->whereKey($staff->id)
                ->whereHas('appointmentItems', function ($query) use ($start, $end) {
                    $query->where('starts_at', '<', $end)
                        ->where('ends_at', '>', $start);
                })
                ->exists();

            if ($hasConflict) {
                return back()
                    ->withErrors(['slot' => "{$staff->full_name} is no longer available for {$validated['slot']}. Please choose another slot or refresh availability."])
                    ->withInput();
            }

            $resolvedAssignments[$serviceId] = $staff;
        }

        DB::transaction(function () use ($validated, $start, $end, $selectedServices, $resolvedAssignments) {
            $phone = trim($validated['customer_phone']);

            $customer = Customer::query()->firstOrCreate(
                ['phone' => $phone],
                ['full_name' => $validated['customer_full_name']]
            );

            if ($customer->full_name !== $validated['customer_full_name']) {
                $customer->full_name = $validated['customer_full_name'];
                $customer->save();
            }

            $group = AppointmentGroup::query()->create([
                'customer_id' => $customer->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => AppointmentStatus::Booked,
                'source' => 'admin',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($selectedServices as $service) {
                $assignedStaff = $resolvedAssignments[(string) $service->id];

                AppointmentItem::query()->create([
                    'appointment_group_id' => $group->id,
                    'service_id' => $service->id,
                    'staff_id' => $assignedStaff->id,
                    'required_role' => $assignedStaff->role_key,
                    'starts_at' => $start,
                    'ends_at' => $end,
                ]);
            }
        });

        return redirect()
            ->to(route('app.appointments.index', ['date' => $validated['date']]))
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

    private function sanitizeSlot(mixed $slot): ?string
    {
        if (! is_string($slot) || trim($slot) === '') {
            return null;
        }

        $slot = trim($slot);

        return preg_match('/^\d{2}:\d{2}$/', $slot) === 1 ? $slot : null;
    }

    private function buildAvailability(Collection $selectedServices, Carbon $date): array
    {
        $servicesSummary = $selectedServices->map(function ($service) {
            return [
                'id' => (string) $service->id,
                'name' => $service->name,
                'duration_minutes' => max(30, (int) ($service->duration_minutes ?: 60)),
                'eligible_staff' => $service->staff
                    ->where('is_active', true)
                    ->map(fn ($staff) => [
                        'id' => (string) $staff->id,
                        'full_name' => $staff->full_name,
                        'role_key' => $staff->role_key,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        $servicesWithoutEligibleStaff = collect($servicesSummary)
            ->filter(fn ($service) => empty($service['eligible_staff']))
            ->map(fn ($service) => $service['name'])
            ->values()
            ->all();

        $durationMinutes = max(
            30,
            (int) $selectedServices->max(function ($service) {
                return $service->duration_minutes ?: 60;
            })
        );

        $slotStart = $date->copy()->setTime(9, 0, 0);
        $slotEndLimit = $date->copy()->setTime(18, 0, 0);

        $slots = [];
        $viableSlots = [];

        while ($slotStart->copy()->addMinutes($durationMinutes)->lte($slotEndLimit)) {
            $start = $slotStart->copy();
            $end = $slotStart->copy()->addMinutes($durationMinutes);
            $timeKey = $start->format('H:i');

            $availableStaffByService = [];
            $serviceDebug = [];

            foreach ($selectedServices as $service) {
                $availableStaff = $service->staff()
                    ->where('staff.is_active', true)
                    ->whereDoesntHave('appointmentItems', function ($query) use ($start, $end) {
                        $query->where('starts_at', '<', $end)
                            ->where('ends_at', '>', $start);
                    })
                    ->orderBy('staff.full_name')
                    ->get(['staff.id', 'staff.full_name', 'staff.role_key'])
                    ->map(fn ($staff) => [
                        'id' => (string) $staff->id,
                        'full_name' => $staff->full_name,
                        'role_key' => $staff->role_key,
                    ])
                    ->values()
                    ->all();

                $availableStaffByService[(string) $service->id] = [
                    'service_name' => $service->name,
                    'staff' => $availableStaff,
                ];

                $serviceDebug[] = [
                    'service_name' => $service->name,
                    'available_count' => count($availableStaff),
                ];
            }

            $combinations = $this->generateValidCombinations($availableStaffByService, $durationMinutes);

            $slots[$timeKey] = [
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'duration_minutes' => $durationMinutes,
                'combinations' => $combinations,
                'service_debug' => $serviceDebug,
            ];

            if (! empty($combinations)) {
                $viableSlots[] = $timeKey;
            }

            $slotStart->addMinutes(30);
        }

        $fullyBookedMessage = null;

        if (empty($viableSlots) && empty($servicesWithoutEligibleStaff)) {
            $fullyBookedMessage = 'All eligible staff are fully booked for the selected services on this date.';
        }

        return [
            'selected_services' => $servicesSummary,
            'services_without_eligible_staff' => $servicesWithoutEligibleStaff,
            'viable_slots' => $viableSlots,
            'slots' => $slots,
            'duration_minutes' => $durationMinutes,
            'fully_booked_message' => $fullyBookedMessage,
        ];
    }

    private function generateValidCombinations(array $availableStaffByService, int $durationMinutes, int $limit = 50): array
    {
        $serviceIds = array_keys($availableStaffByService);
        $results = [];

        $walk = function (int $index, array $pickedStaffIds, array $staffMap, array $parts) use (&$walk, &$results, $serviceIds, $availableStaffByService, $durationMinutes, $limit) {
            if (count($results) >= $limit) {
                return;
            }

            if ($index >= count($serviceIds)) {
                $results[] = [
                    'label' => implode(' | ', $parts),
                    'payload' => json_encode([
                        'duration_minutes' => $durationMinutes,
                        'service_staff_map' => $staffMap,
                    ]),
                ];

                return;
            }

            $serviceId = $serviceIds[$index];
            $serviceName = $availableStaffByService[$serviceId]['service_name'];
            $staffOptions = $availableStaffByService[$serviceId]['staff'];

            foreach ($staffOptions as $staff) {
                if (in_array($staff['id'], $pickedStaffIds, true)) {
                    continue;
                }

                $nextPicked = $pickedStaffIds;
                $nextPicked[] = $staff['id'];

                $nextStaffMap = $staffMap;
                $nextStaffMap[$serviceId] = $staff['id'];

                $nextParts = $parts;
                $nextParts[] = $serviceName.': '.$staff['full_name'];

                $walk($index + 1, $nextPicked, $nextStaffMap, $nextParts);
            }
        };

        $walk(0, [], [], []);

        return $results;
    }
}
