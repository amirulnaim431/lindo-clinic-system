<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\AppointmentSlotReservation;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    private const PLANNER_START_HOUR = 10;
    private const PLANNER_END_HOUR = 19;
    private const PLANNER_SLOT_DURATION_MINUTES = 45;
    private const PLANNER_SLOT_STEP_MINUTES = 60;
    private const PLANNER_SLOT_CAPACITY = 2;

    public function index(Request $request)
    {
        $mode = $request->input('mode') === 'checkin' ? 'checkin' : 'booking';

        $filters = [
            'date' => $request->input('date') ?: now()->format('Y-m-d'),
            'customer_id' => $request->input('customer_id'),
            'customer_full_name' => trim((string) $request->input('customer_full_name', '')),
            'customer_phone' => trim((string) $request->input('customer_phone', '')),
            'notes' => trim((string) $request->input('notes', '')),
            'staff_id' => $request->input('staff_id'),
            'status' => $this->normalizeStatusFilter($request->input('status')),
            'slot' => $this->sanitizeSlot($request->input('slot')),
        ];

        $servicesQuery = Service::query()
            ->with('optionGroups.values')
            ->with(['staff' => function ($query) {
                $query->where('is_active', true)->orderBy('full_name');
            }])
            ->where('is_active', true);

        if (Service::supportsCatalogFields()) {
            $servicesQuery
                ->orderBy('category_key')
                ->orderByRaw("CASE WHEN consultation_category_key IS NULL OR consultation_category_key = '' THEN 1 ELSE 0 END")
                ->orderBy('consultation_category_key')
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
                    'consultation_groups' => $key === 'consultations'
                        ? collect(Service::consultationCategoryOptions())
                            ->map(function (string $departmentLabel, string $departmentKey) use ($services) {
                                return [
                                    'key' => $departmentKey,
                                    'label' => $departmentLabel,
                                    'services' => $services
                                        ->filter(fn ($service) => $service->category_key === 'consultations' && $service->consultation_category_key === $departmentKey)
                                        ->values(),
                                ];
                            })
                            ->filter(fn (array $group) => $group['services']->isNotEmpty())
                            ->values()
                            ->all()
                        : [],
                    'services' => $services
                        ->filter(fn ($service) => (Service::supportsCatalogFields() ? $service->category_key : 'consultations') === $key)
                        ->values(),
                ];
            })
            ->filter(fn (array $group) => $group['services']->isNotEmpty())
            ->values();

        $staffList = Staff::query()
            ->where('is_active', true)
            ->get(['id', 'full_name', 'role_key', 'job_title', 'department', 'operational_role']);
        $staffList = Staff::sortForPicSelector($staffList);

        $selectedDayStart = Carbon::parse($filters['date'])->startOfDay();
        $selectedDayEnd = Carbon::parse($filters['date'])->endOfDay();

        $appointmentGroupsQuery = AppointmentGroup::query()
            ->with(['customer', 'items.staff', 'items.service', 'items.optionSelections'])
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

        $appointmentGroups = $appointmentGroupsQuery->get();

        $plannerBoard = $this->buildPlannerBoard(Carbon::parse($filters['date']), $staffList);

        $statusOptions = AppointmentStatus::cases();

        return view('app.appointments.index', compact(
            'mode',
            'filters',
            'services',
            'serviceCategories',
            'staffList',
            'plannerBoard',
            'appointmentGroups',
            'statusOptions',
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'string', Rule::exists('customers', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'customer_full_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'booking_payload' => ['required', 'string'],
        ]);

        $payload = json_decode($validated['booking_payload'], true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'booking_payload' => 'The booking draft could not be read. Please rebuild the appointment and try again.',
            ]);
        }

        $serviceInstances = collect($payload['services'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['instance_id'] ?? null) && filled($row['service_id'] ?? null))
            ->map(function (array $row) {
                return [
                    'instance_id' => (string) $row['instance_id'],
                    'service_id' => (string) $row['service_id'],
                    'selected_options' => $this->normalizeSelectedOptionsInput([
                        (string) ($row['instance_id'] ?? '') => $row['selected_options'] ?? [],
                    ])[(string) ($row['instance_id'] ?? '')] ?? [],
                ];
            })
            ->values();

        $assignments = collect($payload['assignments'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['instance_id'] ?? null) && filled($row['staff_id'] ?? null) && filled($row['start_time'] ?? null))
            ->map(function (array $row) use ($validated) {
                return [
                    'instance_id' => (string) $row['instance_id'],
                    'staff_id' => (string) $row['staff_id'],
                    'date' => $validated['date'],
                    'start_time' => (string) $row['start_time'],
                    'slot_index' => max(1, min(self::PLANNER_SLOT_CAPACITY, (int) ($row['slot_index'] ?? 1))),
                ];
            })
            ->values();

        if ($serviceInstances->isEmpty()) {
            throw ValidationException::withMessages([
                'booking_payload' => 'Choose at least one service before creating the appointment.',
            ]);
        }

        if ($assignments->count() !== $serviceInstances->count()) {
            throw ValidationException::withMessages([
                'booking_payload' => 'Assign every selected service to an eligible staff time box before submitting.',
            ]);
        }

        $duplicateAssignments = $assignments->pluck('instance_id')->duplicates();
        if ($duplicateAssignments->isNotEmpty()) {
            throw ValidationException::withMessages([
                'booking_payload' => 'Each selected service can only be placed into one staff time box.',
            ]);
        }

        $duplicateBoxes = $assignments
            ->map(fn (array $assignment) => implode('|', [
                $assignment['staff_id'],
                $assignment['date'],
                $assignment['start_time'],
                $assignment['slot_index'],
            ]))
            ->duplicates();

        if ($duplicateBoxes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'booking_payload' => 'Each staff time box can only hold one selected service. Choose another empty box.',
            ]);
        }

        $selectedServicesQuery = Service::query()
            ->with('optionGroups.values')
            ->with(['staff' => function ($query) {
                $query->where('is_active', true)->orderBy('full_name');
            }])
            ->whereIn('id', $serviceInstances->pluck('service_id')->unique()->all())
            ->where('is_active', true);

        if (Service::supportsCatalogFields()) {
            $selectedServicesQuery->orderBy('display_order');
        }

        $selectedServices = $selectedServicesQuery
            ->orderBy('name')
            ->get();

        if ($selectedServices->count() !== $serviceInstances->pluck('service_id')->unique()->count()) {
            throw ValidationException::withMessages([
                'booking_payload' => 'One or more selected services are invalid or inactive.',
            ]);
        }

        $servicesById = $selectedServices->keyBy(fn ($service) => (string) $service->id);
        $serviceInstancesById = $serviceInstances->keyBy('instance_id');
        $resolvedSelections = $this->resolveServiceInstanceOptions($serviceInstances, $servicesById);
        $resolvedAssignments = [];

        foreach ($assignments as $assignment) {
            $instance = $serviceInstancesById->get($assignment['instance_id']);
            $service = $instance ? $servicesById->get($instance['service_id']) : null;

            if (! $instance || ! $service) {
                throw ValidationException::withMessages([
                    'booking_payload' => 'One of the assigned services no longer exists. Please rebuild the booking.',
                ]);
            }

            $staff = $service->staff
                ->firstWhere('id', $assignment['staff_id']);

            if (! $staff) {
                throw ValidationException::withMessages([
                    'booking_payload' => "{$service->name} can only be assigned to eligible staff.",
                ]);
            }

            $start = Carbon::createFromFormat('Y-m-d H:i', $assignment['date'].' '.$assignment['start_time']);
            $end = $start->copy()->addMinutes(max(30, (int) ($service->duration_minutes ?: self::PLANNER_SLOT_DURATION_MINUTES)));
            $clinicStart = $start->copy()->setTime(self::PLANNER_START_HOUR, 0);
            $clinicEnd = $start->copy()->setTime(self::PLANNER_END_HOUR, 0);

            if ($start->lt($clinicStart) || $end->gt($clinicEnd)) {
                throw ValidationException::withMessages([
                    'booking_payload' => "Assigned time for {$service->name} sits outside clinic hours.",
                ]);
            }

            $draftOverlapCount = collect($resolvedAssignments)
                ->filter(function (array $row) use ($staff, $start, $end) {
                    return (string) $row['staff']->id === (string) $staff->id
                        && $row['start']->lt($end)
                        && $row['end']->gt($start);
                })
                ->count();

            if ($draftOverlapCount >= self::PLANNER_SLOT_CAPACITY) {
                throw ValidationException::withMessages([
                    'booking_payload' => "{$staff->full_name} has too many selected services in the same time window. Pick another empty box.",
                ]);
            }

            $resolvedAssignments[] = [
                'instance_id' => $assignment['instance_id'],
                'service' => $service,
                'staff' => $staff,
                'start' => $start,
                'end' => $end,
                'slot_index' => $assignment['slot_index'],
            ];
        }

        $visitStart = collect($resolvedAssignments)->min(fn (array $row) => $row['start']) ?? Carbon::parse($validated['date'])->setTime(self::PLANNER_START_HOUR, 0);
        $visitEnd = collect($resolvedAssignments)->max(fn (array $row) => $row['end']) ?? $visitStart->copy()->addMinutes(self::PLANNER_SLOT_DURATION_MINUTES);

        try {
            DB::transaction(function () use ($validated, $visitStart, $visitEnd, $resolvedAssignments, $resolvedSelections) {
                foreach ($resolvedAssignments as $assignment) {
                    $this->assertStaffCapacityAvailable(
                        $assignment['staff'],
                        $assignment['start'],
                        $assignment['end']
                    );
                }

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

                foreach ($resolvedAssignments as $assignment) {
                    $service = $assignment['service'];
                    $serviceInstanceId = $assignment['instance_id'];
                    $assignedStaff = $assignment['staff'];

                    $appointmentItem = AppointmentItem::query()->create([
                        'appointment_group_id' => $group->id,
                        'service_id' => $service->id,
                        'service_name_snapshot' => $this->buildServiceSnapshotName($service, $resolvedSelections[$serviceInstanceId] ?? []),
                        'service_category_key_snapshot' => $service->category_key,
                        'service_category_label_snapshot' => $service->category_label,
                        'staff_id' => $assignedStaff->id,
                        'staff_name_snapshot' => $assignedStaff->full_name,
                        'staff_role_snapshot' => $assignedStaff->role_key,
                        'required_role' => $assignedStaff->role_key,
                        'starts_at' => $assignment['start'],
                        'ends_at' => $assignment['end'],
                    ]);

                    $this->reserveStaffTimeBox($appointmentItem, $assignedStaff, $assignment['start'], $assignment['slot_index']);

                    $appointmentItem->optionSelections()->createMany(
                        collect($resolvedSelections[$serviceInstanceId] ?? [])
                            ->map(fn (array $selection) => [
                                'service_option_group_id' => $selection['group_id'],
                                'service_option_value_id' => $selection['value_id'],
                                'option_group_name' => $selection['group_name'],
                                'option_value_label' => $selection['value_label'],
                                'display_order' => $selection['display_order'],
                            ])
                            ->values()
                            ->all()
                    );
                }
            });
        } catch (QueryException $exception) {
            if ($this->isSlotReservationCollision($exception)) {
                throw ValidationException::withMessages([
                    'booking_payload' => 'That staff time box was just taken by another booking. Refresh the board and choose another empty box.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->to(route('app.calendar', ['date' => $validated['date']]))
            ->with('success', 'Appointment created.');
    }

    private function assertStaffCapacityAvailable(Staff $staff, Carbon $start, Carbon $end): void
    {
        $existingCount = AppointmentItem::query()
            ->where('staff_id', $staff->id)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->lockForUpdate()
            ->count();

        if ($existingCount >= self::PLANNER_SLOT_CAPACITY) {
            throw ValidationException::withMessages([
                'booking_payload' => "{$staff->full_name} is already full in that time window. Pick another empty box.",
            ]);
        }
    }

    private function reserveStaffTimeBox(AppointmentItem $appointmentItem, Staff $staff, Carbon $start, int $slotIndex): void
    {
        AppointmentSlotReservation::query()->create([
            'appointment_item_id' => $appointmentItem->id,
            'staff_id' => $staff->id,
            'slot_date' => $start->toDateString(),
            'start_time' => $start->format('H:i:s'),
            'slot_index' => max(1, min(self::PLANNER_SLOT_CAPACITY, $slotIndex)),
        ]);
    }

    private function syncStaffTimeBoxReservation(AppointmentItem $appointmentItem, ?Staff $staff, Carbon $start): void
    {
        if (! $staff) {
            $appointmentItem->slotReservation()->delete();
            return;
        }

        $slotIndex = $this->findAvailableSlotIndex(
            $staff,
            $start,
            $appointmentItem->slotReservation?->slot_index,
            (string) $appointmentItem->id
        );

        $appointmentItem->slotReservation()->updateOrCreate(
            ['appointment_item_id' => $appointmentItem->id],
            [
                'staff_id' => $staff->id,
                'slot_date' => $start->toDateString(),
                'start_time' => $start->format('H:i:s'),
                'slot_index' => $slotIndex,
            ]
        );
    }

    private function findAvailableSlotIndex(Staff $staff, Carbon $start, ?int $preferredSlotIndex = null, ?string $exceptAppointmentItemId = null): int
    {
        $takenSlotIndexes = AppointmentSlotReservation::query()
            ->where('staff_id', $staff->id)
            ->where('slot_date', $start->toDateString())
            ->where('start_time', $start->format('H:i:s'))
            ->when($exceptAppointmentItemId, fn ($query) => $query->where('appointment_item_id', '!=', $exceptAppointmentItemId))
            ->lockForUpdate()
            ->pluck('slot_index')
            ->map(fn ($slotIndex) => (int) $slotIndex)
            ->all();

        $preferredSlotIndex = $preferredSlotIndex ? max(1, min(self::PLANNER_SLOT_CAPACITY, $preferredSlotIndex)) : null;

        if ($preferredSlotIndex && ! in_array($preferredSlotIndex, $takenSlotIndexes, true)) {
            return $preferredSlotIndex;
        }

        for ($slotIndex = 1; $slotIndex <= self::PLANNER_SLOT_CAPACITY; $slotIndex++) {
            if (! in_array($slotIndex, $takenSlotIndexes, true)) {
                return $slotIndex;
            }
        }

        throw ValidationException::withMessages([
            'booking_payload' => "{$staff->full_name} is already full in that time window. Pick another empty box.",
        ]);
    }

    private function isSlotReservationCollision(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = $exception->getMessage();

        return $sqlState === '23000'
            && (
                ($driverCode === '1062' && str_contains($message, 'asr_staff_slot_unique'))
                || ($driverCode === '19' && str_contains($message, 'appointment_slot_reservations'))
            );
    }

    private function buildPlannerBoard(Carbon $date, Collection $staffList): array
    {
        $slots = collect();
        $cursor = $date->copy()->setTime(self::PLANNER_START_HOUR, 0);
        $cutoff = $date->copy()->setTime(self::PLANNER_END_HOUR, 0);

        while ($cursor->copy()->addMinutes(self::PLANNER_SLOT_DURATION_MINUTES)->lte($cutoff)) {
            $slotStart = $cursor->copy();
            $slotEnd = $cursor->copy()->addMinutes(self::PLANNER_SLOT_DURATION_MINUTES);

            $slots->push([
                'time' => $slotStart->format('H:i'),
                'label' => $slotStart->format('g:i A').' - '.$slotEnd->format('g:i A'),
                'start' => $slotStart,
                'end' => $slotEnd,
            ]);

            $cursor->addMinutes(self::PLANNER_SLOT_STEP_MINUTES);
        }

        $items = AppointmentItem::query()
            ->with(['group.customer:id,full_name,phone', 'service:id,name', 'optionSelections'])
            ->where('starts_at', '<=', $date->copy()->endOfDay())
            ->where('ends_at', '>=', $date->copy()->startOfDay())
            ->get();

        $occupancy = [];

        foreach ($staffList as $staff) {
            foreach ($slots as $slot) {
                $overlapping = $items->filter(function (AppointmentItem $item) use ($staff, $slot) {
                    return (string) $item->staff_id === (string) $staff->id
                        && $item->starts_at
                        && $item->ends_at
                        && $item->starts_at->lt($slot['end'])
                        && $item->ends_at->gt($slot['start']);
                })->values();

                $occupancy[(string) $staff->id][$slot['time']] = [
                    'count' => $overlapping->count(),
                    'appointments' => $overlapping->map(function (AppointmentItem $item) {
                        $optionSuffix = $item->optionSelections
                            ->map(fn ($selection) => $selection->option_value_label)
                            ->filter()
                            ->implode(' | ');

                        return [
                            'id' => (string) $item->id,
                            'customer_name' => $item->group?->customer?->full_name ?: 'Customer',
                            'service_name' => $item->displayServiceName().($optionSuffix !== '' ? ' | '.$optionSuffix : ''),
                        ];
                    })->all(),
                ];
            }
        }

        return [
            'slots' => $slots->map(fn (array $slot) => [
                'time' => $slot['time'],
                'label' => $slot['label'],
            ])->all(),
            'capacity_per_slot' => self::PLANNER_SLOT_CAPACITY,
            'staff' => $staffList->map(function (Staff $staff) {
                return [
                    'id' => (string) $staff->id,
                    'full_name' => $staff->full_name,
                    'role_key' => $staff->role_key,
                    'role_label' => $staff->job_title
                        ?: str((string) ($staff->role_key ?: $staff->operational_role ?: 'Staff'))->replace('_', ' ')->title()->toString(),
                    'appointment_group_key' => Staff::appointmentGroupKeyForStaff($staff),
                    'appointment_group_label' => Staff::appointmentGroupLabelForStaff($staff),
                ];
            })->values()->all(),
            'occupancy' => $occupancy,
        ];
    }

    private function resolveServiceInstanceOptions(Collection $serviceInstances, Collection $servicesById): array
    {
        $resolved = [];
        $errors = [];

        foreach ($serviceInstances as $instance) {
            $service = $servicesById->get($instance['service_id']);
            $instanceId = $instance['instance_id'];
            $serviceSelections = $instance['selected_options'] ?? [];

            if (! $service) {
                continue;
            }

            foreach ($service->optionGroups as $group) {
                $groupId = (string) $group->id;
                $valueId = $serviceSelections[$groupId] ?? null;
                $isRequired = (bool) ($group->pivot?->is_required ?? true);

                if (! $valueId) {
                    if ($isRequired) {
                        $errors[] = 'Choose a '.$group->name.' option for '.$service->name.'.';
                    }

                    continue;
                }

                $value = $group->values->firstWhere('id', $valueId);

                if (! $value) {
                    $errors[] = 'Selected option is invalid for '.$service->name.'.';
                    continue;
                }

                $resolved[$instanceId][] = [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'value_id' => $value->id,
                    'value_label' => $value->label,
                    'display_order' => (int) ($group->pivot?->display_order ?? 0),
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'booking_payload' => $errors,
            ]);
        }

        return $resolved;
    }

    private function buildServiceSnapshotName(Service $service, array $resolvedSelections): string
    {
        if ((string) $service->service_code !== 'consult_tirze') {
            return $service->name;
        }

        $dosage = collect($resolvedSelections)
            ->firstWhere('group_name', 'Dosage');

        return $dosage && filled($dosage['value_label'] ?? null)
            ? 'Tirze '.$dosage['value_label']
            : $service->name;
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

            $clinicStart = $start->copy()->setTime(self::PLANNER_START_HOUR, 0);
            $clinicEnd = $start->copy()->setTime(self::PLANNER_END_HOUR, 0);

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
                    'service_name_snapshot' => $preparedItem['service']->name,
                    'service_category_key_snapshot' => $preparedItem['service']->category_key,
                    'service_category_label_snapshot' => $preparedItem['service']->category_label,
                    'staff_id' => $preparedItem['staff_id'],
                    'staff_name_snapshot' => $preparedItem['staff']?->full_name,
                    'staff_role_snapshot' => $preparedItem['staff']?->role_key,
                    'required_role' => $preparedItem['staff']?->role_key,
                    'starts_at' => $preparedItem['start'],
                    'ends_at' => $preparedItem['end'],
                ]);

                $this->syncStaffTimeBoxReservation(
                    $preparedItem['item']->fresh('slotReservation'),
                    $preparedItem['staff'],
                    $preparedItem['start']
                );
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

                $this->syncStaffTimeBoxReservation(
                    $schedule['item']->fresh('slotReservation.staff'),
                    $schedule['item']->staff,
                    $schedule['start']
                );
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

            $this->syncStaffTimeBoxReservation(
                $appointmentItem->fresh('slotReservation.staff'),
                $appointmentItem->staff,
                $newStart
            );

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

    private function buildAvailability(Collection $selectedServices, Carbon $date, string $arrangementMode, array $selectedStaff = []): array
    {
        $servicesSummary = $selectedServices->map(function ($service) {
            return [
                'id' => (string) $service->id,
                'name' => $service->name,
                'category_key' => $service->category_key,
                'category_label' => $service->category_label,
                'duration_minutes' => max(30, (int) ($service->duration_minutes ?: 60)),
                'eligible_staff' => $this->mapStaffForAppointmentFlow(
                    $service->staff->where('is_active', true)
                ),
            ];
        })->values()->all();

        $servicesWithoutEligibleStaff = collect($servicesSummary)
            ->filter(fn ($service) => empty($service['eligible_staff']))
            ->map(fn ($service) => $service['name'])
            ->values()
            ->all();
        $servicesMissingSelectedStaff = $selectedServices
            ->filter(fn ($service) => empty($selectedStaff[(string) $service->id] ?? null))
            ->map(fn ($service) => $service->name)
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
                $selectedStaffId = $selectedStaff[$serviceId] ?? null;

                $availableStaffQuery = $service->staff()
                    ->where('staff.is_active', true)
                    ->whereDoesntHave('appointmentItems', function ($query) use ($serviceSchedule) {
                        $query->where('starts_at', '<', $serviceSchedule['end'])
                            ->where('ends_at', '>', $serviceSchedule['start']);
                    })
                    ->orderBy('staff.full_name');

                if ($selectedStaffId) {
                    $availableStaffQuery->where('staff.id', $selectedStaffId);
                }

                $availableStaff = $availableStaffQuery->get([
                    'staff.id',
                    'staff.full_name',
                    'staff.role_key',
                    'staff.operational_role',
                    'staff.job_title',
                    'staff.department',
                ]);

                $availableStaffByService[$serviceId] = [
                    'service_name' => $service->name,
                    'staff' => $this->mapStaffForAppointmentFlow($availableStaff),
                ];

                $serviceDebug[] = [
                    'service_name' => $service->name,
                    'available_count' => count($availableStaff),
                    'start' => $serviceSchedule['start']->format('H:i'),
                    'end' => $serviceSchedule['end']->format('H:i'),
                ];
            }

            $hasAvailability = $this->hasValidStaffSelectionForSchedules(
                $selectedServices,
                $availableStaffByService,
                $arrangementMode,
                $selectedStaff
            );

            $slots[$timeKey] = [
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'duration_minutes' => $durationMinutes,
                'is_available' => $hasAvailability,
                'service_debug' => $serviceDebug,
                'arrangement_mode' => $arrangementMode,
            ];

            if ($hasAvailability) {
                $viableSlots[] = $timeKey;
            }

            $slotStart->addMinutes(30);
        }

        $fullyBookedMessage = null;

        if ($servicesMissingSelectedStaff !== []) {
            $fullyBookedMessage = 'Choose a staff member for each selected service before reviewing available time slots.';
        } elseif (empty($viableSlots) && empty($servicesWithoutEligibleStaff)) {
            $insufficientSameSlotCoverage = false;

            if ($arrangementMode === 'same_slot') {
                $distinctEligibleStaffCount = collect($servicesSummary)
                    ->flatMap(fn ($service) => collect($service['eligible_staff'] ?? [])->pluck('id'))
                    ->filter()
                    ->unique()
                    ->count();

                $insufficientSameSlotCoverage = $distinctEligibleStaffCount < count($servicesSummary);
            }

            $fullyBookedMessage = $insufficientSameSlotCoverage
                ? 'No same-slot staff combination is possible for these selected services with the current staff assignments. Try Back-to-back or Custom.'
                : 'All eligible staff are fully booked for the selected services and arrangement on this date.';
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

    private function buildCustomAvailability(Collection $selectedServices, array $customSchedule, array $selectedStaff = []): array
    {
        $servicesSummary = $selectedServices->map(function ($service) use ($customSchedule) {
            $serviceId = (string) $service->id;
            $schedule = $customSchedule[$serviceId] ?? null;

            return [
                'id' => $serviceId,
                'name' => $service->name,
                'category_key' => $service->category_key,
                'category_label' => $service->category_label,
                'duration_minutes' => max(30, (int) ($service->duration_minutes ?: 60)),
                'scheduled_date' => $schedule['date'] ?? null,
                'scheduled_time' => $schedule['start_time'] ?? null,
                'eligible_staff' => $this->mapStaffForAppointmentFlow(
                    $service->staff->where('is_active', true)
                ),
            ];
        })->values()->all();

        $missingSchedules = collect($servicesSummary)
            ->filter(fn ($service) => empty($service['scheduled_date']) || empty($service['scheduled_time']))
            ->pluck('name')
            ->values()
            ->all();
        $servicesMissingSelectedStaff = $selectedServices
            ->filter(fn ($service) => empty($selectedStaff[(string) $service->id] ?? null))
            ->map(fn ($service) => $service->name)
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
                'custom_is_available' => false,
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
                'staff' => $this->mapStaffForAppointmentFlow($service->staff()
                    ->where('staff.is_active', true)
                    ->whereDoesntHave('appointmentItems', function ($query) use ($serviceSchedule) {
                        $query->where('starts_at', '<', $serviceSchedule['end'])
                            ->where('ends_at', '>', $serviceSchedule['start']);
                    })
                    ->orderBy('staff.full_name')
                    ->get([
                        'staff.id',
                        'staff.full_name',
                        'staff.role_key',
                        'staff.operational_role',
                        'staff.job_title',
                        'staff.department',
                    ])),
            ];
        }

        $hasAvailability = $this->hasValidStaffSelectionForSchedules(
            $selectedServices,
            $availableStaffByService,
            'custom',
            $selectedStaff
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
            'fully_booked_message' => $servicesMissingSelectedStaff !== []
                ? 'Choose a staff member for each selected service before reviewing availability.'
                : (! $hasAvailability ? 'The selected staff are not available for the chosen custom schedule.' : null),
            'arrangement_mode' => 'custom',
            'custom_schedule' => $customSchedule,
            'custom_is_available' => $hasAvailability,
            'custom_ready' => true,
            'custom_missing_message' => null,
        ];
    }

    private function hasValidStaffSelectionForSchedules(Collection $selectedServices, array $availableStaffByService, string $arrangementMode, array $selectedStaff): bool
    {
        $selectedServiceIds = $selectedServices->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
        $selectedStaffMap = collect($selectedStaff)
            ->mapWithKeys(fn ($staffId, $serviceId) => [(string) $serviceId => (string) $staffId])
            ->all();

        foreach ($selectedServiceIds as $serviceId) {
            $staffId = $selectedStaffMap[$serviceId] ?? null;

            if (! $staffId) {
                return false;
            }

            $availableIds = collect($availableStaffByService[$serviceId]['staff'] ?? [])
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

            if (! in_array($staffId, $availableIds, true)) {
                return false;
            }
        }

        if ($arrangementMode === 'same_slot') {
            $chosenStaffIds = array_values($selectedStaffMap);

            if (count($chosenStaffIds) !== count(array_unique($chosenStaffIds))) {
                return false;
            }
        }

        return true;
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

    private function mapStaffForAppointmentFlow(Collection $staffMembers): array
    {
        return $staffMembers
            ->sort(function (Staff $left, Staff $right) {
                $leftRank = Staff::appointmentGroupRankForStaff($left);
                $rightRank = Staff::appointmentGroupRankForStaff($right);

                if ($leftRank === $rightRank) {
                    return strcasecmp($left->full_name, $right->full_name);
                }

                return $leftRank <=> $rightRank;
            })
            ->map(function (Staff $staff) {
                return [
                    'id' => (string) $staff->id,
                    'full_name' => $staff->full_name,
                    'role_key' => $staff->role_key,
                    'role_label' => str((string) ($staff->job_title ?: $staff->role_key ?: $staff->operational_role ?: 'Staff'))
                        ->replace('_', ' ')
                        ->title()
                        ->toString(),
                    'appointment_group_key' => Staff::appointmentGroupKeyForStaff($staff),
                    'appointment_group_label' => Staff::appointmentGroupLabelForStaff($staff),
                    'appointment_group_rank' => Staff::appointmentGroupRankForStaff($staff),
                ];
            })
            ->values()
            ->all();
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

    private function normalizeSelectedOptionsInput(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        return collect($input)
            ->mapWithKeys(function ($groupSelections, $serviceId) {
                if (! is_array($groupSelections)) {
                    return [];
                }

                return [(string) $serviceId => collect($groupSelections)
                    ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
                    ->mapWithKeys(fn ($value, $groupId) => [(string) $groupId => (string) $value])
                    ->all()];
            })
            ->all();
    }

    private function normalizeSelectedStaffInput(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        return collect($input)
            ->filter(fn ($value) => is_scalar($value) && trim((string) $value) !== '')
            ->mapWithKeys(fn ($value, $serviceId) => [(string) $serviceId => (string) $value])
            ->all();
    }

    private function normalizeSelectedStaffForServices(array $selectedStaff, Collection $selectedServices): array
    {
        return $selectedServices
            ->mapWithKeys(function (Service $service) use ($selectedStaff) {
                $serviceId = (string) $service->id;
                $staffId = $selectedStaff[$serviceId] ?? null;
                $eligibleStaffIds = $service->staff->pluck('id')->map(fn ($id) => (string) $id);

                if (! $staffId || ! $eligibleStaffIds->contains((string) $staffId)) {
                    return [$serviceId => null];
                }

                return [$serviceId => (string) $staffId];
            })
            ->all();
    }

    private function normalizeSelectedOptionsForServices(array $selectedOptions, Collection $selectedServices): array
    {
        return $selectedServices
            ->mapWithKeys(function (Service $service) use ($selectedOptions) {
                $serviceId = (string) $service->id;
                $serviceSelections = $selectedOptions[$serviceId] ?? [];
                $allowedGroupIds = $service->optionGroups->pluck('id')->map(fn ($id) => (string) $id);

                return [$serviceId => collect($serviceSelections)
                    ->filter(fn ($value, $groupId) => $allowedGroupIds->contains((string) $groupId))
                    ->mapWithKeys(fn ($value, $groupId) => [(string) $groupId => (string) $value])
                    ->all()];
            })
            ->all();
    }

    private function resolveSelectedOptions(Collection $selectedServices, array $selectedOptions): array
    {
        $resolved = [];
        $errors = [];

        foreach ($selectedServices as $service) {
            $serviceId = (string) $service->id;
            $serviceSelections = $selectedOptions[$serviceId] ?? [];

            foreach ($service->optionGroups as $group) {
                $groupId = (string) $group->id;
                $valueId = $serviceSelections[$groupId] ?? null;
                $isRequired = (bool) ($group->pivot?->is_required ?? true);

                if (! $valueId) {
                    if ($isRequired) {
                        $errors[] = 'Choose a '.$group->name.' option for '.$service->name.'.';
                    }

                    continue;
                }

                $value = $group->values->firstWhere('id', $valueId);

                if (! $value) {
                    $errors[] = 'Selected option is invalid for '.$service->name.'.';
                    continue;
                }

                $resolved[$serviceId][] = [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'value_id' => $value->id,
                    'value_label' => $value->label,
                    'display_order' => (int) ($group->pivot?->display_order ?? 0),
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'selected_options' => $errors,
            ]);
        }

        return $resolved;
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
