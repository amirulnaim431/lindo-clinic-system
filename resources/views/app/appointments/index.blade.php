@php
    $mode = $mode ?? 'booking';
    $isCheckInMode = $mode === 'checkin';
@endphp

<x-internal-layout :title="$isCheckInMode ? 'Customer Check-In' : 'Appointments'" :subtitle="null">
    @php
        $filters = $filters ?? ['date' => now()->format('Y-m-d'), 'service_ids' => [], 'slot' => null];
        $selectedServiceIds = collect($filters['service_ids'] ?? [])->map(fn ($id) => (string) $id)->values()->all();
        $selectedDate = $filters['date'] ?? now()->format('Y-m-d');
        $selectedStatus = $filters['status'] ?? null;
        $prefilledSlot = $filters['slot'] ?? null;
        $selectedArrangementMode = $filters['arrangement_mode'] ?? 'same_slot';
        $services = $services ?? collect();
        $availability = $availability ?? null;
        $appointmentGroups = collect($appointmentGroups ?? []);
        $statusOptions = $statusOptions ?? [];
        $quickCreate = $quickCreate ?? ['prefilled_slot' => null, 'slot_is_available' => false, 'slot_combinations' => [], 'message' => null];
        $statusPalette = [
            'booked' => ['bg' => '#fff7ed', 'border' => '#fed7aa', 'text' => '#c2410c', 'label' => 'Pending'],
            'confirmed' => ['bg' => '#f0f9ff', 'border' => '#bae6fd', 'text' => '#0369a1', 'label' => 'Confirmed'],
            'checked_in' => ['bg' => '#f5f3ff', 'border' => '#ddd6fe', 'text' => '#6d28d9', 'label' => 'Checked In'],
            'completed' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#047857', 'label' => 'Completed'],
            'cancelled' => ['bg' => '#fff1f2', 'border' => '#fecdd3', 'text' => '#be123c', 'label' => 'Reschedule'],
            'no_show' => ['bg' => '#fff1f2', 'border' => '#fecdd3', 'text' => '#be123c', 'label' => 'Reschedule'],
        ];
        $slotOptions = [];
        if (! empty($availability['slots'])) {
            foreach ($availability['slots'] as $slotTime => $slotData) {
                $slotOptions[] = [
                    'time' => $slotTime,
                    'combinations' => $slotData['combinations'] ?? [],
                    'count' => count($slotData['combinations'] ?? []),
                    'is_available' => ! empty($slotData['combinations']),
                    'is_prefilled' => $prefilledSlot === $slotTime,
                ];
            }
        }
        $selectedSlotRow = $prefilledSlot ? ($availability['slots'][$prefilledSlot] ?? null) : null;
        $selectedServiceLabels = $services->whereIn('id', $selectedServiceIds)->pluck('name')->values()->all();
        $selectedServiceOrderIds = collect($filters['service_order'] ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => in_array($id, $selectedServiceIds, true))
            ->values();
        $selectedServiceOrderIds = $selectedServiceOrderIds
            ->concat(collect($selectedServiceIds)->reject(fn ($id) => $selectedServiceOrderIds->contains($id)))
            ->values()
            ->all();
        $selectedServiceOrderLabels = collect($selectedServiceOrderIds)
            ->map(fn ($id) => $services->firstWhere('id', $id)?->name)
            ->filter()
            ->values()
            ->all();
        $serviceCategories = collect($serviceCategories ?? []);
        $serviceCatalog = $services->mapWithKeys(function ($service) {
            return [
                (string) $service->id => [
                    'id' => (string) $service->id,
                    'name' => $service->name,
                    'category_key' => $service->category_key,
                    'description' => $service->description,
                    'duration' => (int) ($service->duration_minutes ?? 60),
                    'price' => $service->price,
                    'promo_price' => $service->promo_price,
                    'is_promo' => (bool) $service->is_promo,
                ],
            ];
        })->all();
        $selectedCategoryKey = collect($selectedServiceIds)
            ->map(fn ($id) => $services->firstWhere('id', $id)?->category_key)
            ->filter()
            ->first();
        $defaultCategoryKey = $selectedCategoryKey
            ?: ($serviceCategories->first()['key'] ?? 'consultations');
        $formatMembershipBalance = function ($value) {
            $raw = trim((string) $value);

            if ($raw === '') {
                return 'Pending update';
            }

            $normalized = preg_replace('/[^0-9.\-]/', '', $raw);

            if ($normalized !== null && $normalized !== '' && is_numeric($normalized)) {
                return 'RM '.number_format((float) $normalized, 0);
            }

            return $raw;
        };
        $customSchedule = $customSchedule ?? [];
        $selectedCustomerId = old('customer_id', $filters['customer_id'] ?? '');
        $selectedCustomerName = old('customer_full_name', $filters['customer_full_name'] ?? '');
        $selectedCustomerPhone = old('customer_phone', $filters['customer_phone'] ?? '');
        $shouldAutoOpenAvailability = ! empty($availability) && (bool) ($filters['open_availability'] ?? false);
        $slotIndex = collect($slotOptions)->keyBy('time');
        $combinationSource = collect($selectedArrangementMode === 'custom'
            ? ($availability['custom_combinations'] ?? [])
            : collect($slotOptions)->flatMap(fn ($slot) => $slot['combinations'] ?? [])->values()->all()
        )
            ->filter(fn ($combo) => ! empty($combo['payload']))
            ->unique('payload')
            ->map(function ($combo) {
                $decoded = json_decode($combo['payload'] ?? '', true);

                if (! is_array($decoded) || empty($decoded['service_staff_map'])) {
                    return null;
                }

                $map = collect($decoded['service_staff_map'])
                    ->mapWithKeys(fn ($staffId, $serviceId) => [(string) $serviceId => (string) $staffId])
                    ->all();
                ksort($map);

                return [
                    'payload' => $combo['payload'],
                    'map' => $map,
                ];
            })
            ->filter()
            ->values();
        $serviceSelectionSections = collect($availability['selected_services'] ?? [])
            ->map(function ($serviceSummary) use ($combinationSource) {
                $serviceId = (string) ($serviceSummary['id'] ?? '');
                $staffOptions = collect($serviceSummary['eligible_staff'] ?? [])
                    ->map(function ($staff) use ($serviceId, $combinationSource) {
                        $staffId = (string) ($staff['id'] ?? '');
                        $matchingPayloads = $combinationSource
                            ->filter(fn ($combo) => (($combo['map'][$serviceId] ?? null) === $staffId))
                            ->pluck('payload')
                            ->values()
                            ->all();

                        return [
                            'id' => $staffId,
                            'full_name' => $staff['full_name'],
                            'role_label' => $staff['role_label'] ?? $staff['role_key'] ?? 'Staff',
                            'group_label' => $staff['appointment_group_label'] ?? 'Others',
                            'group_rank' => (int) ($staff['appointment_group_rank'] ?? 6),
                            'matching_payloads' => $matchingPayloads,
                        ];
                    })
                    ->sortBy([
                        ['group_rank', 'asc'],
                        ['full_name', 'asc'],
                    ])
                    ->values();
                $staffOptionsByRank = $staffOptions->groupBy(fn ($option) => (int) ($option['group_rank'] ?? 6));
                $staffGroups = collect([
                    ['rank' => 1, 'label' => 'Management'],
                    ['rank' => 2, 'label' => 'Doctors'],
                    ['rank' => 3, 'label' => 'Nurses'],
                    ['rank' => 4, 'label' => 'Aesthetics'],
                    ['rank' => 5, 'label' => 'Spa'],
                    ['rank' => 6, 'label' => 'Others'],
                ])->map(function ($group) use ($staffOptionsByRank) {
                    return [
                        'label' => $group['label'],
                        'options' => collect($staffOptionsByRank->get($group['rank'], []))->values(),
                    ];
                })->filter(fn ($group) => $group['options']->isNotEmpty())->values();

                return [
                    'id' => $serviceId,
                    'name' => $serviceSummary['name'] ?? 'Service',
                    'scheduled_date' => $serviceSummary['scheduled_date'] ?? null,
                    'scheduled_time' => $serviceSummary['scheduled_time'] ?? null,
                    'staff_groups' => $staffGroups,
                ];
            })
            ->values();
    @endphp


    <div class="ops-shell">
        @if (session('success'))
            <div class="flash flash--success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash--error">
                Please fix the following:
                <ul style="margin:8px 0 0 18px; padding:0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $isCheckInMode && $quickCreate['message'])
            <div class="flash flash--warn">{{ $quickCreate['message'] }}</div>
        @endif

        <section class="panel" id="appointment-date-panel">
            <div class="panel-body">
                <form method="GET" action="{{ route('app.appointments.index') }}" class="form-grid" data-preserve-scroll-form>
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <div class="col-4 field-block">
                        <label class="field-label" for="date">Appointment date</label>
                        <input id="date" name="date" type="date" value="{{ $selectedDate }}" class="form-input" required>
                    </div>
                    <div class="col-8 field-block" style="align-self:end;">
                        <div class="btn-row">
                            <button type="submit" class="btn btn-primary">Apply date</button>
                            <a href="{{ route('app.appointments.index', ['mode' => $mode]) }}" class="btn btn-secondary" data-preserve-scroll-link>Today</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="ops-card ops-card--hero">
            <div class="ops-card__body">
                <div class="ops-kicker">{{ $isCheckInMode ? 'Status' : 'Clinic desk' }}</div>
                <h2 class="ops-title">{{ $isCheckInMode ? 'Status' : 'Appointments' }}</h2>
                <div class="booking-hero-summary" style="margin-top:22px;">
                    <div class="metric-card">
                        <div class="metric-card__label">Date</div>
                        <div class="metric-card__value" style="font-size:22px;">{{ \Carbon\Carbon::parse($selectedDate)->format('d M') }}</div>
                        <div class="metric-card__meta">{{ \Carbon\Carbon::parse($selectedDate)->format('l') }}</div>
                    </div>
                    <div class="hero-date-note">
                        <span class="hero-date-note__label">Front desk booking</span>
                    </div>
                </div>
            </div>
        </section>

        @if (false)
        <section class="ops-grid" id="appointment-list-panel">
            <div class="ops-card">
                <div class="ops-card__header">
                    <div class="filter-bar__head">
                        <div>
                            <div class="ops-kicker">{{ $isCheckInMode ? 'Status update' : 'Daily appointments' }}</div>
                            <h3 class="panel-title-display" style="font-size:24px;">Appointments for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h3>
                        </div>
                        <div class="page-actions">
                            @if ($selectedStatus)
                                <a href="{{ route('app.appointments.index', array_filter(['mode' => $mode, 'date' => $selectedDate])) }}" class="btn btn-secondary" data-preserve-scroll-link>Reset list</a>
                            @endif
                            <button type="button" class="btn btn-secondary" onclick="window.print()">Print list</button>
                        </div>
                    </div>
                </div>

                <div class="ops-card__body">
                    <div class="schedule-list">
                        @forelse ($appointmentGroups as $group)
                            @php
                                $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');
                                $statusStyle = $statusPalette[$statusValue] ?? ['bg' => '#f8fafc', 'border' => '#cbd5e1', 'text' => '#475569', 'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $statusValue))];
                                $statusLabel = in_array($statusValue, ['cancelled', 'no_show'], true)
                                    ? 'Reschedule'
                                    : ($group->status instanceof \App\Enums\AppointmentStatus ? $group->status->label() : $statusStyle['label']);
                                $membershipLabel = $group->customer?->membership_type ?: 'No membership';
                                $membershipCode = $group->customer?->membership_code ?: null;
                                $membershipBalance = $formatMembershipBalance($group->customer?->current_package);
                            @endphp
                            <article class="schedule-card">
                                <div class="schedule-card__head">
                                    <div>
                                        <div class="schedule-card__time">{{ optional($group->starts_at)->format('h:i A') ?? '-' }} @if(optional($group->ends_at)) - {{ optional($group->ends_at)->format('h:i A') }} @endif</div>
                                        <div class="schedule-card__name">{{ $group->customer?->full_name ?? 'Customer' }}</div>
                                        <div class="schedule-card__phone">{{ $group->customer?->phone ?? 'No phone recorded' }}</div>
                                        <div class="schedule-card__meta-stack">
                                            <div class="schedule-card__membership">{{ $membershipLabel }}@if ($membershipCode) • {{ $membershipCode }} @endif</div>
                                            <div class="schedule-card__balance">Membership balance: {{ $membershipBalance }}</div>
                                        </div>
                                    </div>
                                    <span class="status-chip" style="background: {{ $statusStyle['bg'] }}; border-color: {{ $statusStyle['border'] }}; color: {{ $statusStyle['text'] }};">
                                        <span class="status-dot"></span>
                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                <div style="display:grid;gap:10px;margin-top:16px;">
                                    @foreach ($group->items as $item)
                                        <div class="service-line">
                                            <div>
                                                <div class="service-line__name">{{ $item->service?->name ?? 'Service' }}</div>
                                                @if ($item->starts_at && $item->ends_at)
                                                    <div class="service-card__meta">{{ $item->starts_at->format('d M Y h:i A') }} - {{ $item->ends_at->format('h:i A') }}</div>
                                                @endif
                                            </div>
                                            <div class="service-line__staff">{{ $item->staff?->full_name ?? 'Unassigned' }}@if($item->staff?->role_key) ({{ $item->staff->role_key }}) @endif</div>
                                        </div>
                                    @endforeach
                                </div>

                                @if ($isCheckInMode)
                                    <form method="POST" action="{{ route('app.appointments.status', $group) }}" style="margin-top:16px;display:grid;gap:8px;" data-preserve-scroll-form>
                                        @csrf
                                        @method('PATCH')
                                        <label for="status-{{ $group->id }}" class="field-label" style="color: var(--app-accent-strong);">Status update</label>
                                        <select id="status-{{ $group->id }}" name="status" onchange="this.form.submit()" class="field-input select-input">
                                            @foreach ($statusOptions as $option)
                                                @php
                                                    $optionValue = $option instanceof \BackedEnum ? $option->value : (is_string($option) ? $option : '');
                                                    $optionLabel = in_array($optionValue, ['cancelled', 'no_show'], true)
                                                        ? 'Reschedule'
                                                        : ($option instanceof \App\Enums\AppointmentStatus ? $option->label() : \Illuminate\Support\Str::headline(str_replace('_', ' ', $optionValue)));
                                                @endphp
                                                <option value="{{ $optionValue }}" @selected($statusValue === $optionValue)>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            </article>
                        @empty
                            <div class="empty-card">
                                <div class="empty-card__title">No appointment groups found</div>
                            </div>
                        @endforelse
                    </div>

                    @if (method_exists($appointmentGroups, 'links'))
                        <div class="pagination-wrap">{{ $appointmentGroups->links() }}</div>
                    @endif
                </div>
            </div>
        </section>
        @endif

        @if (! $isCheckInMode)
            <section class="booking-grid" id="booking-builder-panel">
                <div class="ops-card">
                    <div class="ops-card__header">
                        <div class="ops-kicker">Booking builder</div>
                        <h3 class="panel-title-display" style="font-size:24px;">Create a booking</h3>
                    </div>

                    <div class="ops-card__body">
                        <form method="GET" action="{{ route('app.appointments.index') }}" class="form-stack" data-preserve-scroll-form>
                            <input type="hidden" name="mode" value="{{ $mode }}">
                            <input type="hidden" name="date" value="{{ $selectedDate }}">
                            <input type="hidden" name="slot" value="{{ $prefilledSlot }}">
                            <input type="hidden" name="open_availability" value="1">
                            <input type="hidden" name="customer_id" id="builder_customer_id" value="{{ $selectedCustomerId }}">
                            <div id="service-order-inputs">
                                @foreach ($selectedServiceOrderIds as $serviceOrderId)
                                    <input type="hidden" name="service_order[]" value="{{ $serviceOrderId }}">
                                @endforeach
                            </div>

                            <div class="booking-form-grid booking-form-grid--customer">
                                <div class="field-block customer-picker">
                                    <label for="builder_customer_full_name" class="field-label">Customer name</label>
                                    <input
                                        id="builder_customer_full_name"
                                        type="text"
                                        name="customer_full_name"
                                        value="{{ $selectedCustomerName }}"
                                        class="field-input"
                                        autocomplete="off"
                                        placeholder="Start typing member name or phone"
                                        required
                                    >
                                    <div id="customer_suggestions" class="customer-suggestions hidden" role="listbox" aria-label="Customer suggestions"></div>
                                    <div id="customer_selected_hint" class="customer-picked {{ $selectedCustomerId ? '' : 'hidden' }}"></div>
                                </div>
                                <div class="field-block">
                                    <label for="builder_customer_phone" class="field-label">Customer phone</label>
                                    <input
                                        id="builder_customer_phone"
                                        type="text"
                                        name="customer_phone"
                                        value="{{ $selectedCustomerPhone }}"
                                        class="field-input"
                                        placeholder="Customer contact number"
                                        required
                                    >
                                </div>
                            </div>

                            <div>
                                <div class="ops-kicker">Categories</div>
                                <div class="btn-row" style="margin-top:14px;flex-wrap:wrap;">
                                    @foreach ($serviceCategories as $category)
                                        <button
                                            type="button"
                                            class="btn {{ $defaultCategoryKey === $category['key'] ? 'btn-primary' : 'btn-secondary' }} service-category-tab"
                                            data-category-tab="{{ $category['key'] }}"
                                        >
                                            {{ $category['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <div class="ops-kicker">Services</div>
                                <h4 class="panel-title-display" style="font-size:18px;">Choose services</h4>
                                <div class="service-grid" style="margin-top:14px;">
                                    @forelse ($services as $service)
                                        @php $isSelected = in_array((string) $service->id, $selectedServiceIds, true); @endphp
                                        <label
                                            class="service-card {{ $isSelected ? 'is-selected' : '' }} {{ $service->category_key !== $defaultCategoryKey ? 'hidden' : '' }}"
                                            data-service-category="{{ $service->category_key }}"
                                        >
                                            <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="selection-input service-checkbox" {{ $isSelected ? 'checked' : '' }}>
                                            <div style="display:flex;align-items:start;justify-content:space-between;gap:14px;">
                                                <div>
                                                    <div class="service-card__title">{{ $service->name }}</div>
                                                    @if ($service->is_promo && $service->promo_price !== null)
                                                        <div class="service-card__meta">Promo RM {{ number_format($service->promo_price, 0) }}</div>
                                                    @endif
                                                </div>
                                                <span class="service-card__badge">{{ (int) ($service->duration_minutes ?? 60) }} mins</span>
                                            </div>
                                        </label>
                                    @empty
                                        <div class="flash flash--warn" style="grid-column:1 / -1;">No active services found. Add services in the new Services page first.</div>
                                    @endforelse
                                </div>
                            </div>

                            <div>
                                <div class="ops-kicker">Visit setup</div>
                                <h4 class="panel-title-display" style="font-size:18px;">Choose service arrangement</h4>
                                <div class="arrangement-grid" style="margin-top:14px;">
                                    <label class="arrangement-card {{ $selectedArrangementMode === 'same_slot' ? 'is-selected' : '' }}">
                                        <input type="radio" name="arrangement_mode" value="same_slot" class="selection-input arrangement-radio" {{ $selectedArrangementMode === 'same_slot' ? 'checked' : '' }}>
                                        <div class="arrangement-card__title">Same slot</div>
                                    </label>
                                    <label class="arrangement-card {{ $selectedArrangementMode === 'back_to_back' ? 'is-selected' : '' }}">
                                        <input type="radio" name="arrangement_mode" value="back_to_back" class="selection-input arrangement-radio" {{ $selectedArrangementMode === 'back_to_back' ? 'checked' : '' }}>
                                        <div class="arrangement-card__title">Back-to-back</div>
                                    </label>
                                    <label class="arrangement-card {{ $selectedArrangementMode === 'custom' ? 'is-selected' : '' }}">
                                        <input type="radio" name="arrangement_mode" value="custom" class="selection-input arrangement-radio" {{ $selectedArrangementMode === 'custom' ? 'checked' : '' }}>
                                        <div class="arrangement-card__title">Custom</div>
                                    </label>
                                </div>
                            </div>

                            <div id="workflow-panel" class="workflow-panel {{ $selectedArrangementMode === 'back_to_back' ? '' : 'hidden' }}">
                                <div class="ops-kicker">Service workflow</div>
                                <div class="selection-card__title" style="margin-top:6px;">Set the service order used for back-to-back visits</div>
                                <div id="workflow-list" class="workflow-list"></div>
                            </div>

                            <div id="custom-schedule-panel" class="workflow-panel {{ $selectedArrangementMode === 'custom' ? '' : 'hidden' }}">
                                <div class="ops-kicker">Custom scheduling</div>
                                <div class="selection-card__title" style="margin-top:6px;">Choose date and time for each selected service</div>
                                <div id="custom-schedule-grid" class="form-grid" style="margin-top:14px;">
                                    @foreach ($selectedServiceOrderIds as $serviceOrderId)
                                        @php $service = $services->firstWhere('id', $serviceOrderId); @endphp
                                        @if ($service)
                                            <div class="col-12 service-custom-row" data-custom-service-id="{{ $serviceOrderId }}">
                                                <div class="panel" style="margin:0;">
                                                    <div class="panel-body">
                                                        <div class="filter-bar__head" style="margin-bottom:1rem;">
                                                            <div class="selection-card__title">{{ $service->name }}</div>
                                                            <div class="small-note">{{ (int) ($service->duration_minutes ?? 60) }} mins</div>
                                                        </div>
                                                        <div class="form-grid">
                                                            <div class="col-6 field-block">
                                                                <label class="field-label" for="custom-date-{{ $serviceOrderId }}">Date</label>
                                                                <input id="custom-date-{{ $serviceOrderId }}" name="custom_schedule[{{ $serviceOrderId }}][date]" type="date" class="form-input" value="{{ $customSchedule[$serviceOrderId]['date'] ?? $selectedDate }}">
                                                            </div>
                                                            <div class="col-6 field-block">
                                                                <label class="field-label" for="custom-time-{{ $serviceOrderId }}">Start time</label>
                                                                <input id="custom-time-{{ $serviceOrderId }}" name="custom_schedule[{{ $serviceOrderId }}][start_time]" type="time" class="form-input" value="{{ $customSchedule[$serviceOrderId]['start_time'] ?? '' }}">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>

                            <div class="btn-row">
                                <button type="submit" class="action-btn action-btn--primary" style="min-width:190px;">Check Availability</button>
                                <a href="{{ route('app.appointments.index', ['date' => $selectedDate]) }}" class="action-btn action-btn--secondary" data-preserve-scroll-link>Clear</a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        @endif

        @if (false)
        <section class="ops-grid">
            <div class="ops-card">
                <div class="ops-card__header">
                    <div class="filter-bar__head">
                        <div>
                            <div class="ops-kicker">{{ $isCheckInMode ? 'Status update' : 'Schedule' }}</div>
                            <h3 class="panel-title-display" style="font-size:24px;">{{ ($isCheckInMode ? 'Status update' : 'Schedule').' for '.\Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h3>
                        </div>
                        <div class="page-actions">
                            @if ($selectedStatus)
                                <a href="{{ route('app.appointments.index', array_filter(['mode' => $mode, 'date' => $selectedDate])) }}" class="btn btn-secondary">Reset list</a>
                            @endif
                            <button type="button" class="btn btn-secondary" onclick="window.print()">Print list</button>
                        </div>
                    </div>
                </div>

                <div class="ops-card__body">

                    <div class="schedule-list">
                        @forelse ($appointmentGroups as $group)
                              @php
                                   $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');
                                   $statusStyle = $statusPalette[$statusValue] ?? ['bg' => '#f8fafc', 'border' => '#cbd5e1', 'text' => '#475569', 'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $statusValue))];
                                   $statusLabel = in_array($statusValue, ['cancelled', 'no_show'], true)
                                       ? 'Reschedule'
                                       : ($group->status instanceof \App\Enums\AppointmentStatus ? $group->status->label() : $statusStyle['label']);
                                   $membershipLabel = $group->customer?->membership_type ?: 'No membership';
                                   $membershipCode = $group->customer?->membership_code ?: null;
                                   $membershipBalance = $formatMembershipBalance($group->customer?->current_package);
                               @endphp
                            <article class="schedule-card">
                                <div class="schedule-card__head">
                                      <div>
                                          <div class="schedule-card__time">{{ optional($group->starts_at)->format('h:i A') ?? '-' }} @if(optional($group->ends_at)) - {{ optional($group->ends_at)->format('h:i A') }} @endif</div>
                                          <div class="schedule-card__name">{{ $group->customer?->full_name ?? 'Customer' }}</div>
                                          <div class="schedule-card__phone">{{ $group->customer?->phone ?? 'No phone recorded' }}</div>
                                          <div class="schedule-card__meta-stack">
                                              <div class="schedule-card__membership">{{ $membershipLabel }}@if ($membershipCode) • {{ $membershipCode }} @endif</div>
                                              <div class="schedule-card__balance">Membership balance: {{ $membershipBalance }}</div>
                                          </div>
                                      </div>
                                    <span class="status-chip" style="background: {{ $statusStyle['bg'] }}; border-color: {{ $statusStyle['border'] }}; color: {{ $statusStyle['text'] }};">
                                        <span class="status-dot"></span>
                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                <div style="display:grid;gap:10px;margin-top:16px;">
                                    @foreach ($group->items as $item)
                                        <div class="service-line">
                                            <div>
                                                <div class="service-line__name">{{ $item->service?->name ?? 'Service' }}</div>
                                                @if ($item->starts_at && $item->ends_at)
                                                    <div class="service-card__meta">{{ $item->starts_at->format('h:i A') }} - {{ $item->ends_at->format('h:i A') }}</div>
                                                @endif
                                            </div>
                                            <div class="service-line__staff">{{ $item->staff?->full_name ?? 'Unassigned' }}@if($item->staff?->role_key) ({{ $item->staff->role_key }}) @endif</div>
                                        </div>
                                    @endforeach
                                </div>

                                <form method="POST" action="{{ route('app.appointments.status', $group) }}" style="margin-top:16px;display:grid;gap:8px;">
                                    @csrf
                                    @method('PATCH')
                                    <label for="status-{{ $group->id }}" class="field-label" style="color: var(--app-accent-strong);">Status update</label>
                                    <select id="status-{{ $group->id }}" name="status" onchange="this.form.submit()" class="field-input select-input">
                                        @foreach ($statusOptions as $option)
                                            @php
                                                $optionValue = $option instanceof \BackedEnum ? $option->value : (is_string($option) ? $option : '');
                                                $optionLabel = in_array($optionValue, ['cancelled', 'no_show'], true)
                                                    ? 'Reschedule'
                                                    : ($option instanceof \App\Enums\AppointmentStatus ? $option->label() : \Illuminate\Support\Str::headline(str_replace('_', ' ', $optionValue)));
                                            @endphp
                                            <option value="{{ $optionValue }}" @selected($statusValue === $optionValue)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </article>
                        @empty
                            <div class="empty-card">
                                <div class="empty-card__title">No appointment groups found</div>
                                <div class="empty-card__body">Once bookings are created for this day, they will appear here for front desk follow-up.</div>
                            </div>
                        @endforelse
                    </div>

                    @if (method_exists($appointmentGroups, 'links'))
                        <div class="pagination-wrap">{{ $appointmentGroups->links() }}</div>
                    @endif
                </div>
            </div>

        </section>
        @endif
    </div>

    @if (! $isCheckInMode && ! empty($availability))
        <div id="availability-modal" class="modal-shell hidden" aria-hidden="true">
            <div class="modal-backdrop" data-close-availability-modal></div>
            <div class="modal-stage">
                <div class="modal-card availability-modal-card">
                    <div class="modal-header">
                        <div class="modal-header__row">
                            <div>
                                <div class="modal-kicker">Availability</div>
                                <h3 class="modal-title">Choose slot and staff</h3>
                            </div>
                            <div class="btn-row">
                                <button type="button" class="btn btn-secondary" data-close-availability-modal>Close</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-body availability-modal-body">
                        <div class="availability-grid">
                            @if (! empty($availability['services_without_eligible_staff']))
                                <div class="flash flash--error">No active eligible staff assigned for: <strong>{{ implode(', ', $availability['services_without_eligible_staff']) }}</strong></div>
                            @endif

                            @if (! empty($availability['fully_booked_message']))
                                <div class="flash flash--warn">{{ $availability['fully_booked_message'] }}</div>
                            @endif

                            @if (! empty($availability['custom_missing_message']))
                                <div class="flash flash--warn">{{ $availability['custom_missing_message'] }}</div>
                            @endif

                            <div class="availability-modal-layout">
                                <div class="availability-modal-main">
                                    <div class="availability-section">
                                        <div
                                            id="service-progress-summary"
                                            class="service-progress-summary"
                                            style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;"
                                        >
                                            @foreach ($serviceSelectionSections as $section)
                                                <div
                                                    data-service-summary="{{ $section['id'] }}"
                                                    data-service-name="{{ $section['name'] }}"
                                                    tabindex="0"
                                                    role="button"
                                                    style="border:1px solid rgba(214,180,192,.55);border-radius:18px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(255,248,246,.96));padding:14px 16px;min-height:88px;display:flex;flex-direction:column;justify-content:space-between;"
                                                >
                                                    <div style="font-size:.78rem;font-weight:800;line-height:1.2;color:#4f3340;">{{ $section['name'] }}</div>
                                                    <div data-service-summary-state style="margin-top:10px;font-size:.75rem;font-weight:700;color:#9a6d79;">Choose staff</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="availability-section">
                                        @if ($serviceSelectionSections->isEmpty())
                                            <div class="empty-card">
                                                <div class="empty-card__title">No staff options available</div>
                                            </div>
                                        @else
                                            <div class="service-selection-stack" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;">
                                                @foreach ($serviceSelectionSections as $section)
                                                    <section
                                                        class="service-selection-card"
                                                        data-service-card
                                                        data-service-id="{{ $section['id'] }}"
                                                        data-service-name="{{ $section['name'] }}"
                                                        style="border:1px solid rgba(214,180,192,.58);border-radius:22px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(255,248,246,.95));padding:18px;box-shadow:0 12px 28px rgba(88,54,70,.06);display:grid;gap:14px;align-content:start;"
                                                    >
                                                        <div class="service-selection-card__head" style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                                            <div>
                                                                <div class="service-selection-card__title" style="font-size:1.02rem;font-weight:800;color:#4f3340;line-height:1.2;">{{ $section['name'] }}</div>
                                                                @if (! empty($section['scheduled_date']) && ! empty($section['scheduled_time']))
                                                                    <div class="service-selection-card__meta" style="margin-top:4px;font-size:.74rem;font-weight:600;color:#8f7680;">{{ \Carbon\Carbon::parse($section['scheduled_date'])->format('d M') }} {{ $section['scheduled_time'] }}</div>
                                                                @endif
                                                            </div>
                                                            <div
                                                                class="service-selection-card__status"
                                                                data-service-status="{{ $section['id'] }}"
                                                                style="flex-shrink:0;border:1px solid rgba(214,180,192,.65);border-radius:999px;background:rgba(255,255,255,.9);padding:7px 12px;font-size:.72rem;font-weight:800;color:#8f6a75;line-height:1.1;"
                                                            >
                                                                Choose staff
                                                            </div>
                                                        </div>

                                                        <div class="service-selection-groups" style="display:grid;gap:12px;">
                                                            @foreach ($section['staff_groups'] as $group)
                                                                <div class="availability-group" style="display:grid;gap:8px;">
                                                                    <div class="availability-group__label" style="font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#9a6d79;">{{ $group['label'] }}</div>
                                                                    <div class="staff-pill-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
                                                                        @foreach ($group['options'] as $option)
                                                                            <button
                                                                                type="button"
                                                                                class="staff-pill-button"
                                                                                data-staff-option
                                                                                data-service-id="{{ $section['id'] }}"
                                                                                data-service-name="{{ $section['name'] }}"
                                                                                data-staff-id="{{ $option['id'] }}"
                                                                                data-staff-name="{{ $option['full_name'] }}"
                                                                                data-role-label="{{ $option['role_label'] }}"
                                                                                data-matching-payloads='@json($option['matching_payloads'])'
                                                                                style="appearance:none;border:none;background:none;padding:0;width:100%;text-align:left;cursor:pointer;"
                                                                            >
                                                                                <div
                                                                                    class="staff-pill"
                                                                                    style="position:relative;display:grid;gap:4px;min-height:86px;border:1px solid rgba(214,180,192,.55);border-radius:18px;background:rgba(255,255,255,.98);padding:14px 16px;align-content:center;transition:180ms ease;"
                                                                                >
                                                                                    <span class="staff-pill__name" style="font-size:.88rem;font-weight:800;color:#4f3340;line-height:1.2;">{{ $option['full_name'] }}</span>
                                                                                    <span class="staff-pill__meta" style="font-size:.72rem;font-weight:600;color:#8f7680;line-height:1.2;">{{ $option['role_label'] }}</span>
                                                                                </div>
                                                                            </button>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </section>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                </div>

                                <div class="availability-modal-side" style="display:grid;gap:14px;align-content:start;position:sticky;top:0;">
                                    @if ($selectedArrangementMode !== 'custom' && $slotOptions !== [])
                                        <div id="time-selection-section" class="availability-section hidden" style="border:1px solid rgba(214,180,192,.48);border-radius:22px;background:rgba(255,255,255,.92);padding:16px 18px;">
                                            <div class="availability-section__head">
                                                <div class="ops-kicker">Time</div>
                                                @if ($selectedSlotRow)
                                                    <div class="summary-pill summary-pill--compact">
                                                        <span class="summary-pill__label">Duration</span>
                                                        <span class="summary-pill__value">{{ $selectedSlotRow['duration_minutes'] ?? ($availability['duration_minutes'] ?? 0) }} mins</span>
                                                    </div>
                                                @endif
                                            </div>

                                            <div id="slot-selection-hint" class="small-note">Choose staff for each service first.</div>
                                            <div class="slot-grid slot-grid--availability" style="margin-top:12px;grid-template-columns:repeat(2,minmax(0,1fr));">
                                                @foreach ($slotOptions as $slot)
                                                    @php
                                                        $slotData = $slotIndex->get($slot['time']);
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        class="slot-button slot-select-button"
                                                        data-slot-time="{{ $slot['time'] }}"
                                                        data-slot-combinations='@json($slotData['combinations'] ?? [])'
                                                        style="appearance:none;border:none;background:none;padding:0;"
                                                    >
                                                        <div class="slot-card {{ ($slotData['is_prefilled'] ?? false) ? 'is-selected' : '' }} {{ ($slot['is_available'] ?? false) ? '' : 'is-unavailable' }}" style="min-height:62px;border:1px solid rgba(214,180,192,.48);border-radius:16px;background:rgba(255,255,255,.98);display:flex;align-items:center;justify-content:center;padding:10px;">
                                                            <div class="slot-card__time">{{ $slot['time'] }}</div>
                                                        </div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                <div id="selected-slot-card" class="booking-panel booking-panel--modal" style="border:1px solid rgba(214,180,192,.48);border-radius:22px;background:rgba(255,255,255,.95);padding:18px;">
                                    <div class="ops-kicker">Booking</div>
                                    <div class="booking-panel__title">Front desk notes</div>

                                    <form method="POST" action="{{ route('app.appointments.store') }}" class="booking-form" data-preserve-scroll-form>
                                        @csrf
                                        <input type="hidden" name="customer_id" id="customer_id" value="{{ $selectedCustomerId }}">
                                        <input type="hidden" name="customer_full_name" id="selected_customer_full_name" value="{{ $selectedCustomerName }}">
                                        <input type="hidden" name="customer_phone" id="selected_customer_phone" value="{{ $selectedCustomerPhone }}">
                                        <input type="hidden" name="date" value="{{ $selectedDate }}">
                                        <input type="hidden" name="slot" id="selected-slot-input" value="">
                                        <input type="hidden" name="arrangement_mode" value="{{ $selectedArrangementMode }}">
                                        <input type="hidden" name="selected_combination" id="selected-combination-input" value="">
                                        @foreach ($selectedServiceIds as $serviceId)
                                            <input type="hidden" name="service_ids[]" value="{{ $serviceId }}">
                                        @endforeach
                                        <div id="booking-service-order-inputs">
                                            @foreach ($selectedServiceOrderIds as $serviceOrderId)
                                                <input type="hidden" name="service_order[]" value="{{ $serviceOrderId }}">
                                            @endforeach
                                        </div>
                                        @foreach ($selectedServiceOrderIds as $serviceOrderId)
                                            <input type="hidden" name="custom_schedule[{{ $serviceOrderId }}][date]" value="{{ $customSchedule[$serviceOrderId]['date'] ?? $selectedDate }}">
                                            <input type="hidden" name="custom_schedule[{{ $serviceOrderId }}][start_time]" value="{{ $customSchedule[$serviceOrderId]['start_time'] ?? '' }}">
                                        @endforeach

                                        <div class="summary-pill summary-pill--stack summary-pill--compact">
                                            <span class="summary-pill__label">Customer</span>
                                            <span id="selected-customer-summary" class="summary-pill__value">{{ trim($selectedCustomerName.' '.$selectedCustomerPhone) !== '' ? trim($selectedCustomerName.' | '.$selectedCustomerPhone, ' |') : 'Customer details required' }}</span>
                                        </div>

                                        <input type="hidden" id="selected_combination_select" value="">

                                        <div class="summary-pill summary-pill--stack summary-pill--compact">
                                            <span class="summary-pill__label">Selection</span>
                                            <span id="selected-booking-summary" class="summary-pill__value">Choose staff for each service.</span>
                                        </div>

                                        <div class="field-block">
                                            <label for="notes">Front desk notes</label>
                                            <textarea id="notes" name="notes" class="field-input booking-textarea">{{ old('notes') }}</textarea>
                                        </div>

                                        <div class="booking-panel__footer">
                                            <button type="submit" id="create-appointment-button" class="action-btn action-btn--primary" disabled>Create Appointment</button>
                                        </div>
                                    </form>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const serviceCheckboxes = Array.from(document.querySelectorAll('.service-checkbox'));
            const arrangementRadios = Array.from(document.querySelectorAll('.arrangement-radio'));
            const staffOptionButtons = Array.from(document.querySelectorAll('[data-staff-option]'));
            const slotButtons = Array.from(document.querySelectorAll('.slot-select-button'));
            const slotCard = document.getElementById('selected-slot-card');
            const slotHint = document.getElementById('slot-selection-hint');
            const slotInput = document.getElementById('selected-slot-input');
            const comboInput = document.getElementById('selected-combination-input');
            const comboSelect = document.getElementById('selected_combination_select');
            const bookingSummary = document.getElementById('selected-booking-summary');
            const createAppointmentButton = document.getElementById('create-appointment-button');
            const modalServiceCards = Array.from(document.querySelectorAll('[data-service-card]'));
            const serviceProgressSummary = document.getElementById('service-progress-summary');
            const serviceSummaryCards = Array.from(document.querySelectorAll('[data-service-summary]'));
            const timeSelectionSection = document.getElementById('time-selection-section');
            const workflowList = document.getElementById('workflow-list');
            const workflowPanel = document.getElementById('workflow-panel');
            const customSchedulePanel = document.getElementById('custom-schedule-panel');
            const customScheduleGrid = document.getElementById('custom-schedule-grid');
            const serviceOrderInputs = document.getElementById('service-order-inputs');
            const bookingServiceOrderInputs = document.getElementById('booking-service-order-inputs');
            const serviceCategoryTabs = Array.from(document.querySelectorAll('.service-category-tab'));
            const serviceCards = Array.from(document.querySelectorAll('[data-service-category]'));
            const prefilledSlot = @json($prefilledSlot);
            const slotAvailable = @json($quickCreate['slot_is_available']);
            const selectedArrangementMode = @json($selectedArrangementMode);
            const defaultDate = @json($selectedDate);
            const defaultCategoryKey = @json($defaultCategoryKey);
            const customScheduleSeed = @json($customSchedule);
            const combinationCatalog = @json($combinationSource->values()->all());
            const customerIdInput = document.getElementById('builder_customer_id');
            const customerNameInput = document.getElementById('builder_customer_full_name');
            const customerPhoneInput = document.getElementById('builder_customer_phone');
            const customerSuggestions = document.getElementById('customer_suggestions');
            const customerSelectedHint = document.getElementById('customer_selected_hint');
            const customerSearchUrl = @json(route('app.appointments.customer-search'));
            const selectedServicesSeed = @json($selectedServiceOrderIds);
            const serviceCatalog = @json($serviceCatalog);
            const scrollStorageKey = 'lindo-appointments-scroll';
            const shouldAutoOpenAvailability = @json($shouldAutoOpenAvailability);
            const modalCustomerIdInput = document.getElementById('customer_id');
            const modalCustomerNameInput = document.getElementById('selected_customer_full_name');
            const modalCustomerPhoneInput = document.getElementById('selected_customer_phone');
            const customerSummary = document.getElementById('selected-customer-summary');
            const availabilityModal = document.getElementById('availability-modal');
            const availabilityCloseButtons = Array.from(document.querySelectorAll('[data-close-availability-modal]'));
            let activeCustomerRequest = null;
            let selectedServiceOrder = Array.isArray(selectedServicesSeed) ? [...selectedServicesSeed] : [];
            let activeCategoryKey = defaultCategoryKey;
            let selectedStaffByService = {};
            let activeServiceId = serviceSummaryCards[0]?.dataset.serviceSummary || modalServiceCards[0]?.dataset.serviceId || null;

            const restoreScroll = sessionStorage.getItem(scrollStorageKey);

            if (restoreScroll) {
                window.requestAnimationFrame(() => {
                    window.scrollTo({ top: Number(restoreScroll), behavior: 'auto' });
                    sessionStorage.removeItem(scrollStorageKey);
                });
            }

            document.querySelectorAll('[data-preserve-scroll-form]').forEach((form) => {
                form.addEventListener('submit', () => {
                    sessionStorage.setItem(scrollStorageKey, String(window.scrollY));
                });
            });

            document.querySelectorAll('[data-preserve-scroll-link]').forEach((link) => {
                link.addEventListener('click', () => {
                    sessionStorage.setItem(scrollStorageKey, String(window.scrollY));
                });
            });

            document.querySelectorAll('.pagination-wrap a').forEach((link) => {
                link.addEventListener('click', () => {
                    sessionStorage.setItem(scrollStorageKey, String(window.scrollY));
                });
            });

            function clearAvailabilityAutoOpenFlag() {
                const url = new URL(window.location.href);

                if (!url.searchParams.has('open_availability')) {
                    return;
                }

                url.searchParams.delete('open_availability');
                window.history.replaceState({}, '', url.toString());
            }

            function openAvailabilityModal() {
                if (!availabilityModal) {
                    return;
                }

                availabilityModal.classList.remove('hidden');
                availabilityModal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            function closeAvailabilityModal() {
                if (!availabilityModal) {
                    return;
                }

                availabilityModal.classList.add('hidden');
                availabilityModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                clearAvailabilityAutoOpenFlag();
            }

            availabilityCloseButtons.forEach((button) => {
                button.addEventListener('click', closeAvailabilityModal);
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeAvailabilityModal();
                }
            });

            function buildHiddenInputs(container, inputName, values) {
                if (!container) {
                    return;
                }

                container.innerHTML = '';

                values.forEach((value) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = inputName;
                    input.value = value;
                    container.appendChild(input);
                });
            }

            function applyCategoryTabState(categoryKey) {
                activeCategoryKey = categoryKey || defaultCategoryKey;

                serviceCategoryTabs.forEach((button) => {
                    const isActive = button.dataset.categoryTab === activeCategoryKey;
                    button.classList.toggle('btn-primary', isActive);
                    button.classList.toggle('btn-secondary', !isActive);
                });

                serviceCards.forEach((card) => {
                    card.classList.toggle('hidden', card.dataset.serviceCategory !== activeCategoryKey);
                });
            }

            function syncServiceOrderFromSelection() {
                const checkedIds = Array.from(serviceCheckboxes)
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => checkbox.value);

                selectedServiceOrder = selectedServiceOrder.filter((id) => checkedIds.includes(id));

                checkedIds.forEach((id) => {
                    if (!selectedServiceOrder.includes(id)) {
                        selectedServiceOrder.push(id);
                    }
                });
            }

            function renderWorkflowList() {
                if (!workflowList) {
                    return;
                }

                if (!selectedServiceOrder.length) {
                    workflowList.innerHTML = '<div class="helper-note__body">Select one or more services above to define the visit workflow.</div>';
                    return;
                }

                workflowList.innerHTML = '';

                selectedServiceOrder.forEach((serviceId, index) => {
                    const service = serviceCatalog[serviceId];

                    if (!service) {
                        return;
                    }

                    const row = document.createElement('div');
                    row.className = 'workflow-item';
                    row.innerHTML = `
                        <div>
                            <div class="workflow-item__name">${service.name}</div>
                            <div class="workflow-item__meta">Step ${index + 1} - ${service.duration} mins</div>
                        </div>
                        <div class="workflow-actions">
                            <button type="button" class="workflow-btn" data-direction="up" data-service-id="${serviceId}" ${index === 0 ? 'disabled' : ''}>Up</button>
                            <button type="button" class="workflow-btn" data-direction="down" data-service-id="${serviceId}" ${index === selectedServiceOrder.length - 1 ? 'disabled' : ''}>Down</button>
                        </div>
                    `;
                    workflowList.appendChild(row);
                });
            }

            function renderCustomScheduleRows() {
                if (!customScheduleGrid) {
                    return;
                }

                const currentValues = {};
                customScheduleGrid.querySelectorAll('[data-custom-service-id]').forEach((row) => {
                    const serviceId = row.dataset.customServiceId;
                    currentValues[serviceId] = {
                        date: row.querySelector('input[type="date"]')?.value || '',
                        start_time: row.querySelector('input[type="time"]')?.value || '',
                    };
                });

                if (!selectedServiceOrder.length) {
                    customScheduleGrid.innerHTML = '<div class="col-12 small-note">Select one or more services first.</div>';
                    return;
                }

                customScheduleGrid.innerHTML = '';

                selectedServiceOrder.forEach((serviceId) => {
                    const service = serviceCatalog[serviceId];

                    if (!service) {
                        return;
                    }

                    const schedule = currentValues[serviceId] || customScheduleSeed[serviceId] || {};
                    const row = document.createElement('div');
                    row.className = 'col-12 service-custom-row';
                    row.dataset.customServiceId = serviceId;
                    row.innerHTML = `
                        <div class="panel" style="margin:0;">
                            <div class="panel-body">
                                <div class="filter-bar__head" style="margin-bottom:1rem;">
                                    <div class="selection-card__title">${service.name}</div>
                                    <div class="small-note">${service.duration} mins</div>
                                </div>
                                <div class="form-grid">
                                    <div class="col-6 field-block">
                                        <label class="field-label" for="custom-date-${serviceId}">Date</label>
                                        <input id="custom-date-${serviceId}" name="custom_schedule[${serviceId}][date]" type="date" class="form-input" value="${schedule.date || defaultDate}">
                                    </div>
                                    <div class="col-6 field-block">
                                        <label class="field-label" for="custom-time-${serviceId}">Start time</label>
                                        <input id="custom-time-${serviceId}" name="custom_schedule[${serviceId}][start_time]" type="time" class="form-input" value="${schedule.start_time || ''}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    customScheduleGrid.appendChild(row);
                });
            }

            function syncServiceOrderUi() {
                buildHiddenInputs(serviceOrderInputs, 'service_order[]', selectedServiceOrder);
                buildHiddenInputs(bookingServiceOrderInputs, 'service_order[]', selectedServiceOrder);
                renderWorkflowList();
                renderCustomScheduleRows();
            }

            function updateArrangementCards() {
                arrangementRadios.forEach((radio) => {
                    radio.closest('.arrangement-card')?.classList.toggle('is-selected', radio.checked);
                });

                const mode = arrangementRadios.find((radio) => radio.checked)?.value || selectedArrangementMode;
                workflowPanel?.classList.toggle('hidden', mode !== 'back_to_back');
                customSchedulePanel?.classList.toggle('hidden', mode !== 'custom');
            }

            serviceCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    this.closest('.service-card')?.classList.toggle('is-selected', this.checked);
                    syncServiceOrderFromSelection();
                    syncServiceOrderUi();
                });
            });

            serviceCategoryTabs.forEach((button) => {
                button.addEventListener('click', function () {
                    applyCategoryTabState(this.dataset.categoryTab || defaultCategoryKey);
                });
            });

            arrangementRadios.forEach((radio) => {
                radio.addEventListener('change', updateArrangementCards);
            });

            workflowList?.addEventListener('click', function (event) {
                const button = event.target.closest('.workflow-btn');

                if (!button || button.disabled) {
                    return;
                }

                const serviceId = button.dataset.serviceId;
                const direction = button.dataset.direction;
                const currentIndex = selectedServiceOrder.indexOf(serviceId);

                if (currentIndex === -1) {
                    return;
                }

                const swapIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

                if (swapIndex < 0 || swapIndex >= selectedServiceOrder.length) {
                    return;
                }

                const nextOrder = [...selectedServiceOrder];
                [nextOrder[currentIndex], nextOrder[swapIndex]] = [nextOrder[swapIndex], nextOrder[currentIndex]];
                selectedServiceOrder = nextOrder;
                syncServiceOrderUi();
            });

            function parseJson(value, fallback) {
                try {
                    return JSON.parse(value || JSON.stringify(fallback));
                } catch (error) {
                    return fallback;
                }
            }

            function normalizeServiceStaffMap(map) {
                const normalized = {};

                if (!map || typeof map !== 'object') {
                    return normalized;
                }

                Object.keys(map).sort().forEach((serviceId) => {
                    normalized[String(serviceId)] = String(map[serviceId]);
                });

                return normalized;
            }

            function getRequiredServiceIds() {
                return modalServiceCards
                    .map((card) => String(card.dataset.serviceId || ''))
                    .filter(Boolean);
            }

            function getServiceName(serviceId) {
                return modalServiceCards.find((card) => String(card.dataset.serviceId || '') === String(serviceId))?.dataset.serviceName || 'Service';
            }

            function getSelectedStaffButton(serviceId) {
                return staffOptionButtons.find((button) => {
                    return String(button.dataset.serviceId || '') === String(serviceId)
                        && String(button.dataset.staffId || '') === String(selectedStaffByService[serviceId] || '');
                }) || null;
            }

            function getChosenServiceIds() {
                return getRequiredServiceIds().filter((serviceId) => Boolean(selectedStaffByService[serviceId]));
            }

            function areAllServicesSelected() {
                const requiredServiceIds = getRequiredServiceIds();

                return requiredServiceIds.length > 0
                    && requiredServiceIds.every((serviceId) => Boolean(selectedStaffByService[serviceId]));
            }

            function mapsMatchSelection(serviceStaffMap) {
                const normalizedMap = normalizeServiceStaffMap(serviceStaffMap);
                const requiredServiceIds = getRequiredServiceIds();

                if (Object.keys(normalizedMap).length !== requiredServiceIds.length) {
                    return false;
                }

                return requiredServiceIds.every((serviceId) => {
                    return String(normalizedMap[serviceId] || '') === String(selectedStaffByService[serviceId] || '');
                });
            }

            function getMatchingCombinationPayload(combinations) {
                return combinations.find((combo) => {
                    return mapsMatchSelection(parseJson(combo.payload, {}).service_staff_map || {});
                }) || null;
            }

            function getMatchingCustomCombination() {
                return combinationCatalog.find((combo) => mapsMatchSelection(combo.map || {})) || null;
            }

            function paintStaffPill(button, isSelected) {
                const pill = button?.querySelector('.staff-pill');
                const name = button?.querySelector('.staff-pill__name');
                const meta = button?.querySelector('.staff-pill__meta');

                if (!pill) {
                    return;
                }

                pill.style.borderColor = isSelected ? 'rgba(201,146,115,.75)' : 'rgba(214,180,192,.55)';
                pill.style.background = isSelected
                    ? 'linear-gradient(180deg,rgba(255,243,238,.98),rgba(255,248,246,.98))'
                    : 'rgba(255,255,255,.98)';
                pill.style.boxShadow = isSelected ? '0 14px 28px rgba(111,78,92,.12)' : 'none';
                pill.style.transform = isSelected ? 'translateY(-1px)' : 'none';

                if (name) {
                    name.style.textDecoration = isSelected ? 'line-through' : 'none';
                    name.style.textDecorationThickness = isSelected ? '1.5px' : '';
                }

                if (meta) {
                    meta.textContent = isSelected
                        ? `${button.dataset.roleLabel || 'Staff'} • Selected`
                        : (button.dataset.roleLabel || 'Staff');
                }
            }

            function paintServiceSummaryCard(card, isComplete, detail) {
                if (!card) {
                    return;
                }

                const state = card.querySelector('[data-service-summary-state]');

                card.style.borderColor = isComplete ? 'rgba(201,146,115,.62)' : 'rgba(214,180,192,.55)';
                card.style.background = isComplete
                    ? 'linear-gradient(180deg,rgba(255,244,240,.98),rgba(255,250,248,.96))'
                    : 'linear-gradient(180deg,rgba(255,255,255,.98),rgba(255,248,246,.96))';
                card.style.boxShadow = isComplete ? '0 14px 28px rgba(111,78,92,.1)' : 'none';

                if (state) {
                    state.textContent = detail;
                    state.style.color = isComplete ? '#6f4e5c' : '#9a6d79';
                }
            }

            function syncActiveServiceCard() {
                modalServiceCards.forEach((card) => {
                    const isActive = String(card.dataset.serviceId || '') === String(activeServiceId || '');
                    card.style.display = isActive ? 'grid' : 'none';
                });

                serviceSummaryCards.forEach((card) => {
                    const isActive = String(card.dataset.serviceSummary || '') === String(activeServiceId || '');
                    card.style.cursor = 'pointer';
                    card.style.transform = isActive ? 'translateY(-2px)' : 'none';
                    card.style.boxShadow = isActive ? '0 14px 28px rgba(111,78,92,.12)' : 'none';
                    card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            }

            function paintSlotCard(button, state) {
                const card = button?.querySelector('.slot-card');

                if (!card) {
                    return;
                }

                const isUnavailable = state === 'unavailable';
                const isSelected = state === 'selected';

                card.style.borderColor = isSelected ? 'rgba(201,146,115,.72)' : 'rgba(214,180,192,.48)';
                card.style.background = isUnavailable
                    ? 'linear-gradient(180deg,rgba(241,245,249,.96),rgba(226,232,240,.92))'
                    : isSelected
                        ? 'linear-gradient(180deg,rgba(255,244,240,.98),rgba(255,250,248,.96))'
                        : 'rgba(255,255,255,.98)';
                card.style.color = isUnavailable ? '#64748b' : '#4f3340';
                card.style.opacity = isUnavailable ? '0.78' : '1';
                card.style.boxShadow = isSelected ? '0 12px 24px rgba(111,78,92,.1)' : 'none';
            }

            function renderServiceProgress() {
                if (!serviceProgressSummary) {
                    return;
                }

                const requiredServiceIds = getRequiredServiceIds();
                serviceProgressSummary.style.display = requiredServiceIds.length ? 'grid' : 'none';
            }

            function syncServiceCardState() {
                modalServiceCards.forEach((card) => {
                    const serviceId = String(card.dataset.serviceId || '');
                    const selectedButton = getSelectedStaffButton(serviceId);
                    const selectedName = selectedButton?.dataset.staffName || '';
                    const status = card.querySelector('[data-service-status]');
                    const isComplete = Boolean(selectedName);
                    const summaryCard = serviceSummaryCards.find((item) => String(item.dataset.serviceSummary || '') === serviceId);

                    card.classList.toggle('is-complete', isComplete);
                    card.style.borderColor = isComplete ? 'rgba(201,146,115,.72)' : 'rgba(214,180,192,.58)';
                    card.style.boxShadow = isComplete ? '0 14px 28px rgba(111,78,92,.1)' : '0 12px 28px rgba(88,54,70,.06)';

                    if (status) {
                        status.textContent = isComplete ? 'Ready' : 'Choose staff';
                        status.classList.toggle('is-complete', isComplete);
                        status.style.borderColor = isComplete ? 'rgba(201,146,115,.62)' : 'rgba(214,180,192,.65)';
                        status.style.background = isComplete ? 'rgba(255,244,240,.98)' : 'rgba(255,255,255,.9)';
                        status.style.color = isComplete ? '#6f4e5c' : '#8f6a75';
                    }

                    paintServiceSummaryCard(summaryCard, isComplete, isComplete ? selectedName : 'Choose staff');
                });

                syncActiveServiceCard();
                renderServiceProgress();
            }

            function updateBookingState() {
                const selectedSlot = slotInput?.value || '';
                const requiresSlot = selectedArrangementMode !== 'custom';
                const selectedLabels = getRequiredServiceIds()
                    .map((serviceId) => {
                        const button = getSelectedStaffButton(serviceId);
                        const staffName = button?.dataset.staffName || '';

                        return staffName ? `${getServiceName(serviceId)}: ${staffName}` : null;
                    })
                    .filter(Boolean);
                const pendingNames = getRequiredServiceIds()
                    .filter((serviceId) => !selectedStaffByService[serviceId])
                    .map((serviceId) => getServiceName(serviceId));
                const isReady = Boolean(comboInput?.value) && (!requiresSlot || Boolean(selectedSlot));

                if (bookingSummary) {
                    if (!selectedLabels.length) {
                        bookingSummary.textContent = 'Choose staff for each service.';
                    } else if (pendingNames.length) {
                        bookingSummary.textContent = `${selectedLabels.join(' | ')} | Pending: ${pendingNames.join(', ')}`;
                    } else if (requiresSlot && !selectedSlot) {
                        bookingSummary.textContent = `${selectedLabels.join(' | ')} | Choose time`;
                    } else if (selectedSlot) {
                        bookingSummary.textContent = `${selectedLabels.join(' | ')} | ${selectedSlot}`;
                    } else {
                        bookingSummary.textContent = selectedLabels.join(' | ');
                    }
                }

                if (slotCard) {
                    slotCard.classList.remove('hidden');
                }

                if (createAppointmentButton) {
                    createAppointmentButton.disabled = !isReady;
                }
            }

            function resetSlotSelection() {
                slotButtons.forEach((button) => {
                    button.querySelector('.slot-card')?.classList.remove('is-selected');
                    paintSlotCard(button, button.disabled ? 'unavailable' : 'default');
                });

                if (slotInput) {
                    slotInput.value = '';
                }
            }

            function syncSlotAvailability() {
                if (selectedArrangementMode === 'custom') {
                    return;
                }

                const allServicesSelected = areAllServicesSelected();
                timeSelectionSection?.classList.toggle('hidden', !allServicesSelected);

                slotButtons.forEach((button) => {
                    const combinations = parseJson(button.dataset.slotCombinations, []);
                    const matchedCombination = allServicesSelected ? getMatchingCombinationPayload(combinations) : null;
                    const isAvailable = Boolean(matchedCombination);

                    button.disabled = !allServicesSelected || !isAvailable;
                    button.querySelector('.slot-card')?.classList.toggle('is-unavailable', !isAvailable);
                    paintSlotCard(button, !isAvailable ? 'unavailable' : (slotInput?.value === button.dataset.slotTime ? 'selected' : 'default'));
                });

                if (slotHint) {
                    slotHint.textContent = allServicesSelected
                        ? 'Choose a time.'
                        : 'Choose staff for each service first.';
                }

                if (slotInput?.value) {
                    const selectedButton = slotButtons.find((button) => button.dataset.slotTime === slotInput.value);

                    if (!selectedButton || selectedButton.disabled) {
                        resetSlotSelection();
                        comboInput.value = '';
                        if (comboSelect) {
                            comboSelect.value = '';
                        }
                    }
                }
            }

            function selectStaffOption(button, preserveSlot = false) {
                if (!button) {
                    return;
                }

                const serviceId = String(button.dataset.serviceId || '');
                const staffId = String(button.dataset.staffId || '');

                if (!serviceId || !staffId) {
                    return;
                }

                selectedStaffByService[serviceId] = staffId;
                activeServiceId = serviceId;

                staffOptionButtons.forEach((staffButton) => {
                    const sameService = String(staffButton.dataset.serviceId || '') === serviceId;

                    if (sameService) {
                        const isSelected = staffButton === button;
                        staffButton.querySelector('.staff-pill')?.classList.toggle('is-selected', isSelected);
                        paintStaffPill(staffButton, isSelected);
                    }
                });

                syncServiceCardState();
                syncSlotAvailability();

                if (!preserveSlot) {
                    resetSlotSelection();
                }

                const matchingCombination = selectedArrangementMode === 'custom' && areAllServicesSelected()
                    ? getMatchingCustomCombination()
                    : null;
                comboInput.value = selectedArrangementMode === 'custom'
                    ? (matchingCombination?.payload || '')
                    : '';
                if (comboSelect) {
                    comboSelect.value = comboInput.value;
                }

                updateBookingState();
            }

            function selectSlot(button) {
                if (!button || button.disabled) {
                    return;
                }

                const combinations = parseJson(button.dataset.slotCombinations, []);
                const matchedCombination = getMatchingCombinationPayload(combinations);

                if (!matchedCombination) {
                    return;
                }

                slotButtons.forEach((slotButton) => {
                    slotButton.querySelector('.slot-card')?.classList.toggle('is-selected', slotButton === button);
                    paintSlotCard(slotButton, slotButton === button ? 'selected' : (slotButton.disabled ? 'unavailable' : 'default'));
                });

                if (slotInput) {
                    slotInput.value = button.dataset.slotTime || '';
                }

                comboInput.value = matchedCombination.payload || '';
                if (comboSelect) {
                    comboSelect.value = matchedCombination.payload || '';
                }

                updateBookingState();
            }

            staffOptionButtons.forEach((button) => {
                const meta = button.querySelector('.staff-pill__meta');

                if (meta && button.dataset.roleLabel) {
                    meta.textContent = button.dataset.roleLabel;
                }

                paintStaffPill(button, false);

                button.addEventListener('click', function () {
                    selectStaffOption(this);
                });
            });

            serviceSummaryCards.forEach((card) => {
                const activate = () => {
                    activeServiceId = card.dataset.serviceSummary || activeServiceId;
                    syncServiceCardState();
                };

                card.addEventListener('click', activate);
                card.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        activate();
                    }
                });
            });

            slotButtons.forEach((button) => {
                paintSlotCard(button, button.disabled ? 'unavailable' : 'default');

                button.addEventListener('click', function () {
                    selectSlot(this);
                });
            });

            function hideCustomerSuggestions() {
                if (customerSuggestions) {
                    customerSuggestions.innerHTML = '';
                    customerSuggestions.classList.add('hidden');
                }
            }

            function syncCustomerBookingState() {
                if (modalCustomerIdInput) {
                    modalCustomerIdInput.value = customerIdInput?.value || '';
                }

                if (modalCustomerNameInput) {
                    modalCustomerNameInput.value = customerNameInput?.value || '';
                }

                if (modalCustomerPhoneInput) {
                    modalCustomerPhoneInput.value = customerPhoneInput?.value || '';
                }

                if (!customerSummary) {
                    return;
                }

                const parts = [];

                if (customerNameInput?.value?.trim()) {
                    parts.push(customerNameInput.value.trim());
                }

                if (customerPhoneInput?.value?.trim()) {
                    parts.push(customerPhoneInput.value.trim());
                }

                customerSummary.textContent = parts.length
                    ? parts.join(' | ')
                    : 'Customer details required';
            }

            function renderSelectedCustomer(customer) {
                if (!customerSelectedHint) {
                    return;
                }

                if (!customer) {
                    customerSelectedHint.textContent = '';
                    customerSelectedHint.classList.add('hidden');
                    return;
                }

                const parts = [customer.full_name || 'Customer'];

                if (customer.phone) {
                    parts.push(customer.phone);
                }

                if (customer.membership_code) {
                    parts.push(`Member ${customer.membership_code}`);
                }

                customerSelectedHint.textContent = `Linked to existing customer: ${parts.join(' | ')}`;
                customerSelectedHint.classList.remove('hidden');
            }

            function selectCustomer(customer) {
                if (customerIdInput) {
                    customerIdInput.value = customer.id || '';
                }

                if (customerNameInput) {
                    customerNameInput.value = customer.full_name || '';
                }

                if (customerPhoneInput && customer.phone) {
                    customerPhoneInput.value = customer.phone;
                }

                renderSelectedCustomer(customer);
                hideCustomerSuggestions();
                syncCustomerBookingState();
            }

            function clearSelectedCustomer() {
                if (customerIdInput) {
                    customerIdInput.value = '';
                }

                renderSelectedCustomer(null);
                syncCustomerBookingState();
            }

            async function searchCustomers(query) {
                if (!customerSuggestions) {
                    return;
                }

                if (activeCustomerRequest) {
                    activeCustomerRequest.abort();
                }

                activeCustomerRequest = new AbortController();

                try {
                    const response = await fetch(`${customerSearchUrl}?q=${encodeURIComponent(query)}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: activeCustomerRequest.signal,
                    });

                    const result = await response.json().catch(() => ({ customers: [] }));
                    const customers = Array.isArray(result.customers) ? result.customers : [];

                    if (!customers.length) {
                        hideCustomerSuggestions();
                        return;
                    }

                    customerSuggestions.innerHTML = '';

                    customers.forEach((customer, index) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = `customer-suggestion${index === 0 ? ' is-active' : ''}`;
                        option.innerHTML = `
                            <div class="customer-suggestion__name">${customer.full_name || 'Customer'}</div>
                            <div class="customer-suggestion__meta">
                                ${(customer.phone || 'No phone')}
                                ${customer.membership_code ? ` | Member ${customer.membership_code}` : ''}
                                ${customer.current_package ? ` | ${customer.current_package}` : ''}
                            </div>
                        `;

                        option.addEventListener('click', () => selectCustomer(customer));
                        customerSuggestions.appendChild(option);
                    });

                    customerSuggestions.classList.remove('hidden');
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        hideCustomerSuggestions();
                    }
                }
            }

            if (customerNameInput) {
                customerNameInput.addEventListener('input', function () {
                    const query = this.value.trim();
                    clearSelectedCustomer();
                    syncCustomerBookingState();

                    if (query.length < 2) {
                        hideCustomerSuggestions();
                        return;
                    }

                    searchCustomers(query);
                });

                customerNameInput.addEventListener('blur', function () {
                    window.setTimeout(() => hideCustomerSuggestions(), 120);
                });
            }

            customerPhoneInput?.addEventListener('input', function () {
                if (customerIdInput?.value) {
                    clearSelectedCustomer();
                }

                syncCustomerBookingState();
            });

            syncServiceOrderFromSelection();
            syncServiceOrderUi();
            updateArrangementCards();
            applyCategoryTabState(defaultCategoryKey);
            syncCustomerBookingState();
            syncServiceCardState();
            syncSlotAvailability();
            updateBookingState();

            if (customerIdInput?.value && customerNameInput?.value) {
                renderSelectedCustomer({
                    full_name: customerNameInput.value,
                    phone: customerPhoneInput?.value || '',
                });
            }

            if (prefilledSlot && slotAvailable) {
                const matchedButton = slotButtons.find((button) => button.dataset.slotTime === prefilledSlot);

                if (matchedButton) {
                    const slotCombinations = parseJson(matchedButton.dataset.slotCombinations, []);
                    const matchedCombination = slotCombinations[0] || null;
                    const selectedMap = normalizeServiceStaffMap(parseJson(matchedCombination?.payload, {}).service_staff_map || {});

                    Object.entries(selectedMap).forEach(([serviceId, staffId]) => {
                        const matchingStaffButton = staffOptionButtons.find((button) => {
                            return String(button.dataset.serviceId || '') === String(serviceId)
                                && String(button.dataset.staffId || '') === String(staffId);
                        });

                        if (matchingStaffButton) {
                            selectStaffOption(matchingStaffButton, true);
                        }
                    });

                    if (Object.keys(selectedMap).length) {
                        selectSlot(matchedButton);
                    }
                }
            }

            if (availabilityModal && shouldAutoOpenAvailability) {
                openAvailabilityModal();
                clearAvailabilityAutoOpenFlag();
            }
        });
    </script>
</x-internal-layout>
