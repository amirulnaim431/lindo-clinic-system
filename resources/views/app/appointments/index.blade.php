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
        if (! empty($availability['slots']) && is_array($availability['slots'])) {
            $allSlots = $availability['slots'];
            ksort($allSlots);
            foreach ($allSlots as $slotTime => $slotData) {
                $combinations = $slotData['combinations'] ?? [];
                $slotOptions[] = [
                    'time' => $slotTime,
                    'combinations' => $combinations,
                    'count' => count($combinations),
                    'is_prefilled' => $prefilledSlot === $slotTime,
                    'is_available' => count($combinations) > 0,
                ];
            }
        }
        $selectedSlotRow = $prefilledSlot ? ($availability['slots'][$prefilledSlot] ?? null) : null;
        $dailyStatusCounts = [
            'total' => $appointmentGroups->count(),
            'checked_in' => 0,
            'completed' => 0,
            'reschedule' => 0,
        ];
        foreach ($appointmentGroups as $group) {
            $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');
            if ($statusValue === 'checked_in') {
                $dailyStatusCounts['checked_in']++;
            } elseif ($statusValue === 'completed') {
                $dailyStatusCounts['completed']++;
            } elseif (in_array($statusValue, ['cancelled', 'no_show'], true)) {
                $dailyStatusCounts['reschedule']++;
            }
        }
        $summaryCards = [
            [
                'key' => 'total',
                'label' => 'Total',
                'value' => $dailyStatusCounts['total'],
                'status' => null,
            ],
            [
                'key' => 'checked_in',
                'label' => 'Checked In',
                'value' => $dailyStatusCounts['checked_in'],
                'status' => 'checked_in',
            ],
            [
                'key' => 'completed',
                'label' => 'Completed',
                'value' => $dailyStatusCounts['completed'],
                'status' => 'completed',
            ],
            [
                'key' => 'reschedule',
                'label' => 'Reschedule',
                'value' => $dailyStatusCounts['reschedule'],
                'status' => 'reschedule',
            ],
        ];
        $appointmentGroupBuckets = [
            'total' => $appointmentGroups->values(),
            'checked_in' => $appointmentGroups->filter(function ($group) {
                $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');

                return $statusValue === 'checked_in';
            })->values(),
            'completed' => $appointmentGroups->filter(function ($group) {
                $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');

                return $statusValue === 'completed';
            })->values(),
            'reschedule' => $appointmentGroups->filter(function ($group) {
                $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');

                return in_array($statusValue, ['cancelled', 'no_show'], true);
            })->values(),
        ];
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
                    'category_label' => $service->category_label,
                    'description' => $service->description,
                    'duration' => (int) ($service->duration_minutes ?? 60),
                    'price' => $service->price,
                    'promo_price' => $service->promo_price,
                    'is_promo' => (bool) $service->is_promo,
                    'option_groups' => $service->optionGroups->map(function ($group) {
                        return [
                            'id' => (string) $group->id,
                            'name' => $group->name,
                            'is_required' => (bool) ($group->pivot?->is_required ?? true),
                            'values' => $group->values->map(fn ($value) => [
                                'id' => (string) $value->id,
                                'label' => $value->label,
                            ])->values()->all(),
                        ];
                    })->values()->all(),
                ],
            ];
        })->all();
        $selectedOptions = collect($filters['selected_options'] ?? [])
            ->mapWithKeys(function ($groupSelections, $serviceId) {
                return [(string) $serviceId => collect($groupSelections)
                    ->mapWithKeys(fn ($valueId, $groupId) => [(string) $groupId => (string) $valueId])
                    ->all()];
            })
            ->all();
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
        $shouldAutoOpenAvailability = ! empty($availability) && (bool) ($filters['open_availability'] ?? false);
        $staffGroupConfig = [
            'doctor' => ['label' => 'Doctors', 'rank' => 1],
            'nurse' => ['label' => 'Nurses', 'rank' => 2],
            'aesthetic_spa' => ['label' => 'Aesthetic & Spa', 'rank' => 3],
            'others' => ['label' => 'Remaining Staff', 'rank' => 4],
        ];
        $staffGroupKey = function (array $staff): string {
            $name = mb_strtolower((string) ($staff['full_name'] ?? ''));
            $role = mb_strtolower((string) ($staff['role_key'] ?? ''));

            if (str_contains($name, 'monica') || preg_match('/\bvan\b/u', $name)) {
                return 'aesthetic_spa';
            }

            if (in_array($role, ['doctor'], true)) {
                return 'doctor';
            }

            if (in_array($role, ['nurse'], true)) {
                return 'nurse';
            }

            if (in_array($role, ['aesthetic', 'aestatic', 'beautician', 'spa', 'spa_beauty'], true)) {
                return 'aesthetic_spa';
            }

            return 'others';
        };
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
                <div class="ops-kicker">{{ $isCheckInMode ? 'Status' : 'Appointments' }}</div>
                <h2 class="ops-title">{{ $isCheckInMode ? 'Status' : "Book and manage today's appointments" }}</h2>

                <div class="metrics-grid" style="margin-top:22px;">
                    <div class="metric-card">
                        <div class="metric-card__label">Date</div>
                        <div class="metric-card__value" style="font-size:22px;">{{ \Carbon\Carbon::parse($selectedDate)->format('d M') }}</div>
                        <div class="metric-card__meta">{{ \Carbon\Carbon::parse($selectedDate)->format('l') }}</div>
                    </div>
                    @foreach ($summaryCards as $card)
                        <div class="metric-card">
                            <div class="metric-card__label">{{ $card['label'] }}</div>
                            <div class="metric-card__value">{{ $card['value'] }}</div>
                        </div>
                    @endforeach
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
                                                <div class="service-line__name">{{ $item->displayServiceName() }}</div>
                                                @if ($item->optionSelections->isNotEmpty())
                                                    <div class="small-note">{{ $item->optionSelections->pluck('option_group_name', 'option_value_label')->map(fn ($group, $label) => $group.': '.$label)->implode(' | ') }}</div>
                                                @endif
                                                @if ($item->starts_at && $item->ends_at)
                                                    <div class="service-card__meta">{{ $item->starts_at->format('d M Y h:i A') }} - {{ $item->ends_at->format('h:i A') }}</div>
                                                @endif
                                            </div>
                                            <div class="service-line__staff">{{ $item->displayStaffName() }}@if($item->displayStaffRole()) ({{ $item->displayStaffRole() }}) @endif</div>
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
                            <div id="service-order-inputs">
                                @foreach ($selectedServiceOrderIds as $serviceOrderId)
                                    <input type="hidden" name="service_order[]" value="{{ $serviceOrderId }}">
                                @endforeach
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
                                <div class="field-block" style="margin-top:14px;">
                                    <label class="field-label" for="service-search-input">Search services</label>
                                    <input id="service-search-input" type="search" class="form-input" placeholder="Search treatment, category, or option">
                                </div>
                                <div class="service-grid" style="margin-top:14px;">
                                    @forelse ($services as $service)
                                        @php $isSelected = in_array((string) $service->id, $selectedServiceIds, true); @endphp
                                        <label
                                            class="service-card {{ $isSelected ? 'is-selected' : '' }} {{ $service->category_key !== $defaultCategoryKey ? 'hidden' : '' }}"
                                            data-service-category="{{ $service->category_key }}"
                                            data-service-id="{{ $service->id }}"
                                            data-service-search="{{ strtolower(trim($service->name.' '.$service->category_label.' '.collect($service->optionGroups)->flatMap(fn ($group) => $group->values->pluck('label'))->implode(' '))) }}"
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

                            <div id="service-option-panel" class="workflow-panel {{ count($selectedServiceOrderIds) ? '' : 'hidden' }}">
                                <div class="ops-kicker">Treatment options</div>
                                <div class="selection-card__title" style="margin-top:6px;">Select the exact session, cycle, or treatment area for each service that needs it</div>
                                <div id="service-option-grid" class="form-grid" style="margin-top:14px;">
                                    @foreach ($selectedServiceOrderIds as $serviceOrderId)
                                        @php
                                            $service = $services->firstWhere('id', $serviceOrderId);
                                            $serviceOptionGroups = collect($service?->optionGroups ?? []);
                                        @endphp
                                        @if ($service && $serviceOptionGroups->isNotEmpty())
                                            <div class="col-12" data-service-option-row="{{ $serviceOrderId }}">
                                                <div class="panel" style="margin:0;">
                                                    <div class="panel-body">
                                                        <div class="filter-bar__head" style="margin-bottom:1rem;">
                                                            <div>
                                                                <div class="selection-card__title">{{ $service->name }}</div>
                                                                <div class="small-note">{{ $service->category_label }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="form-grid">
                                                            @foreach ($serviceOptionGroups as $group)
                                                                @php $currentValue = $selectedOptions[(string) $serviceOrderId][(string) $group->id] ?? null; @endphp
                                                                <div class="col-12 field-block">
                                                                    <div class="field-label">{{ $group->name }}</div>
                                                                    <div class="btn-row" style="margin-top:10px;flex-wrap:wrap;">
                                                                        @foreach ($group->values as $value)
                                                                            <label class="btn {{ $currentValue === (string) $value->id ? 'btn-primary' : 'btn-secondary' }}" style="position:relative;">
                                                                                <input
                                                                                    type="radio"
                                                                                    name="selected_options[{{ $serviceOrderId }}][{{ $group->id }}]"
                                                                                    value="{{ $value->id }}"
                                                                                    class="selection-input option-radio"
                                                                                    data-option-service="{{ $serviceOrderId }}"
                                                                                    data-option-group="{{ $group->id }}"
                                                                                    {{ $currentValue === (string) $value->id ? 'checked' : '' }}
                                                                                >
                                                                                {{ $value->label }}
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
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

        @if (! $isCheckInMode && ! empty($availability))
            <div id="availability-modal" class="modal-shell hidden" aria-hidden="true">
                <div class="modal-backdrop" data-close-availability-modal></div>
                <div class="modal-stage" style="padding: clamp(12px, 2vw, 24px);">
                    <div class="modal-card availability-modal-card" style="width:min(1480px, 96vw); max-height:calc(100vh - 2rem);">
                        <div class="modal-header">
                            <div class="modal-header__row">
                                <div>
                                    <div class="modal-kicker">Availability</div>
                                    <h3 class="modal-title" style="font-size:28px;">{{ $selectedArrangementMode === 'custom' ? 'Available staff for selected custom schedule' : 'Available staff and times' }}</h3>
                                </div>
                                <button type="button" class="btn btn-secondary" data-close-availability-modal>Close</button>
                            </div>
                        </div>
                        <div class="modal-body availability-modal-body" style="max-height:calc(100vh - 10.5rem);">
                            <div class="availability-modal-layout" style="grid-template-columns:minmax(0, 1.8fr) minmax(360px, 1fr);">
                                <div class="availability-modal-main">
                                    @if (! empty($availability['services_without_eligible_staff']))
                                        <div class="flash flash--error">No active eligible staff assigned for: <strong>{{ implode(', ', $availability['services_without_eligible_staff']) }}</strong></div>
                                    @endif

                                    @if (! empty($availability['fully_booked_message']))
                                        <div class="flash flash--warn">{{ $availability['fully_booked_message'] }}</div>
                                    @endif

                                    @if (! empty($availability['custom_missing_message']))
                                        <div class="flash flash--warn">{{ $availability['custom_missing_message'] }}</div>
                                    @endif

                                    <div class="eligibility-grid">
                                        @foreach (($availability['selected_services'] ?? []) as $serviceSummary)
                                            <div class="eligibility-card">
                                                <div class="eligibility-card__title">{{ $serviceSummary['name'] ?? 'Service' }}</div>
                                                @if (! empty($serviceSummary['scheduled_date']) && ! empty($serviceSummary['scheduled_time']))
                                                    <div class="small-note">{{ \Carbon\Carbon::parse($serviceSummary['scheduled_date'])->format('d M Y') }} {{ $serviceSummary['scheduled_time'] }}</div>
                                                @endif
                                                @if (empty($serviceSummary['eligible_staff']))
                                                    <div class="field-error" style="margin-top:10px;font-size:13px;font-weight:700;">No active staff assigned.</div>
                                                @else
                                                    @php
                                                        $groupedServiceStaff = collect($serviceSummary['eligible_staff'])
                                                            ->groupBy(fn ($staff) => $staffGroupKey($staff))
                                                            ->sortBy(fn ($group, $key) => $staffGroupConfig[$key]['rank'] ?? 99);
                                                    @endphp
                                                    <div class="staff-group-list">
                                                        @foreach ($groupedServiceStaff as $groupKey => $staffMembers)
                                                            <div class="staff-group-item">
                                                                <div class="staff-group-item__label">{{ $staffGroupConfig[$groupKey]['label'] ?? 'Remaining Staff' }}</div>
                                                                <div class="staff-chip-wrap">
                                                                    @foreach ($staffMembers as $staff)
                                                                        <span class="staff-chip">{{ $staff['full_name'] }} ({{ $staff['role_key'] }})</span>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    @if ($selectedArrangementMode !== 'custom' && count($slotOptions))
                                        <div class="availability-section">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                                                <div>
                                                    <div class="ops-kicker">Choose a viable time</div>
                                                    <h4 class="panel-title-display" style="font-size:18px;">Available booking windows</h4>
                                                </div>
                                                @if ($selectedSlotRow)
                                                    <div class="summary-pill" style="padding:10px 12px;">
                                                        <span class="summary-pill__label">Selected slot duration</span>
                                                        <span class="summary-pill__value">{{ $selectedSlotRow['duration_minutes'] ?? ($availability['duration_minutes'] ?? 0) }} mins</span>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="slot-grid" style="margin-top:16px;">
                                                @foreach ($slotOptions as $slot)
                                                    <button
                                                        type="button"
                                                        class="slot-button slot-select-button {{ $slot['is_available'] ? '' : 'is-disabled' }}"
                                                        data-slot-time="{{ $slot['time'] }}"
                                                        data-combinations='@json($slot['combinations'])'
                                                        {{ $slot['is_available'] ? '' : 'disabled aria-disabled=true' }}
                                                    >
                                                        <div class="slot-card {{ $slot['is_prefilled'] ? 'is-selected' : '' }} {{ $slot['is_available'] ? '' : 'is-unavailable' }}">
                                                            <div>
                                                                <div class="slot-card__time">{{ $slot['time'] }}</div>
                                                                <div class="slot-card__meta">
                                                                    {{ $slot['is_available'] ? $slot['count'].' valid combo'.($slot['count'] === 1 ? '' : 's') : 'Unavailable' }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div id="selected-slot-card" class="booking-panel booking-panel--modal {{ $selectedArrangementMode === 'custom' && ! empty($availability['custom_combinations']) ? '' : 'hidden' }}">
                                    <div class="ops-kicker">Confirm booking</div>
                                    <div class="booking-panel__title">
                                        @if ($selectedArrangementMode === 'custom')
                                            Create custom appointment
                                        @else
                                            Create appointment at <span id="selected-slot-time-label">-</span>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('app.appointments.store') }}" class="booking-form" data-preserve-scroll-form>
                                        @csrf
                                        <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">
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
                                        <div id="booking-selected-option-inputs">
                                            @foreach ($selectedOptions as $serviceId => $groupSelections)
                                                @foreach ($groupSelections as $groupId => $valueId)
                                                    <input type="hidden" name="selected_options[{{ $serviceId }}][{{ $groupId }}]" value="{{ $valueId }}">
                                                @endforeach
                                            @endforeach
                                        </div>
                                        @foreach ($selectedServiceOrderIds as $serviceOrderId)
                                            <input type="hidden" name="custom_schedule[{{ $serviceOrderId }}][date]" value="{{ $customSchedule[$serviceOrderId]['date'] ?? $selectedDate }}">
                                            <input type="hidden" name="custom_schedule[{{ $serviceOrderId }}][start_time]" value="{{ $customSchedule[$serviceOrderId]['start_time'] ?? '' }}">
                                        @endforeach

                                        <div class="booking-form-grid">
                                            <div class="field-block">
                                                <label for="booking_date_display">Date</label>
                                                <input id="booking_date_display" type="text" class="field-input" value="{{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}" readonly>
                                            </div>
                                            <div class="field-block customer-picker">
                                                <label for="customer_full_name">Customer name</label>
                                                <input
                                                    id="customer_full_name"
                                                    type="text"
                                                    name="customer_full_name"
                                                    value="{{ old('customer_full_name') }}"
                                                    class="field-input"
                                                    autocomplete="off"
                                                    placeholder="Start typing member name or phone"
                                                    required
                                                >
                                                <div id="customer_suggestions" class="customer-suggestions hidden" role="listbox" aria-label="Customer suggestions"></div>
                                                <div id="customer_selected_hint" class="customer-picked hidden"></div>
                                            </div>
                                            <div class="field-block">
                                                <label for="customer_phone">Customer phone</label>
                                                <input id="customer_phone" type="text" name="customer_phone" value="{{ old('customer_phone') }}" class="field-input" required>
                                            </div>
                                        </div>

                                        <div class="field-block">
                                            <label for="selected_combination_select">Staff combination</label>
                                            <select id="selected_combination_select" class="field-input select-input" required></select>
                                        </div>

                                        <div class="field-block">
                                            <label for="notes">Front desk notes</label>
                                            <textarea id="notes" name="notes" class="field-input booking-textarea">{{ old('notes') }}</textarea>
                                        </div>

                                        <div class="booking-panel__footer">
                                            <button type="submit" class="action-btn action-btn--primary">Create Appointment</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                                                <div class="service-line__name">{{ $item->displayServiceName() }}</div>
                                                @if ($item->optionSelections->isNotEmpty())
                                                    <div class="small-note">{{ $item->optionSelections->pluck('option_group_name', 'option_value_label')->map(fn ($group, $label) => $group.': '.$label)->implode(' | ') }}</div>
                                                @endif
                                                @if ($item->starts_at && $item->ends_at)
                                                    <div class="service-card__meta">{{ $item->starts_at->format('h:i A') }} - {{ $item->ends_at->format('h:i A') }}</div>
                                                @endif
                                            </div>
                                            <div class="service-line__staff">{{ $item->displayStaffName() }}@if($item->displayStaffRole()) ({{ $item->displayStaffRole() }}) @endif</div>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const serviceCheckboxes = Array.from(document.querySelectorAll('.service-checkbox'));
            const arrangementRadios = Array.from(document.querySelectorAll('.arrangement-radio'));
            const slotButtons = Array.from(document.querySelectorAll('.slot-select-button'));
            const slotCard = document.getElementById('selected-slot-card');
            const slotTimeLabel = document.getElementById('selected-slot-time-label');
            const slotInput = document.getElementById('selected-slot-input');
            const comboInput = document.getElementById('selected-combination-input');
            const comboSelect = document.getElementById('selected_combination_select');
            const workflowList = document.getElementById('workflow-list');
            const workflowPanel = document.getElementById('workflow-panel');
            const customSchedulePanel = document.getElementById('custom-schedule-panel');
            const customScheduleGrid = document.getElementById('custom-schedule-grid');
            const serviceOptionPanel = document.getElementById('service-option-panel');
            const serviceOptionGrid = document.getElementById('service-option-grid');
            const serviceOrderInputs = document.getElementById('service-order-inputs');
            const bookingServiceOrderInputs = document.getElementById('booking-service-order-inputs');
            const bookingSelectedOptionInputs = document.getElementById('booking-selected-option-inputs');
            const serviceCategoryTabs = Array.from(document.querySelectorAll('.service-category-tab'));
            const serviceCards = Array.from(document.querySelectorAll('[data-service-category]'));
            const serviceSearchInput = document.getElementById('service-search-input');
            const prefilledSlot = @json($prefilledSlot);
            const slotAvailable = @json($quickCreate['slot_is_available']);
            const customCombinations = @json($availability['custom_combinations'] ?? []);
            const selectedArrangementMode = @json($selectedArrangementMode);
            const defaultDate = @json($selectedDate);
            const defaultCategoryKey = @json($defaultCategoryKey);
            const customScheduleSeed = @json($customSchedule);
            const customerIdInput = document.getElementById('customer_id');
            const customerNameInput = document.getElementById('customer_full_name');
            const customerPhoneInput = document.getElementById('customer_phone');
            const customerSuggestions = document.getElementById('customer_suggestions');
            const customerSelectedHint = document.getElementById('customer_selected_hint');
            const customerSearchUrl = @json(route('app.appointments.customer-search'));
            const selectedServicesSeed = @json($selectedServiceOrderIds);
            const selectedOptionsSeed = @json($selectedOptions);
            const serviceCatalog = @json($serviceCatalog);
            const scrollStorageKey = 'lindo-appointments-scroll';
            const availabilityModal = document.getElementById('availability-modal');
            const availabilityCloseButtons = Array.from(document.querySelectorAll('[data-close-availability-modal]'));
            let activeCustomerRequest = null;
            let selectedServiceOrder = Array.isArray(selectedServicesSeed) ? [...selectedServicesSeed] : [];
            let activeCategoryKey = defaultCategoryKey;
            let selectedOptions = typeof selectedOptionsSeed === 'object' && selectedOptionsSeed !== null ? { ...selectedOptionsSeed } : {};

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
                const searchQuery = (serviceSearchInput?.value || '').trim().toLowerCase();

                serviceCategoryTabs.forEach((button) => {
                    const isActive = button.dataset.categoryTab === activeCategoryKey;
                    button.classList.toggle('btn-primary', isActive);
                    button.classList.toggle('btn-secondary', !isActive);
                });

                serviceCards.forEach((card) => {
                    const matchesCategory = card.dataset.serviceCategory === activeCategoryKey;
                    const matchesSearch = searchQuery === '' || (card.dataset.serviceSearch || '').includes(searchQuery);
                    card.classList.toggle('hidden', !matchesCategory || !matchesSearch);
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
                renderServiceOptionRows();
                renderCustomScheduleRows();
            }

            function buildSelectedOptionInputs() {
                if (!bookingSelectedOptionInputs) {
                    return;
                }

                bookingSelectedOptionInputs.innerHTML = '';

                Object.entries(selectedOptions).forEach(([serviceId, groupSelections]) => {
                    if (!selectedServiceOrder.includes(serviceId) || typeof groupSelections !== 'object' || groupSelections === null) {
                        return;
                    }

                    Object.entries(groupSelections).forEach(([groupId, valueId]) => {
                        if (!valueId) {
                            return;
                        }

                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `selected_options[${serviceId}][${groupId}]`;
                        input.value = valueId;
                        bookingSelectedOptionInputs.appendChild(input);
                    });
                });
            }

            function renderServiceOptionRows() {
                if (!serviceOptionGrid || !serviceOptionPanel) {
                    return;
                }

                const rows = selectedServiceOrder
                    .map((serviceId) => ({ serviceId, service: serviceCatalog[serviceId] }))
                    .filter(({ service }) => service && Array.isArray(service.option_groups) && service.option_groups.length > 0);

                serviceOptionPanel.classList.toggle('hidden', rows.length === 0);

                if (!rows.length) {
                    serviceOptionGrid.innerHTML = '<div class="col-12 small-note">Selected services do not require extra treatment options.</div>';
                    buildSelectedOptionInputs();
                    return;
                }

                serviceOptionGrid.innerHTML = '';

                rows.forEach(({ serviceId, service }) => {
                    const serviceSelections = selectedOptions[serviceId] || {};
                    const column = document.createElement('div');
                    column.className = 'col-12';
                    column.dataset.serviceOptionRow = serviceId;
                    column.innerHTML = `
                        <div class="panel" style="margin:0;">
                            <div class="panel-body">
                                <div class="filter-bar__head" style="margin-bottom:1rem;">
                                    <div>
                                        <div class="selection-card__title">${service.name}</div>
                                        <div class="small-note">${service.category_label || ''}</div>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    ${service.option_groups.map((group) => `
                                        <div class="col-12 field-block">
                                            <div class="field-label">${group.name}</div>
                                            <div class="btn-row" style="margin-top:10px;flex-wrap:wrap;">
                                                ${group.values.map((value) => {
                                                    const checked = serviceSelections[group.id] === value.id;
                                                    return `
                                                        <label class="btn ${checked ? 'btn-primary' : 'btn-secondary'}">
                                                            <input
                                                                type="radio"
                                                                name="selected_options[${serviceId}][${group.id}]"
                                                                value="${value.id}"
                                                                class="selection-input option-radio"
                                                                data-option-service="${serviceId}"
                                                                data-option-group="${group.id}"
                                                                ${checked ? 'checked' : ''}
                                                            >
                                                            ${value.label}
                                                        </label>
                                                    `;
                                                }).join('')}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    `;
                    serviceOptionGrid.appendChild(column);
                });

                buildSelectedOptionInputs();
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

            serviceSearchInput?.addEventListener('input', function () {
                applyCategoryTabState(activeCategoryKey);
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

            serviceOptionGrid?.addEventListener('change', function (event) {
                const radio = event.target.closest('.option-radio');

                if (!radio) {
                    return;
                }

                const serviceId = radio.dataset.optionService || '';
                const groupId = radio.dataset.optionGroup || '';

                if (!serviceId || !groupId) {
                    return;
                }

                selectedOptions[serviceId] = {
                    ...(selectedOptions[serviceId] || {}),
                    [groupId]: radio.value,
                };

                renderServiceOptionRows();
            });

            function setCombinations(combinations) {
                if (! comboSelect) {
                    return;
                }

                comboSelect.innerHTML = '';

                combinations.forEach((combo, index) => {
                    const option = document.createElement('option');
                    option.value = combo.payload || '';
                    option.textContent = combo.label || `Combination ${index + 1}`;
                    comboSelect.appendChild(option);
                });

                comboInput.value = comboSelect.value || '';
            }

            function openSlot(time, combinations, triggerButton) {
                slotButtons.forEach((button) => button.querySelector('.slot-card')?.classList.remove('is-selected'));
                triggerButton?.querySelector('.slot-card')?.classList.add('is-selected');

                if (slotTimeLabel) {
                    slotTimeLabel.textContent = time || '-';
                }

                if (slotInput) {
                    slotInput.value = time || '';
                }

                setCombinations(combinations || []);

                if (slotCard) {
                    slotCard.classList.remove('hidden');
                    slotCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            comboSelect?.addEventListener('change', function () {
                comboInput.value = this.value || '';
            });

            slotButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    let combinations = [];

                    try {
                        combinations = JSON.parse(this.dataset.combinations || '[]');
                    } catch (error) {
                        combinations = [];
                    }

                    openSlot(this.dataset.slotTime || '', combinations, this);
                });
            });

            if (prefilledSlot && slotAvailable) {
                const matchedButton = Array.from(slotButtons).find((button) => button.dataset.slotTime === prefilledSlot);

                if (matchedButton) {
                    let combinations = [];

                    try {
                        combinations = JSON.parse(matchedButton.dataset.combinations || '[]');
                    } catch (error) {
                        combinations = [];
                    }

                    openSlot(prefilledSlot, combinations, matchedButton);
                }
            }

            function hideCustomerSuggestions() {
                if (customerSuggestions) {
                    customerSuggestions.innerHTML = '';
                    customerSuggestions.classList.add('hidden');
                }
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
            }

            function clearSelectedCustomer() {
                if (customerIdInput) {
                    customerIdInput.value = '';
                }

                renderSelectedCustomer(null);
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
            });

            syncServiceOrderFromSelection();
            syncServiceOrderUi();
            updateArrangementCards();
            applyCategoryTabState(defaultCategoryKey);

            if (selectedArrangementMode === 'custom' && customCombinations.length) {
                setCombinations(customCombinations);

                if (slotCard) {
                    slotCard.classList.remove('hidden');
                }
            }

            if (availabilityModal && shouldAutoOpenAvailability) {
                openAvailabilityModal();
            }
        });
    </script>
</x-internal-layout>
