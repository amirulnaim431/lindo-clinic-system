<x-app-layout>
    <div class="page-wrap">
        @if (session('success'))
            <div class="alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert-error">
                <div class="mb-2 font-semibold">Fix the following:</div>
                <ul class="list-disc pl-5 space-y-1">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="page-head">
            <div>
                <h1 class="page-title">Appointments</h1>
                <p class="page-subtitle">
                    Phase 1 uses 1-hour concurrent booking windows from 09:00 to 17:00.
                </p>
                <p class="page-note">
                    Select one or more services. Only slots with valid staff combinations will be shown.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('app.dashboard') }}" class="btn btn-secondary">
                    ← Back to Dashboard
                </a>
            </div>
        </div>

        <div class="content-grid">
            <div class="content-grid__left">
                <div class="panel">
                    <div class="panel__header">
                        <h2 class="panel__title">Create appointment</h2>
                        <p class="panel__subtitle">
                            Choose date and services, then pick a valid staff combination.
                        </p>
                    </div>

                    <div class="panel__body">
                        <form method="GET" action="{{ route('app.appointments.index') }}" class="field-group">
                            <div>
                                <label for="date" class="field-label">Date</label>
                                <input
                                    id="date"
                                    name="date"
                                    type="date"
                                    value="{{ $filters['date'] ?? now()->toDateString() }}"
                                    class="form-input"
                                >
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label class="field-label !mb-0">Services</label>
                                    <span class="text-xs text-slate-500">Select all services needed in the same 1-hour slot</span>
                                </div>

                                <div class="service-grid">
                                    @foreach($services as $svc)
                                        @php
                                            $checked = in_array($svc->id, $filters['service_ids'] ?? []);
                                        @endphp
                                        <label class="service-option">
                                            <input
                                                type="checkbox"
                                                name="service_ids[]"
                                                value="{{ $svc->id }}"
                                                class="service-option__input js-service-checkbox"
                                                data-service-name="{{ $svc->name }}"
                                                @checked($checked)
                                            >
                                            <div class="service-option__card">
                                                <div class="pr-7">{{ $svc->name }}</div>
                                                <span class="service-option__check">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.415 0l-3-3a1 1 0 011.414-1.42l2.293 2.294 6.543-6.544a1 1 0 011.415 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="selected-services">
                                    <div class="selected-services__title">Selected services</div>
                                    <div id="selected-services-empty" class="selected-services__empty">
                                        No services selected yet.
                                    </div>
                                    <div id="selected-services-list" class="selected-services__list"></div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button type="submit" class="btn btn-primary">
                                    Check availability
                                </button>

                                <a
                                    href="{{ route('app.appointments.index', ['date' => $filters['date'] ?? now()->toDateString()]) }}"
                                    class="btn btn-secondary"
                                >
                                    Clear
                                </a>
                            </div>
                        </form>

                        <div class="section-soft mt-8">
                            <div class="mb-4">
                                <h3 class="text-sm font-semibold text-slate-900">Eligibility & availability</h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    Slots only appear when every selected service can be covered by a different available staff member.
                                </p>
                            </div>

                            @if(!empty($availability))
                                @if(!empty($availability['services_without_eligible_staff']))
                                    <div class="alert-error !mb-4">
                                        No active eligible staff assigned for:
                                        <span class="font-semibold">{{ implode(', ', $availability['services_without_eligible_staff']) }}</span>
                                    </div>
                                @endif

                                <div class="summary-list mb-5">
                                    @foreach($availability['selected_services'] as $serviceSummary)
                                        <div class="summary-card">
                                            <div class="summary-card__title">{{ $serviceSummary['name'] }}</div>
                                            <div class="summary-card__meta">Eligible staff</div>

                                            @if(empty($serviceSummary['eligible_staff']))
                                                <div class="mt-2 text-sm text-rose-600">No active staff assigned.</div>
                                            @else
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach($serviceSummary['eligible_staff'] as $staff)
                                                        <span class="chip">
                                                            {{ $staff['full_name'] }} ({{ $staff['role_key'] }})
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @if(!empty($availability['fully_booked_message']))
                                    <div class="alert-warning">
                                        {{ $availability['fully_booked_message'] }}
                                    </div>
                                @endif

                                @if(!empty($availability['viable_slots']))
                                    <form method="POST" action="{{ route('app.appointments.store') }}" class="field-group mt-5">
                                        @csrf
                                        <input type="hidden" name="date" value="{{ $filters['date'] ?? now()->toDateString() }}">

                                        @foreach(($filters['service_ids'] ?? []) as $sid)
                                            <input type="hidden" name="service_ids[]" value="{{ $sid }}">
                                        @endforeach

                                        <div>
                                            <div class="mb-3 flex items-center justify-between gap-3">
                                                <h3 class="text-sm font-semibold text-slate-900">Available slots</h3>
                                                <span class="text-xs text-slate-500">Choose slot first</span>
                                            </div>

                                            <div class="slot-grid">
                                                @foreach($availability['viable_slots'] as $slot)
                                                    <label class="slot-option">
                                                        <input
                                                            type="radio"
                                                            name="slot"
                                                            value="{{ $slot }}"
                                                            class="slot-option__input slot-radio"
                                                            @checked(old('slot') === $slot)
                                                        >
                                                        <div class="slot-option__card">
                                                            {{ $slot }}
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div>
                                            <label for="selected_combination" class="field-label">
                                                Staff combination
                                            </label>
                                            <select
                                                id="selected_combination"
                                                name="selected_combination"
                                                class="form-select"
                                                required
                                            >
                                                <option value="">Select a slot first</option>
                                            </select>
                                            <p id="combination_help" class="field-hint">
                                                For VIP/VVIP cases, front desk can choose the preferred valid combination.
                                            </p>
                                        </div>

                                        <div>
                                            <label for="customer_full_name" class="field-label">Customer name</label>
                                            <input
                                                id="customer_full_name"
                                                name="customer_full_name"
                                                type="text"
                                                value="{{ old('customer_full_name') }}"
                                                class="form-input"
                                                required
                                            >
                                        </div>

                                        <div>
                                            <label for="customer_phone" class="field-label">Customer phone</label>
                                            <input
                                                id="customer_phone"
                                                name="customer_phone"
                                                type="text"
                                                value="{{ old('customer_phone') }}"
                                                class="form-input"
                                                required
                                            >
                                        </div>

                                        <div>
                                            <label for="notes" class="field-label">Notes (optional)</label>
                                            <textarea
                                                id="notes"
                                                name="notes"
                                                rows="4"
                                                class="form-textarea"
                                            >{{ old('notes') }}</textarea>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
                                            All selected services will be booked in the same 1-hour slot. The selected staff combination must remain valid at booking time.
                                        </div>

                                        <div class="flex justify-end">
                                            <button type="submit" class="btn btn-primary">
                                                Book Appointment
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            @else
                                <div class="empty-state">
                                    Select a date and one or more services, then click
                                    <span class="font-semibold text-slate-700">Check availability</span>.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-grid__right">
                <div class="panel">
                    <div class="panel__header">
                        <h2 class="panel__title">Daily schedule</h2>
                        <p class="panel__subtitle">
                            Showing {{ $filters['date'] ?? now()->toDateString() }}
                        </p>
                    </div>

                    <div class="panel__body">
                        <form method="GET" action="{{ route('app.appointments.index') }}" class="grid gap-4 lg:grid-cols-4">
                            <div>
                                <label for="filter_date" class="field-label">Date</label>
                                <input
                                    id="filter_date"
                                    name="date"
                                    type="date"
                                    value="{{ $filters['date'] ?? now()->toDateString() }}"
                                    class="form-input"
                                >
                            </div>

                            <div>
                                <label for="staff_id" class="field-label">Staff</label>
                                <select id="staff_id" name="staff_id" class="form-select">
                                    <option value="">All staff</option>
                                    @foreach($staffList as $s)
                                        <option value="{{ $s->id }}" @selected(($filters['staff_id'] ?? null) == $s->id)>
                                            {{ $s->full_name }} ({{ $s->role_key }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="status" class="field-label">Status</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="">All statuses</option>
                                    @foreach($statusOptions as $st)
                                        @php
                                            $statusValue = is_object($st) ? $st->value : $st;
                                            $statusLabel = is_object($st) && method_exists($st, 'label')
                                                ? $st->label()
                                                : ucfirst(str_replace('_', ' ', $statusValue));
                                        @endphp
                                        <option value="{{ $statusValue }}" @selected(($filters['status'] ?? null) == $statusValue)>
                                            {{ $statusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex items-end">
                                <button type="submit" class="btn btn-primary w-full">
                                    Apply Filters
                                </button>
                            </div>
                        </form>

                        <div class="table-wrap mt-6">
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Time</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Customer</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Services</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Assigned staff</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                                        <th class="px-4 py-3 text-left font-semibold text-slate-700">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    @forelse($appointmentGroups as $g)
                                        @php
                                            $servicesSummary = $g->items?->map(fn($i) => $i->service?->name)->filter()->unique()->implode(', ') ?: '-';
                                            $staffSummary = $g->items?->map(fn($i) => $i->staff?->full_name)->filter()->unique()->implode(', ') ?: '-';
                                            $currentStatus = is_object($g->status) ? $g->status->value : (string) $g->status;
                                            $currentStatusLabel = is_object($g->status) && method_exists($g->status, 'label')
                                                ? $g->status->label()
                                                : ucfirst(str_replace('_', ' ', $currentStatus));
                                        @endphp
                                        <tr class="align-top">
                                            <td class="px-4 py-4 font-semibold text-slate-900 whitespace-nowrap">
                                                {{ optional($g->starts_at)->format('H:i') }}
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="font-medium text-slate-900">{{ $g->customer?->full_name ?? '-' }}</div>
                                                <div class="mt-1 text-xs text-slate-500">{{ $g->customer?->phone ?? '' }}</div>
                                            </td>
                                            <td class="px-4 py-4 text-slate-700">{{ $servicesSummary }}</td>
                                            <td class="px-4 py-4 text-slate-700">{{ $staffSummary }}</td>
                                            <td class="px-4 py-4">
                                                <span class="chip">{{ $currentStatusLabel }}</span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <form method="POST" action="{{ route('app.appointments.status', $g) }}" class="flex flex-col gap-2 sm:flex-row">
                                                    @csrf
                                                    @method('PATCH')

                                                    <select name="status" class="form-select">
                                                        @foreach($statusOptions as $st)
                                                            @php
                                                                $statusValue = is_object($st) ? $st->value : $st;
                                                                $statusLabel = is_object($st) && method_exists($st, 'label')
                                                                    ? $st->label()
                                                                    : ucfirst(str_replace('_', ' ', $statusValue));
                                                            @endphp
                                                            <option value="{{ $statusValue }}" @selected($currentStatus === $statusValue)>
                                                                {{ $statusLabel }}
                                                            </option>
                                                        @endforeach
                                                    </select>

                                                    <button type="submit" class="btn btn-secondary">
                                                        Update
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                                No appointments found for this date/filter.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-6">
                            {{ $appointmentGroups->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($availability['slots']))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const slotDetails = @json($availability['slots']);
                const combinationSelect = document.getElementById('selected_combination');
                const helpText = document.getElementById('combination_help');
                const radios = document.querySelectorAll('.slot-radio');
                const serviceCheckboxes = document.querySelectorAll('.js-service-checkbox');
                const selectedServicesEmpty = document.getElementById('selected-services-empty');
                const selectedServicesList = document.getElementById('selected-services-list');

                function renderSelectedServices() {
                    if (!selectedServicesList || !selectedServicesEmpty) return;

                    const selected = Array.from(serviceCheckboxes)
                        .filter((checkbox) => checkbox.checked)
                        .map((checkbox) => checkbox.dataset.serviceName);

                    selectedServicesList.innerHTML = '';

                    if (!selected.length) {
                        selectedServicesEmpty.style.display = 'block';
                        return;
                    }

                    selectedServicesEmpty.style.display = 'none';

                    selected.forEach(function (name) {
                        const chip = document.createElement('span');
                        chip.className = 'chip';
                        chip.textContent = name;
                        selectedServicesList.appendChild(chip);
                    });
                }

                function updateCombinationOptions() {
                    if (!combinationSelect) return;

                    const selectedSlot = document.querySelector('.slot-radio:checked');
                    combinationSelect.innerHTML = '';

                    if (!selectedSlot) {
                        combinationSelect.innerHTML = '<option value="">Select a slot first</option>';
                        if (helpText) {
                            helpText.textContent = 'For VIP/VVIP cases, front desk can choose the preferred valid combination.';
                        }
                        return;
                    }

                    const slotKey = selectedSlot.value;
                    const combinations = (slotDetails[slotKey] && slotDetails[slotKey].combinations)
                        ? slotDetails[slotKey].combinations
                        : [];

                    if (!combinations.length) {
                        combinationSelect.innerHTML = '<option value="">No valid combinations for this slot</option>';
                        if (helpText) {
                            helpText.textContent = 'Please choose another slot.';
                        }
                        return;
                    }

                    combinations.forEach(function (combo, index) {
                        const option = document.createElement('option');
                        option.value = combo.payload;
                        option.textContent = combo.label;
                        if (index === 0) {
                            option.selected = true;
                        }
                        combinationSelect.appendChild(option);
                    });

                    if (helpText) {
                        helpText.textContent = combinations.length + ' valid combination(s) available for ' + slotKey + '.';
                    }
                }

                serviceCheckboxes.forEach(function (checkbox) {
                    checkbox.addEventListener('change', renderSelectedServices);
                });

                radios.forEach(function (radio) {
                    radio.addEventListener('change', updateCombinationOptions);
                });

                renderSelectedServices();
                updateCombinationOptions();
            });
        </script>
    @else
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const serviceCheckboxes = document.querySelectorAll('.js-service-checkbox');
                const selectedServicesEmpty = document.getElementById('selected-services-empty');
                const selectedServicesList = document.getElementById('selected-services-list');

                function renderSelectedServices() {
                    if (!selectedServicesList || !selectedServicesEmpty) return;

                    const selected = Array.from(serviceCheckboxes)
                        .filter((checkbox) => checkbox.checked)
                        .map((checkbox) => checkbox.dataset.serviceName);

                    selectedServicesList.innerHTML = '';

                    if (!selected.length) {
                        selectedServicesEmpty.style.display = 'block';
                        return;
                    }

                    selectedServicesEmpty.style.display = 'none';

                    selected.forEach(function (name) {
                        const chip = document.createElement('span');
                        chip.className = 'chip';
                        chip.textContent = name;
                        selectedServicesList.appendChild(chip);
                    });
                }

                serviceCheckboxes.forEach(function (checkbox) {
                    checkbox.addEventListener('change', renderSelectedServices);
                });

                renderSelectedServices();
            });
        </script>
    @endif
</x-app-layout>