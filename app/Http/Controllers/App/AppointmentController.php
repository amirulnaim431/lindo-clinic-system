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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $mode = $request->input('mode') === 'checkin' ? 'checkin' : 'booking';

        $filters = [
            'date' => $request->input('date') ?: now()->format('Y-m-d'),
            'service_ids' => $request->input('service_ids', []),
            'service_order' => $request->input('service_order', []),
            'arrangement_mode' => $this->normalizeArrangementMode($request->input('arrangement_mode')),
            'custom_schedule' => $request->input('custom_schedule', []),
            'staff_id' => $request->input('staff_id'),
            'status' => $this->normalizeStatusFilter($request->input('status')),
            'slot' => $this->sanitizeSlot($request->input('slot')),
        ];

        if (! is_array($filters['service_ids'])) {
            $filters['service_ids'] = [$filters['service_ids']];
        }

        if (! is_array($filters['service_order'])) {
            $filters['service_order'] = [$filters['service_order']];
        }

        $filters['service_ids'] = collect($filters['service_ids'])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $filters['service_order'] = collect($filters['service_order'])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $servicesQuery = Service::query()
            ->where('is_active', true);

        if (Service::supportsCatalogFields()) {
            $servicesQuery
                ->orderBy('category_key')
                ->orderBy('display_order');
        }

        $services = $servicesQuery
            ->orderBy('name')
            ->get();

        $serviceCategories = collect(Service::categoryOptions())
            ->map(function (string $label, string $key) use ($services) {
                return [
                    'key' => $key,
                    'label' => $label,
                    'services' => $services
                        ->filter(fn ($service) => (Service::supportsCatalogFields() ? $service->category_key : 'consultations') === $key)
                        ->values(),
                ];
            })
            ->filter(fn (array $group) => $group['services']->isNotEmpty())
            ->values();

        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role_key']);

        $selectedDayStart = Carbon::parse($filters['date'])->startOfDay();
        $selectedDayEnd = Carbon::parse($filters['date'])->endOfDay();

        $appointmentGroupsQuery = AppointmentGroup::query()
            ->with(['customer', 'items.staff', 'items.service'])
            ->where('starts_at', '<=', $selectedDayEnd)
            ->where('ends_at', '>=', $selectedDayStart)
            ->orderBy('starts_at');

        if (! empty($filters['staff_id'])) {
            $appointmentGroupsQuery->whereHas('items', function ($query) use ($filters) {
                $query->where('staff_id', $filters['staff_id']);
            });
        }

        if (! empty($filters['status'])) {
            if ($filters['status'] === 'reschedule') {
                $appointmentGroupsQuery->whereIn('status', ['cancelled', 'no_show']);
            } else {
                $appointmentGroupsQuery->where('status', $filters['status']);
            }
        }

        $appointmentGroups = $appointmentGroupsQuery
            ->paginate(20)
            ->withQueryString();

        $availability = null;
        $customSchedule = [];

        if (! empty($filters['service_ids'])) {
            $selectedServicesQuery = Service::query()
                ->with(['staff' => function ($query) {
                    $query->where('is_active', true)->orderBy('full_name');
                }])
                ->whereIn('id', $filters['service_ids'])
                ->where('is_active', true);

            if (Service::supportsCatalogFields()) {
                $selectedServicesQuery->orderBy('display_order');
            }

            $selectedServices = $selectedServicesQuery
                ->orderBy('name')
                ->get();

            if ($selectedServices->isNotEmpty()) {
                $selectedServices = $this->reorderServices($selectedServices, $filters['service_order']);
                $customSchedule = $this->normalizeCustomScheduleInput(
                    $filters['custom_schedule'],
                    $selectedServices,
                    Carbon::parse($filters['date'])
                );

                $availability = $filters['arrangement_mode'] === 'custom'
                    ? $this->buildCustomAvailability($selectedServices, $customSchedule)
                    : $this->buildAvailability(
                        $selectedServices,
                        Carbon::parse($filters['date']),
                        $filters['arrangement_mode']
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
                    $quickCreate['message'] = 'The selected calendar slot is no longer available for the chosen services and arrangement. Please pick another time.';
                }
            }
        }

        $statusOptions = AppointmentStatus::cases();

        return view('app.appointments.index', compact(
            'mode',
            'filters',
            'services',
            'serviceCategories',
            'staffList',
            'availability',
            'customSchedule',
            'appointmentGroups',
            'statusOptions',
            'quickCreate'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'string', Rule::exists('customers', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'slot' => ['nullable', 'date_format:H:i'],
            'arrangement_mode' => ['required', Rule::in(['same_slot', 'back_to_back', 'custom'])],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['required', 'string', Rule::exists('services', 'id')],
            'service_order' => ['nullable', 'array'],
            'service_order.*' => ['required', 'string', Rule::exists('services', 'id')],
            'custom_schedule' => ['nullable', 'array'],
            'custom_schedule.*.date' => ['nullable', 'date_format:Y-m-d'],
            'custom_schedule.*.start_time' => ['nullable', 'date_format:H:i'],
            'selected_combination' => ['required', 'string'],
            'customer_full_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $selectedServicesQuery = Service::query()
            ->with(['staff' => function ($query) {
                $query->where('is_active', true)->orderBy('full_name');
            }])
            ->whereIn('id', $validated['service_ids'])
            ->where('is_active', true);

        if (Service::supportsCatalogFields()) {
            $selectedServicesQuery->orderBy('display_order');
        }

        $selectedServices = $selectedServicesQuery
            ->orderBy('name')
            ->get();

        if ($selectedServices->count() !== count($validated['service_ids'])) {
            return back()
                ->withErrors(['service_ids' => 'One or more selected services are invalid or inactive.'])
                ->withInput();
        }

        $selectedServices = $this->reorderServices($selectedServices, $validated['service_order'] ?? []);
        $arrangementMode = $this->normalizeArrangementMode($validated['arrangement_mode']);
        $serviceOrderIds = $selectedServices->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        if ($arrangementMode !== 'custom' && empty($validated['slot'])) {
            return back()
                ->withErrors(['slot' => 'Choose an available slot before creating the appointment.'])
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

        if (($decodedCombination['arrangement_mode'] ?? $arrangementMode) !== $arrangementMode) {
            return back()
                ->withErrors(['selected_combination' => 'Selected staff combination does not match the chosen appointment arrangement.'])
                ->withInput();
        }

        $combinationServiceOrder = collect($decodedCombination['service_order'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        if ($combinationServiceOrder !== [] && $combinationServiceOrder !== $serviceOrderIds) {
            return back()
                ->withErrors(['selected_combination' => 'Selected staff combination no longer matches the chosen service order. Please check availability again.'])
                ->withInput();
        }

        $date = Carbon::parse($validated['date']);
        $customSchedule = $this->normalizeCustomScheduleInput(
            $validated['custom_schedule'] ?? [],
            $selectedServices,
            $date
        );

        if ($arrangementMode === 'custom') {
            $serviceSchedules = $this->buildCustomServiceSchedules($selectedServices, $customSchedule);

            if (count($serviceSchedules) !== $selectedServices->count()) {
                return back()
                    ->withErrors(['custom_schedule' => 'Set a date and time for every selected service in custom mode.'])
                    ->withInput();
            }
        } else {
            $visitStart = $date->copy()->setTimeFromTimeString($validated['slot'].':00');
            $serviceSchedules = $this->buildServiceSchedules($selectedServices, $visitStart, $arrangementMode);
            $visitEnd = collect($serviceSchedules)->max(fn ($schedule) => $schedule['end']) ?? $visitStart->copy()->addMinutes($durationMinutes);
            $clinicEnd = $date->copy()->setTime(18, 0, 0);

            if ($visitEnd->gt($clinicEnd)) {
                return back()
                    ->withErrors(['slot' => 'The selected service arrangement extends past clinic hours. Please choose an earlier slot.'])
                    ->withInput();
            }
        }

        $visitStart = collect($serviceSchedules)->min(fn ($schedule) => $schedule['start']) ?? $date->copy()->setTime(9, 0);
        $visitEnd = collect($serviceSchedules)->max(fn ($schedule) => $schedule['end']) ?? $visitStart->copy()->addMinutes($durationMinutes);

        $chosenStaffIds = array_values($serviceStaffMap);

        if ($arrangementMode === 'same_slot' && count($chosenStaffIds) !== count(array_unique($chosenStaffIds))) {
            return back()
                ->withErrors(['selected_combination' => 'The same staff member cannot be assigned to multiple concurrent services in the same slot.'])
                ->withInput();
        }

        $resolvedAssignments = [];

        foreach ($selectedServices as $service) {
            $serviceId = (string) $service->id;
            $staffId = $serviceStaffMap[$serviceId] ?? null;
            $schedule = $serviceSchedules[$serviceId] ?? null;

            if (! $staffId) {
                return back()
                    ->withErrors(['selected_combination' => "Missing staff selection for {$service->name}."])
                    ->withInput();
            }

            if (! $schedule) {
                return back()
                    ->withErrors(['selected_combination' => "Missing timing schedule for {$service->name}."])
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
                ->whereHas('appointmentItems', function ($query) use ($schedule) {
                    $query->where('starts_at', '<', $schedule['end'])
                        ->where('ends_at', '>', $schedule['start']);
                })
                ->exists();

            if ($hasConflict) {
                return back()
                    ->withErrors(['slot' => "{$staff->full_name} is no longer available for the selected arrangement. Please choose another slot or refresh availability."])
                    ->withInput();
            }

            $resolvedAssignments[$serviceId] = $staff;
        }

        DB::transaction(function () use ($validated, $visitStart, $visitEnd, $selectedServices, $resolvedAssignments, $serviceSchedules) {
            $phone = trim($validated['customer_phone']);
            $customer = null;

            if (! empty($validated['customer_id'])) {
                $customer = Customer::query()->find($validated['customer_id']);
            }

            if (! $customer) {
                $customer = Customer::query()->firstOrCreate(
                    ['phone' => $phone],
                    ['full_name' => $validated['customer_full_name']]
                );
            }

            if ($customer->full_name !== $validated['customer_full_name']) {
                $customer->full_name = $validated['customer_full_name'];
            }

            if ($customer->phone !== $phone) {
                $customer->phone = $phone;
            }

            $customer->save();

            $group = AppointmentGroup::query()->create([
                'customer_id' => $customer->id,
                'starts_at' => $visitStart,
                'ends_at' => $visitEnd,
                'status' => AppointmentStatus::Booked,
                'source' => 'admin',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($selectedServices as $service) {
                $serviceId = (string) $service->id;
                $assignedStaff = $resolvedAssignments[$serviceId];
                $schedule = $serviceSchedules[$serviceId];

                AppointmentItem::query()->create([
                    'appointment_group_id' => $group->id,
                    'service_id' => $service->id,
                    'staff_id' => $assignedStaff->id,
                    'required_role' => $assignedStaff->role_key,
                    'starts_at' => $schedule['start'],
                    'ends_at' => $schedule['end'],
                ]);
            }
        });

        return redirect()
            ->to(route('app.appointments.index', ['date' => $validated['date']]))
            ->with('success', 'Appointment created.');
    }

    public function customerSearch(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));

        if ($query === '') {
            return response()->json([
                'customers' => [],
            ]);
        }

        $digits = preg_replace('/\D+/', '', $query);

        $customers = Customer::query()
            ->select([
                'id',
                'full_name',
                'phone',
                'membership_code',
                'membership_type',
                'current_package',
            ])
            ->where(function ($builder) use ($query, $digits) {
                $like = '%'.$query.'%';

                $builder
                    ->where('full_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('membership_code', 'like', $like);

                if ($digits !== '') {
                    $builder->orWhereRaw(
                        'REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") like ?',
                        ['%'.$digits.'%']
                    );
                }
            })
            ->orderByRaw("CASE WHEN full_name IS NULL OR full_name = '' THEN 1 ELSE 0 END")
            ->orderBy('full_name')
            ->limit(5)
            ->get()
            ->map(function (Customer $customer) {
                return [
                    'id' => (string) $customer->id,
                    'full_name' => $customer->full_name ?: 'Unnamed Customer',
                    'phone' => $customer->phone ?: '',
                    'membership_code' => $customer->membership_code ?: '',
                    'membership_type' => $customer->membership_type ?: '',
                    'current_package' => $customer->current_package ?: '',
                ];
            })
            ->values();

        return response()->json([
            'customers' => $customers,
        ]);
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

    public function updateFromCalendar(Request $request, AppointmentGroup $appointmentGroup): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'string', Rule::exists('customers', 'id')],
            'customer_full_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(AppointmentStatus::values())],
            'source' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string'],
            'items.*.service_id' => ['required', 'string', Rule::exists('services', 'id')],
            'items.*.staff_id' => ['nullable', 'string', Rule::exists('staff', 'id')],
            'items.*.date' => ['required', 'date_format:Y-m-d'],
            'items.*.start_time' => ['required', 'date_format:H:i'],
            'items.*.end_time' => ['required', 'date_format:H:i'],
        ]);

        $appointmentGroup->loadMissing([
            'customer',
            'items:id,appointment_group_id,service_id,staff_id,starts_at,ends_at',
        ]);

        $existingItems = $appointmentGroup->items->keyBy(fn ($item) => (string) $item->id);

        foreach ($validated['items'] as $row) {
            if (! $existingItems->has((string) $row['id'])) {
                throw ValidationException::withMessages([
                    'items' => 'One or more linked service items could not be found for this appointment.',
                ]);
            }
        }

        $serviceIds = collect($validated['items'])->pluck('service_id')->map(fn ($id) => (string) $id)->unique()->values();
        $services = Service::query()
            ->whereIn('id', $serviceIds)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($service) => (string) $service->id);

        if ($services->count() !== $serviceIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more selected services are inactive or unavailable.',
            ]);
        }

        $staffIds = collect($validated['items'])
            ->pluck('staff_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $staffMembers = Staff::query()
            ->whereIn('id', $staffIds)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($staff) => (string) $staff->id);

        if ($staffIds->count() !== $staffMembers->count()) {
            throw ValidationException::withMessages([
                'items' => 'One or more assigned staff members are inactive or unavailable.',
            ]);
        }

        $preparedItems = collect($validated['items'])->map(function (array $row) use ($existingItems, $services, $staffMembers) {
            $item = $existingItems->get((string) $row['id']);
            $serviceId = (string) $row['service_id'];
            $staffId = filled($row['staff_id']) ? (string) $row['staff_id'] : null;
            $service = $services->get($serviceId);
            $staff = $staffId ? $staffMembers->get($staffId) : null;
            $start = Carbon::createFromFormat('Y-m-d H:i', $row['date'].' '.$row['start_time']);
            $end = Carbon::createFromFormat('Y-m-d H:i', $row['date'].' '.$row['end_time']);

            if ($end->lte($start)) {
                throw ValidationException::withMessages([
                    'items' => 'Each linked service must end after its start time.',
                ]);
            }

            $clinicStart = $start->copy()->setTime(9, 0);
            $clinicEnd = $start->copy()->setTime(18, 0);

            if ($start->lt($clinicStart) || $end->gt($clinicEnd)) {
                throw ValidationException::withMessages([
                    'items' => 'Appointments can only be scheduled within clinic hours from 09:00 to 18:00.',
                ]);
            }

            if ($staff && ! $service->staff()->where('staff.id', $staff->id)->where('staff.is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'items' => "{$staff->full_name} is not assigned to {$service->name}.",
                ]);
            }

            return [
                'item' => $item,
                'service' => $service,
                'staff' => $staff,
                'staff_id' => $staffId,
                'start' => $start,
                'end' => $end,
            ];
        })->values();

        foreach ($preparedItems as $index => $current) {
            if ($current['staff_id']) {
                $hasConflict = AppointmentItem::query()
                    ->where('staff_id', $current['staff_id'])
                    ->where('id', '!=', $current['item']->id)
                    ->where('starts_at', '<', $current['end'])
                    ->where('ends_at', '>', $current['start'])
                    ->exists();

                if ($hasConflict) {
                    $staffName = $current['staff']?->full_name ?? 'Assigned staff';

                    throw ValidationException::withMessages([
                        'items' => "{$staffName} is already occupied in one of the selected time blocks.",
                    ]);
                }
            }

            foreach ($preparedItems->slice($index + 1) as $other) {
                if (! $current['staff_id'] || $current['staff_id'] !== $other['staff_id']) {
                    continue;
                }

                if ($current['start']->lt($other['end']) && $current['end']->gt($other['start'])) {
                    $staffName = $current['staff']?->full_name ?? 'Assigned staff';

                    throw ValidationException::withMessages([
                        'items' => "{$staffName} is assigned to overlapping linked service times in this visit.",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($validated, $appointmentGroup, $preparedItems) {
            $phone = trim((string) ($validated['customer_phone'] ?? ''));
            $customer = null;

            if (! empty($validated['customer_id'])) {
                $customer = Customer::query()->find($validated['customer_id']);
            }

            if (! $customer && $phone !== '') {
                $customer = Customer::query()->firstOrCreate(
                    ['phone' => $phone],
                    ['full_name' => $validated['customer_full_name']]
                );
            }

            if (! $customer) {
                $customer = $appointmentGroup->customer ?: new Customer();
            }

            $customer->full_name = $validated['customer_full_name'];
            $customer->phone = $phone !== '' ? $phone : $customer->phone;
            $customer->save();

            foreach ($preparedItems as $preparedItem) {
                $preparedItem['item']->update([
                    'service_id' => $preparedItem['service']->id,
                    'staff_id' => $preparedItem['staff_id'],
                    'required_role' => $preparedItem['staff']?->role_key,
                    'starts_at' => $preparedItem['start'],
                    'ends_at' => $preparedItem['end'],
                ]);
            }

            $appointmentGroup->update([
                'customer_id' => $customer->id,
                'status' => $validated['status'],
                'source' => filled($validated['source'] ?? null) ? trim((string) $validated['source']) : null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncAppointmentGroupWindow($appointmentGroup);
        });

        return response()->json([
            'message' => 'Appointment updated successfully.',
        ]);
    }

    public function reschedule(Request $request, AppointmentGroup $appointmentGroup)
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
        ]);

        $appointmentGroup->loadMissing([
            'items:id,appointment_group_id,staff_id,starts_at,ends_at',
            'items.staff:id,full_name',
        ]);

        if ($appointmentGroup->items->isEmpty()) {
            throw ValidationException::withMessages([
                'starts_at' => 'This appointment cannot be rescheduled because it has no assigned service items.',
            ]);
        }

        $originalStart = $appointmentGroup->starts_at;
        $originalEnd = $appointmentGroup->ends_at;

        if (! $originalStart || ! $originalEnd) {
            throw ValidationException::withMessages([
                'starts_at' => 'This appointment is missing its current schedule window.',
            ]);
        }

        $newStart = Carbon::createFromFormat('Y-m-d H:i', $validated['starts_at']);

        $itemSchedules = $appointmentGroup->items
            ->map(function ($item) use ($originalStart, $newStart) {
                $itemStart = $item->starts_at ?: $originalStart;
                $itemEnd = $item->ends_at ?: $itemStart?->copy()->addMinutes(30);
                $offsetMinutes = $originalStart->diffInMinutes($itemStart);
                $durationMinutes = max(30, $itemStart->diffInMinutes($itemEnd));
                $newItemStart = $newStart->copy()->addMinutes($offsetMinutes);
                $newItemEnd = $newItemStart->copy()->addMinutes($durationMinutes);

                return [
                    'item' => $item,
                    'start' => $newItemStart,
                    'end' => $newItemEnd,
                ];
            })
            ->values();

        $newEnd = $itemSchedules->max(fn ($schedule) => $schedule['end']) ?? $newStart->copy();

        $clinicStart = $newStart->copy()->setTime(9, 0);
        $clinicEnd = $newStart->copy()->setTime(18, 0);

        if ($newStart->lt($clinicStart) || $newEnd->gt($clinicEnd)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Appointments can only be moved within clinic hours from 09:00 to 18:00.',
            ]);
        }

        foreach ($itemSchedules as $schedule) {
            $staffId = $schedule['item']->staff_id;

            if (! $staffId) {
                continue;
            }

            $hasConflict = AppointmentItem::query()
                ->where('staff_id', $staffId)
                ->where('appointment_group_id', '!=', $appointmentGroup->id)
                ->where('starts_at', '<', $schedule['end'])
                ->where('ends_at', '>', $schedule['start'])
                ->exists();

            if ($hasConflict) {
                $staffName = $schedule['item']->staff?->full_name ?? 'Assigned staff';

                throw ValidationException::withMessages([
                    'starts_at' => "{$staffName} is already occupied in that time block. Choose another empty slot.",
                ]);
            }
        }

        DB::transaction(function () use ($appointmentGroup, $newStart, $newEnd, $itemSchedules) {
            $appointmentGroup->update([
                'starts_at' => $newStart,
                'ends_at' => $newEnd,
            ]);

            foreach ($itemSchedules as $schedule) {
                $schedule['item']->update([
                    'starts_at' => $schedule['start'],
                    'ends_at' => $schedule['end'],
                ]);
            }
        });

        return response()->json([
            'message' => 'Appointment rescheduled successfully.',
            'starts_at' => $newStart->format('Y-m-d H:i:s'),
            'ends_at' => $newEnd->format('Y-m-d H:i:s'),
        ]);
    }

    public function rescheduleItem(Request $request, AppointmentItem $appointmentItem)
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date_format:Y-m-d H:i'],
        ]);

        $appointmentItem->loadMissing([
            'group:id,starts_at,ends_at',
            'staff:id,full_name',
        ]);

        if (! $appointmentItem->starts_at || ! $appointmentItem->ends_at) {
            throw ValidationException::withMessages([
                'starts_at' => 'This service item is missing its current schedule window.',
            ]);
        }

        $newStart = Carbon::createFromFormat('Y-m-d H:i', $validated['starts_at']);
        $durationMinutes = max(30, $appointmentItem->starts_at->diffInMinutes($appointmentItem->ends_at));
        $newEnd = $newStart->copy()->addMinutes($durationMinutes);
        $clinicStart = $newStart->copy()->setTime(9, 0);
        $clinicEnd = $newStart->copy()->setTime(18, 0);

        if ($newStart->lt($clinicStart) || $newEnd->gt($clinicEnd)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Appointments can only be moved within clinic hours from 09:00 to 18:00.',
            ]);
        }

        if ($appointmentItem->staff_id) {
            $hasConflict = AppointmentItem::query()
                ->where('staff_id', $appointmentItem->staff_id)
                ->where('id', '!=', $appointmentItem->id)
                ->where('starts_at', '<', $newEnd)
                ->where('ends_at', '>', $newStart)
                ->exists();

            if ($hasConflict) {
                $staffName = $appointmentItem->staff?->full_name ?? 'Assigned staff';

                throw ValidationException::withMessages([
                    'starts_at' => "{$staffName} is already occupied in that time block. Choose another empty slot.",
                ]);
            }
        }

        DB::transaction(function () use ($appointmentItem, $newStart, $newEnd) {
            $appointmentItem->update([
                'starts_at' => $newStart,
                'ends_at' => $newEnd,
            ]);

            $this->syncAppointmentGroupWindow($appointmentItem->group);
        });

        return response()->json([
            'message' => 'Service item rescheduled successfully.',
            'starts_at' => $newStart->format('Y-m-d H:i:s'),
            'ends_at' => $newEnd->format('Y-m-d H:i:s'),
        ]);
    }

    private function sanitizeSlot(mixed $slot): ?string
    {
        if (! is_string($slot) || trim($slot) === '') {
            return null;
        }

        $slot = trim($slot);

        return preg_match('/^\d{2}:\d{2}$/', $slot) === 1 ? $slot : null;
    }

    private function buildAvailability(Collection $selectedServices, Carbon $date, string $arrangementMode): array
    {
        $servicesSummary = $selectedServices->map(function ($service) {
            return [
                'id' => (string) $service->id,
                'name' => $service->name,
                'category_key' => $service->category_key,
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

        $durationMinutes = $arrangementMode === 'back_to_back'
            ? max(30, (int) $selectedServices->sum(fn ($service) => $service->duration_minutes ?: 60))
            : max(30, (int) $selectedServices->max(fn ($service) => $service->duration_minutes ?: 60));

        $slotStart = $date->copy()->setTime(9, 0, 0);
        $slotEndLimit = $date->copy()->setTime(18, 0, 0);

        $slots = [];
        $viableSlots = [];

        while ($slotStart->copy()->addMinutes($durationMinutes)->lte($slotEndLimit)) {
            $start = $slotStart->copy();
            $end = $slotStart->copy()->addMinutes($durationMinutes);
            $timeKey = $start->format('H:i');
            $serviceSchedules = $this->buildServiceSchedules($selectedServices, $start, $arrangementMode);

            $availableStaffByService = [];
            $serviceDebug = [];

            foreach ($selectedServices as $service) {
                $serviceId = (string) $service->id;
                $serviceSchedule = $serviceSchedules[$serviceId];

                $availableStaff = $service->staff()
                    ->where('staff.is_active', true)
                    ->whereDoesntHave('appointmentItems', function ($query) use ($serviceSchedule) {
                        $query->where('starts_at', '<', $serviceSchedule['end'])
                            ->where('ends_at', '>', $serviceSchedule['start']);
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

                $availableStaffByService[$serviceId] = [
                    'service_name' => $service->name,
                    'staff' => $availableStaff,
                ];

                $serviceDebug[] = [
                    'service_name' => $service->name,
                    'available_count' => count($availableStaff),
                    'start' => $serviceSchedule['start']->format('H:i'),
                    'end' => $serviceSchedule['end']->format('H:i'),
                ];
            }

            $combinations = $this->generateValidCombinations(
                $availableStaffByService,
                $durationMinutes,
                $arrangementMode,
                $selectedServices->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
            );

            $slots[$timeKey] = [
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'duration_minutes' => $durationMinutes,
                'combinations' => $combinations,
                'service_debug' => $serviceDebug,
                'arrangement_mode' => $arrangementMode,
            ];

            if (! empty($combinations)) {
                $viableSlots[] = $timeKey;
            }

            $slotStart->addMinutes(30);
        }

        $fullyBookedMessage = null;

        if (empty($viableSlots) && empty($servicesWithoutEligibleStaff)) {
            $fullyBookedMessage = 'All eligible staff are fully booked for the selected services and arrangement on this date.';
        }

        return [
            'selected_services' => $servicesSummary,
            'services_without_eligible_staff' => $servicesWithoutEligibleStaff,
            'viable_slots' => $viableSlots,
            'slots' => $slots,
            'duration_minutes' => $durationMinutes,
            'fully_booked_message' => $fullyBookedMessage,
            'arrangement_mode' => $arrangementMode,
        ];
    }

    private function buildCustomAvailability(Collection $selectedServices, array $customSchedule): array
    {
        $servicesSummary = $selectedServices->map(function ($service) use ($customSchedule) {
            $serviceId = (string) $service->id;
            $schedule = $customSchedule[$serviceId] ?? null;

            return [
                'id' => $serviceId,
                'name' => $service->name,
                'category_key' => $service->category_key,
                'duration_minutes' => max(30, (int) ($service->duration_minutes ?: 60)),
                'scheduled_date' => $schedule['date'] ?? null,
                'scheduled_time' => $schedule['start_time'] ?? null,
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

        $missingSchedules = collect($servicesSummary)
            ->filter(fn ($service) => empty($service['scheduled_date']) || empty($service['scheduled_time']))
            ->pluck('name')
            ->values()
            ->all();

        if ($missingSchedules !== []) {
            return [
                'selected_services' => $servicesSummary,
                'services_without_eligible_staff' => [],
                'viable_slots' => [],
                'slots' => [],
                'duration_minutes' => 0,
                'fully_booked_message' => null,
                'arrangement_mode' => 'custom',
                'custom_schedule' => $customSchedule,
                'custom_combinations' => [],
                'custom_ready' => false,
                'custom_missing_message' => 'Choose date and time for each selected service to check availability.',
            ];
        }

        $serviceSchedules = $this->buildCustomServiceSchedules($selectedServices, $customSchedule);
        $availableStaffByService = [];

        foreach ($selectedServices as $service) {
            $serviceId = (string) $service->id;
            $serviceSchedule = $serviceSchedules[$serviceId];

            $availableStaffByService[$serviceId] = [
                'service_name' => $service->name,
                'staff' => $service->staff()
                    ->where('staff.is_active', true)
                    ->whereDoesntHave('appointmentItems', function ($query) use ($serviceSchedule) {
                        $query->where('starts_at', '<', $serviceSchedule['end'])
                            ->where('ends_at', '>', $serviceSchedule['start']);
                    })
                    ->orderBy('staff.full_name')
                    ->get(['staff.id', 'staff.full_name', 'staff.role_key'])
                    ->map(fn ($staff) => [
                        'id' => (string) $staff->id,
                        'full_name' => $staff->full_name,
                        'role_key' => $staff->role_key,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        $combinations = $this->generateValidCombinations(
            $availableStaffByService,
            (int) collect($serviceSchedules)->sum('duration_minutes'),
            'custom',
            $selectedServices->pluck('id')->map(fn ($id) => (string) $id)->values()->all()
        );

        return [
            'selected_services' => $servicesSummary,
            'services_without_eligible_staff' => collect($availableStaffByService)
                ->filter(fn ($service) => empty($service['staff']))
                ->pluck('service_name')
                ->values()
                ->all(),
            'viable_slots' => [],
            'slots' => [],
            'duration_minutes' => (int) collect($serviceSchedules)->sum('duration_minutes'),
            'fully_booked_message' => empty($combinations) ? 'All eligible staff are fully booked for the chosen custom schedule.' : null,
            'arrangement_mode' => 'custom',
            'custom_schedule' => $customSchedule,
            'custom_combinations' => $combinations,
            'custom_ready' => true,
            'custom_missing_message' => null,
        ];
    }

    private function generateValidCombinations(array $availableStaffByService, int $durationMinutes, string $arrangementMode, array $serviceOrderIds, int $limit = 50): array
    {
        $serviceIds = array_keys($availableStaffByService);
        $results = [];

        $walk = function (int $index, array $pickedStaffIds, array $staffMap, array $parts) use (&$walk, &$results, $serviceIds, $availableStaffByService, $durationMinutes, $arrangementMode, $serviceOrderIds, $limit) {
            if (count($results) >= $limit) {
                return;
            }

            if ($index >= count($serviceIds)) {
                $results[] = [
                    'label' => implode(' | ', $parts),
                    'payload' => json_encode([
                        'duration_minutes' => $durationMinutes,
                        'arrangement_mode' => $arrangementMode,
                        'service_order' => $serviceOrderIds,
                        'service_staff_map' => $staffMap,
                    ]),
                ];

                return;
            }

            $serviceId = $serviceIds[$index];
            $serviceName = $availableStaffByService[$serviceId]['service_name'];
            $staffOptions = $availableStaffByService[$serviceId]['staff'];

            foreach ($staffOptions as $staff) {
                if ($arrangementMode === 'same_slot' && in_array($staff['id'], $pickedStaffIds, true)) {
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

    private function reorderServices(Collection $selectedServices, array $serviceOrderIds): Collection
    {
        $orderedIds = collect($serviceOrderIds)
            ->map(fn ($id) => (string) $id)
            ->values();

        if ($orderedIds->isEmpty()) {
            return $selectedServices->values();
        }

        $servicesById = $selectedServices->keyBy(fn ($service) => (string) $service->id);

        $ordered = $orderedIds
            ->map(fn ($id) => $servicesById->get($id))
            ->filter();

        $remaining = $selectedServices
            ->filter(fn ($service) => ! $orderedIds->contains((string) $service->id))
            ->values();

        return $ordered->concat($remaining)->values();
    }

    private function buildServiceSchedules(Collection $selectedServices, Carbon $visitStart, string $arrangementMode): array
    {
        $schedules = [];

        if ($arrangementMode === 'back_to_back') {
            $cursor = $visitStart->copy();

            foreach ($selectedServices as $service) {
                $duration = max(30, (int) ($service->duration_minutes ?: 60));
                $start = $cursor->copy();
                $end = $cursor->copy()->addMinutes($duration);

                $schedules[(string) $service->id] = [
                    'start' => $start,
                    'end' => $end,
                    'duration_minutes' => $duration,
                ];

                $cursor = $end->copy();
            }

            return $schedules;
        }

        $duration = max(30, (int) $selectedServices->max(fn ($service) => $service->duration_minutes ?: 60));
        $visitEnd = $visitStart->copy()->addMinutes($duration);

        foreach ($selectedServices as $service) {
            $schedules[(string) $service->id] = [
                'start' => $visitStart->copy(),
                'end' => $visitEnd->copy(),
                'duration_minutes' => $duration,
            ];
        }

        return $schedules;
    }

    private function buildCustomServiceSchedules(Collection $selectedServices, array $customSchedule): array
    {
        $schedules = [];

        foreach ($selectedServices as $service) {
            $serviceId = (string) $service->id;
            $schedule = $customSchedule[$serviceId] ?? null;

            if (! $schedule || empty($schedule['date']) || empty($schedule['start_time'])) {
                continue;
            }

            $duration = max(30, (int) ($service->duration_minutes ?: 60));
            $start = Carbon::createFromFormat('Y-m-d H:i', $schedule['date'].' '.$schedule['start_time']);
            $end = $start->copy()->addMinutes($duration);
            $clinicStart = $start->copy()->setTime(9, 0);
            $clinicEnd = $start->copy()->setTime(18, 0);

            if ($start->lt($clinicStart) || $end->gt($clinicEnd)) {
                continue;
            }

            $schedules[$serviceId] = [
                'start' => $start,
                'end' => $end,
                'duration_minutes' => $duration,
            ];
        }

        return $schedules;
    }

    private function normalizeArrangementMode(mixed $arrangementMode): string
    {
        return in_array($arrangementMode, ['back_to_back', 'custom'], true)
            ? (string) $arrangementMode
            : 'same_slot';
    }

    private function normalizeCustomScheduleInput(mixed $input, Collection $selectedServices, Carbon $defaultDate): array
    {
        $rows = is_array($input) ? $input : [];

        return $selectedServices
            ->mapWithKeys(function (Service $service) use ($rows, $defaultDate) {
                $serviceId = (string) $service->id;
                $row = $rows[$serviceId] ?? [];
                $date = is_array($row) ? trim((string) ($row['date'] ?? '')) : '';
                $startTime = is_array($row) ? trim((string) ($row['start_time'] ?? '')) : '';

                return [$serviceId => [
                    'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : $defaultDate->toDateString(),
                    'start_time' => preg_match('/^\d{2}:\d{2}$/', $startTime) === 1 ? $startTime : '',
                ]];
            })
            ->all();
    }

    private function normalizeStatusFilter(mixed $status): ?string
    {
        $normalized = trim((string) $status);

        if ($normalized === '') {
            return null;
        }

        return $normalized === 'reschedule' ? 'reschedule' : $normalized;
    }

    private function syncAppointmentGroupWindow(?AppointmentGroup $group): void
    {
        if (! $group) {
            return;
        }

        $group->loadMissing('items:id,appointment_group_id,starts_at,ends_at');

        $startsAt = $group->items
            ->pluck('starts_at')
            ->filter()
            ->sort()
            ->first();

        $endsAt = $group->items
            ->pluck('ends_at')
            ->filter()
            ->sortDesc()
            ->first();

        if (! $startsAt || ! $endsAt) {
            return;
        }

        $group->update([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
