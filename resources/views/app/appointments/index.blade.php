<x-internal-layout :title="'Customer Check-In'" :subtitle="'Cleaner front desk flow for booking, status updates, and same-day follow-up.'">
    @php
        $filters = $filters ?? ['date' => now()->format('Y-m-d'), 'service_ids' => [], 'slot' => null];
        $selectedServiceIds = collect($filters['service_ids'] ?? [])->map(fn ($id) => (string) $id)->values()->all();
        $selectedDate = $filters['date'] ?? now()->format('Y-m-d');
        $prefilledSlot = $filters['slot'] ?? null;
        $selectedArrangementMode = $filters['arrangement_mode'] ?? 'same_slot';
        $services = $services ?? collect();
        $availability = $availability ?? null;
        $appointmentGroups = $appointmentGroups ?? collect();
        $statusOptions = $statusOptions ?? [];
        $quickCreate = $quickCreate ?? ['prefilled_slot' => null, 'slot_is_available' => false, 'slot_combinations' => [], 'message' => null];
        $statusPalette = [
            'booked' => ['bg' => '#fff7ed', 'border' => '#fed7aa', 'text' => '#c2410c', 'label' => 'Pending'],
            'confirmed' => ['bg' => '#f0f9ff', 'border' => '#bae6fd', 'text' => '#0369a1', 'label' => 'Confirmed'],
            'checked_in' => ['bg' => '#f5f3ff', 'border' => '#ddd6fe', 'text' => '#6d28d9', 'label' => 'Checked In'],
            'completed' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#047857', 'label' => 'Completed'],
            'cancelled' => ['bg' => '#fff1f2', 'border' => '#fecdd3', 'text' => '#be123c', 'label' => 'Cancelled'],
            'no_show' => ['bg' => '#f8fafc', 'border' => '#cbd5e1', 'text' => '#475569', 'label' => 'No-show'],
        ];
        $slotOptions = [];
        if (! empty($availability['viable_slots'])) {
            foreach ($availability['viable_slots'] as $slotTime) {
                $slotData = $availability['slots'][$slotTime] ?? [];
                $slotOptions[] = [
                    'time' => $slotTime,
                    'combinations' => $slotData['combinations'] ?? [],
                    'count' => count($slotData['combinations'] ?? []),
                    'is_prefilled' => $prefilledSlot === $slotTime,
                ];
            }
        }
        $selectedSlotRow = $prefilledSlot ? ($availability['slots'][$prefilledSlot] ?? null) : null;
        $dailyStatusCounts = [
            'total' => $appointmentGroups->count(),
            'booked' => 0,
            'confirmed' => 0,
            'checked_in' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        foreach ($appointmentGroups as $group) {
            $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');
            if (array_key_exists($statusValue, $dailyStatusCounts)) {
                $dailyStatusCounts[$statusValue]++;
            } elseif ($statusValue === 'no_show') {
                $dailyStatusCounts['cancelled']++;
            } else {
                $dailyStatusCounts['booked']++;
            }
        }
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
        $serviceCatalog = $services->mapWithKeys(function ($service) {
            return [
                (string) $service->id => [
                    'id' => (string) $service->id,
                    'name' => $service->name,
                    'duration' => (int) ($service->duration_minutes ?? 60),
                ],
            ];
        })->all();
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

        @if ($quickCreate['message'])
            <div class="flash flash--warn">{{ $quickCreate['message'] }}</div>
        @endif

        <section class="ops-card ops-card--hero">
            <div class="ops-card__body">
                <div class="ops-kicker">Front desk</div>
                <h2 class="ops-title">Book and manage today's appointments</h2>
                <div class="ops-subtitle">Choose services, find an available team member, and confirm the booking without jumping between screens.</div>

                <div class="metrics-grid" style="margin-top:22px;">
                    <div class="metric-card">
                        <div class="metric-card__label">Date</div>
                        <div class="metric-card__value" style="font-size:22px;">{{ \Carbon\Carbon::parse($selectedDate)->format('d M') }}</div>
                        <div class="metric-card__meta">{{ \Carbon\Carbon::parse($selectedDate)->format('l') }}</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Total</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['total'] }}</div>
                        <div class="metric-card__meta">Booked groups</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Pending</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['booked'] }}</div>
                        <div class="metric-card__meta">Needs action</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Confirmed</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['confirmed'] }}</div>
                        <div class="metric-card__meta">Reserved</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Checked In</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['checked_in'] }}</div>
                        <div class="metric-card__meta">On site</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Completed</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['completed'] }}</div>
                        <div class="metric-card__meta">Finished</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="booking-grid">
            <div class="ops-card">
                <div class="ops-card__header">
                    <div class="ops-kicker">Booking builder</div>
                    <h3 class="panel-title-display" style="font-size:24px;">Create a booking</h3>
                    <div class="ops-subtitle" style="max-width:none;">Start with the service selection, then review only the workable time and staff combinations.</div>
                </div>

                <div class="ops-card__body">
                    <form method="GET" action="{{ route('app.appointments.index') }}" class="form-stack">
                        <input type="hidden" name="slot" value="{{ $prefilledSlot }}">
                        <div id="service-order-inputs">
                            @foreach ($selectedServiceOrderIds as $serviceOrderId)
                                <input type="hidden" name="service_order[]" value="{{ $serviceOrderId }}">
                            @endforeach
                        </div>

                        <div>
                            <div class="ops-kicker">Services</div>
                            <h4 class="panel-title-display" style="font-size:18px;">Choose services</h4>
                            <div class="service-grid" style="margin-top:14px;">
                                @forelse ($services as $service)
                                    @php $isSelected = in_array((string) $service->id, $selectedServiceIds, true); @endphp
                                    <label class="service-card {{ $isSelected ? 'is-selected' : '' }}">
                                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="selection-input service-checkbox" {{ $isSelected ? 'checked' : '' }}>
                                        <div style="display:flex;align-items:start;justify-content:space-between;gap:14px;">
                                            <div>
                                                <div class="service-card__title">{{ $service->name }}</div>
                                                <div class="service-card__meta">{{ $service->description ?: 'Operational service available for appointment scheduling.' }}</div>
                                            </div>
                                            <span class="service-card__badge">{{ (int) ($service->duration_minutes ?? 60) }} mins</span>
                                        </div>
                                    </label>
                                @empty
                                    <div class="flash flash--warn" style="grid-column:1 / -1;">No active services found. Activate clinic services first before checking appointment availability.</div>
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
                                    <div class="arrangement-card__meta">All selected services happen within the same appointment block. Best for consultation during treatment or parallel handling with different staff.</div>
                                </label>
                                <label class="arrangement-card {{ $selectedArrangementMode === 'back_to_back' ? 'is-selected' : '' }}">
                                    <input type="radio" name="arrangement_mode" value="back_to_back" class="selection-input arrangement-radio" {{ $selectedArrangementMode === 'back_to_back' ? 'checked' : '' }}>
                                    <div class="arrangement-card__title">Back-to-back</div>
                                    <div class="arrangement-card__meta">Services are scheduled sequentially inside one visit. Best for consultation before or after nails, facial, or other treatment steps.</div>
                                </label>
                            </div>
                        </div>

                        <div class="workflow-panel">
                            <div class="ops-kicker">Service workflow</div>
                            <div class="selection-card__title" style="margin-top:6px;">Set the service order used for back-to-back visits</div>
                            <div class="small-note" style="margin-top:6px;">This order matters only for sequential visits. Same-slot visits still keep the service lineup for staff awareness.</div>
                            <div id="workflow-list" class="workflow-list"></div>
                        </div>

                        <div>
                            <div class="ops-kicker">Date</div>
                            <h4 class="panel-title-display" style="font-size:18px;">Choose the clinic day</h4>
                            <div class="field-row" style="margin-top:14px;">
                                <div class="field-block">
                                    <label for="date">Appointment date</label>
                                    <input id="date" name="date" type="date" value="{{ $selectedDate }}" class="field-input" required>
                                </div>
                                <button type="submit" class="action-btn action-btn--primary" style="min-width:190px;">Check Availability</button>
                                <a href="{{ route('app.appointments.index') }}" class="action-btn action-btn--secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="form-stack">
                <div class="ops-card">
                    <div class="ops-card__body">
                        <div class="ops-kicker">Booking summary</div>
                        <h3 class="panel-title-display" style="font-size:22px;">{{ \Carbon\Carbon::parse($selectedDate)->format('l, d M Y') }}</h3>
                        <div class="ops-subtitle" style="margin-top:8px;max-width:none;">{{ $prefilledSlot ? 'Calendar slot preselected at '.$prefilledSlot.'. We will keep it in focus if it remains available.' : 'Choose services and date first, then select the best available time below.' }}</div>
                        <div class="summary-list" style="margin-top:18px;">
                            <div class="summary-pill">
                                <span class="summary-pill__label">Selected services</span>
                                <span class="summary-pill__value">{{ count($selectedServiceLabels) ? implode(', ', $selectedServiceLabels) : 'None yet' }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">Arrangement</span>
                                <span class="summary-pill__value">{{ $selectedArrangementMode === 'back_to_back' ? 'Back-to-back visit' : 'Same-slot visit' }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">Calendar slot</span>
                                <span class="summary-pill__value">{{ $prefilledSlot ?: 'Not prefilled' }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">Viable slots</span>
                                <span class="summary-pill__value">{{ count($slotOptions) ? count($slotOptions) : 'Check first' }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">Workflow order</span>
                                <span class="summary-pill__value">{{ count($selectedServiceOrderLabels) ? implode(' -> ', $selectedServiceOrderLabels) : 'Select services' }}</span>
                            </div>
                        </div>

                        <div style="margin-top:18px;">
                            <a href="{{ route('app.calendar', ['date' => $selectedDate]) }}" class="action-btn action-btn--secondary" style="width:100%;">Open Calendar for {{ \Carbon\Carbon::parse($selectedDate)->format('d M') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if (! empty($availability))
            <section class="ops-card">
                <div class="ops-card__header">
                    <div class="ops-kicker">Availability</div>
                    <h3 class="panel-title-display" style="font-size:24px;">Available staff and times</h3>
                    <div class="ops-subtitle" style="max-width:none;">Only workable combinations are shown, so the front desk can pick and confirm quickly.</div>
                </div>

                <div class="ops-card__body">

                    <div class="availability-grid">
                        @if (! empty($availability['services_without_eligible_staff']))
                            <div class="flash flash--error">No active eligible staff assigned for: <strong>{{ implode(', ', $availability['services_without_eligible_staff']) }}</strong></div>
                        @endif

                        @if (! empty($availability['fully_booked_message']))
                            <div class="flash flash--warn">{{ $availability['fully_booked_message'] }}</div>
                        @endif

                        <div class="eligibility-grid">
                            @foreach (($availability['selected_services'] ?? []) as $serviceSummary)
                                <div class="eligibility-card">
                                    <div class="eligibility-card__title">{{ $serviceSummary['name'] ?? 'Service' }}</div>
                                    <div class="small-note" style="margin-top:6px;">Eligible staff pool</div>
                                    @if (empty($serviceSummary['eligible_staff']))
                                        <div class="field-error" style="margin-top:10px;font-size:13px;font-weight:700;">No active staff assigned.</div>
                                    @else
                                        <div class="staff-chip-wrap">
                                            @foreach ($serviceSummary['eligible_staff'] as $staff)
                                                <span class="staff-chip">{{ $staff['full_name'] }} ({{ $staff['role_key'] }})</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if (count($slotOptions))
                            <div>
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
                                        <button type="button" class="slot-button slot-select-button" data-slot-time="{{ $slot['time'] }}" data-combinations='@json($slot['combinations'])'>
                                            <div class="slot-card {{ $slot['is_prefilled'] ? 'is-selected' : '' }}">
                                                <div>
                                                    <div class="slot-card__time">{{ $slot['time'] }}</div>
                                                    <div class="slot-card__meta">{{ $slot['count'] }} valid combo{{ $slot['count'] === 1 ? '' : 's' }}</div>
                                                </div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div id="selected-slot-card" class="booking-panel hidden">
                            <div class="ops-kicker">Confirm booking</div>
                            <div class="booking-panel__title">Create appointment at <span id="selected-slot-time-label">-</span></div>
                            <div class="booking-panel__subtitle">Pick the valid staff combination, then capture customer details. This preserves the operational staffing logic from the availability engine.</div>

                            <form method="POST" action="{{ route('app.appointments.store') }}" class="booking-form">
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

                                <div class="field-block">
                                    <label for="selected_combination_select">Staff combination</label>
                                    <select id="selected_combination_select" class="field-input select-input" required></select>
                                </div>

                                <div class="booking-form-grid">
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
                                    <label for="notes">Front desk notes</label>
                                    <textarea id="notes" name="notes" class="field-input booking-textarea">{{ old('notes') }}</textarea>
                                </div>

                                <div style="display:flex;justify-content:flex-end;">
                                    <button type="submit" class="action-btn action-btn--primary">Create Appointment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="ops-grid">
            <div class="ops-card">
                <div class="ops-card__header">
                    <div class="ops-kicker">Live Booking Queue</div>
                    <h3 class="panel-title-display" style="font-size:24px;">Schedule for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h3>
                    <div class="ops-subtitle" style="max-width:none;">Current appointment groups for the selected day. Front desk can review service allocations and update status without leaving the booking desk.</div>
                </div>

                <div class="ops-card__body">

                    <div class="schedule-list">
                        @forelse ($appointmentGroups as $group)
                            @php
                                $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');
                                $statusStyle = $statusPalette[$statusValue] ?? ['bg' => '#f8fafc', 'border' => '#cbd5e1', 'text' => '#475569', 'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $statusValue))];
                                $statusLabel = $group->status instanceof \App\Enums\AppointmentStatus ? $group->status->label() : $statusStyle['label'];
                            @endphp
                            <article class="schedule-card">
                                <div class="schedule-card__head">
                                    <div>
                                        <div class="schedule-card__time">{{ optional($group->starts_at)->format('h:i A') ?? '-' }} @if(optional($group->ends_at)) - {{ optional($group->ends_at)->format('h:i A') }} @endif</div>
                                        <div class="schedule-card__name">{{ $group->customer?->full_name ?? 'Customer' }}</div>
                                        <div class="schedule-card__phone">{{ $group->customer?->phone ?? 'No phone recorded' }}</div>
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
                                    <label for="status-{{ $group->id }}" class="field-label" style="color: var(--app-accent-strong);">Update status</label>
                                    <select id="status-{{ $group->id }}" name="status" onchange="this.form.submit()" class="field-input select-input">
                                        @foreach ($statusOptions as $option)
                                            @php
                                                $optionValue = $option instanceof \BackedEnum ? $option->value : (is_string($option) ? $option : '');
                                                $optionLabel = $option instanceof \App\Enums\AppointmentStatus ? $option->label() : \Illuminate\Support\Str::headline(str_replace('_', ' ', $optionValue));
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
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
            const arrangementRadios = document.querySelectorAll('.arrangement-radio');
            const slotButtons = document.querySelectorAll('.slot-select-button');
            const slotCard = document.getElementById('selected-slot-card');
            const slotTimeLabel = document.getElementById('selected-slot-time-label');
            const slotInput = document.getElementById('selected-slot-input');
            const comboInput = document.getElementById('selected-combination-input');
            const comboSelect = document.getElementById('selected_combination_select');
            const workflowList = document.getElementById('workflow-list');
            const serviceOrderInputs = document.getElementById('service-order-inputs');
            const bookingServiceOrderInputs = document.getElementById('booking-service-order-inputs');
            const prefilledSlot = @json($prefilledSlot);
            const slotAvailable = @json($quickCreate['slot_is_available']);
            const customerIdInput = document.getElementById('customer_id');
            const customerNameInput = document.getElementById('customer_full_name');
            const customerPhoneInput = document.getElementById('customer_phone');
            const customerSuggestions = document.getElementById('customer_suggestions');
            const customerSelectedHint = document.getElementById('customer_selected_hint');
            const customerSearchUrl = @json(route('app.appointments.customer-search'));
            const selectedServicesSeed = @json($selectedServiceOrderIds);
            const serviceCatalog = @json($serviceCatalog);
            let activeCustomerRequest = null;
            let selectedServiceOrder = Array.isArray(selectedServicesSeed) ? [...selectedServicesSeed] : [];

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

            function syncServiceOrderUi() {
                buildHiddenInputs(serviceOrderInputs, 'service_order[]', selectedServiceOrder);
                buildHiddenInputs(bookingServiceOrderInputs, 'service_order[]', selectedServiceOrder);
                renderWorkflowList();
            }

            function updateArrangementCards() {
                arrangementRadios.forEach((radio) => {
                    radio.closest('.arrangement-card')?.classList.toggle('is-selected', radio.checked);
                });
            }

            serviceCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    this.closest('.service-card')?.classList.toggle('is-selected', this.checked);
                    syncServiceOrderFromSelection();
                    syncServiceOrderUi();
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
        });
    </script>
</x-internal-layout>
