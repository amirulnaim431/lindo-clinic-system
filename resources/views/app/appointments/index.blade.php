@extends('layouts.internal')

@section('title', 'Appointments')

@section('content')
@php
    $filters = $filters ?? [
        'date' => now()->format('Y-m-d'),
        'service_ids' => [],
        'staff_id' => null,
        'status' => null,
    ];

    $selectedServiceIds = collect($filters['service_ids'] ?? [])
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();

    $selectedDate = $filters['date'] ?? now()->format('Y-m-d');
    $services = $services ?? collect();
    $availability = $availability ?? null;
    $appointmentGroups = $appointmentGroups ?? collect();
    $statusOptions = $statusOptions ?? [];
    $staffList = $staffList ?? collect();

    $statusColors = [
        'booked' => 'bg-amber-100 text-amber-800 border-amber-200',
        'confirmed' => 'bg-sky-100 text-sky-800 border-sky-200',
        'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
        'checked_in' => 'bg-violet-100 text-violet-800 border-violet-200',
        'no_show' => 'bg-slate-100 text-slate-700 border-slate-200',
    ];

    $slotOptions = [];
    if (!empty($availability['viable_slots'])) {
        foreach ($availability['viable_slots'] as $slotTime) {
            $slotData = $availability['slots'][$slotTime] ?? [];
            $combinations = $slotData['combinations'] ?? [];
            $slotOptions[] = [
                'time' => $slotTime,
                'combinations' => $combinations,
                'count' => count($combinations),
            ];
        }
    }
@endphp

<div class="space-y-6">
    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            <div class="font-semibold">Please fix the following:</div>
            <ul class="mt-2 space-y-1">
                @foreach ($errors->all() as $e)
                    <li>• {{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">Check Availability</h2>
            <p class="mt-1 text-sm text-slate-500">
                Choose service, date, then click a time bubble to open one booking form.
            </p>
            <div class="mt-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                Compact booking flow
            </div>
        </div>

        <div class="px-5 py-5">
            <form method="GET" action="{{ route('app.appointments.index') }}" class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Services</label>

                    @if($services->count())
                        <div class="grid gap-3">
                            @foreach ($services as $service)
                                @php
                                    $isSelected = in_array((string) $service->id, $selectedServiceIds, true);
                                @endphp
                                <label class="flex cursor-pointer items-center justify-between rounded-2xl border px-4 py-4 transition {{ $isSelected ? 'border-slate-900 bg-slate-50' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                                    <div class="flex items-start gap-3">
                                        <input
                                            type="checkbox"
                                            name="service_ids[]"
                                            value="{{ $service->id }}"
                                            class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-300"
                                            @checked($isSelected)
                                        >
                                        <div>
                                            <div class="text-sm font-semibold text-slate-900">{{ $service->name }}</div>
                                            @if(!empty($service->description))
                                                <div class="mt-1 text-sm text-slate-500">{{ $service->description }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm font-medium text-slate-700">
                                        {{ (int) ($service->duration_minutes ?? 60) }} mins
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                            No services available yet.
                        </div>
                    @endif
                </div>

                <div class="grid gap-4 lg:grid-cols-[220px_220px_220px_auto] lg:items-end">
                    <div>
                        <label for="date" class="mb-2 block text-sm font-medium text-slate-700">Date</label>
                        <input
                            id="date"
                            name="date"
                            type="date"
                            value="{{ $selectedDate }}"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                        >
                    </div>

                    <div>
                        <label for="staff_id" class="mb-2 block text-sm font-medium text-slate-700">Staff</label>
                        <select
                            id="staff_id"
                            name="staff_id"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="">All staff</option>
                            @foreach($staffList as $staff)
                                <option value="{{ $staff->id }}" @selected((string) ($filters['staff_id'] ?? '') === (string) $staff->id)>
                                    {{ $staff->full_name }} ({{ $staff->role_key }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status" class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                        <select
                            id="status"
                            name="status"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="">All statuses</option>
                            @foreach($statusOptions as $status)
                                @php
                                    $statusValue = is_object($status) ? $status->value : (string) $status;
                                    $statusLabel = is_object($status) && method_exists($status, 'label')
                                        ? $status->label()
                                        : ucfirst(str_replace('_', ' ', $statusValue));
                                @endphp
                                <option value="{{ $statusValue }}" @selected((string) ($filters['status'] ?? '') === $statusValue)>
                                    {{ $statusLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-3">
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            Check Availability
                        </button>

                        <a
                            href="{{ route('app.appointments.index') }}"
                            class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    @if (!empty($availability))
        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Eligibility & Availability</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Click a time bubble, then complete the booking form below.
                </p>
            </div>

            <div class="space-y-5 px-5 py-5">
                @if(!empty($availability['services_without_eligible_staff']))
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        No active eligible staff assigned for: {{ implode(', ', $availability['services_without_eligible_staff']) }}
                    </div>
                @endif

                <div class="grid gap-4 lg:grid-cols-2">
                    @foreach(($availability['selected_services'] ?? []) as $serviceSummary)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-sm font-semibold text-slate-900">
                                {{ $serviceSummary['name'] ?? 'Service' }}
                            </div>
                            <div class="mt-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Eligible staff
                            </div>

                            @if(empty($serviceSummary['eligible_staff']))
                                <div class="mt-2 text-sm text-slate-500">No active staff assigned.</div>
                            @else
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach($serviceSummary['eligible_staff'] as $staff)
                                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm text-slate-700">
                                            {{ $staff['full_name'] }} ({{ $staff['role_key'] }})
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if(!empty($availability['fully_booked_message']))
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {{ $availability['fully_booked_message'] }}
                    </div>
                @endif

                @if(count($slotOptions))
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-sm font-semibold text-slate-900">Choose Time</div>
                        <p class="mt-1 text-sm text-slate-500">Click one slot, then fill in the booking form once.</p>

                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach($slotOptions as $slot)
                                <button
                                    type="button"
                                    class="slot-picker rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                                    data-slot="{{ $slot['time'] }}"
                                    data-combinations='@json($slot['combinations'])'
                                >
                                    <div class="text-sm font-semibold text-slate-900">{{ $slot['time'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $slot['count'] }} combo{{ $slot['count'] === 1 ? '' : 's' }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h3 class="text-lg font-semibold text-slate-900">Create Appointment</h3>
                            <p class="mt-1 text-sm text-slate-500">Selected time: <span id="selected-slot-label">—</span></p>
                        </div>

                        <form method="POST" action="{{ route('app.appointments.store') }}" class="space-y-5 px-5 py-5">
                            @csrf
                            <input type="hidden" name="date" value="{{ $selectedDate }}">
                            <input type="hidden" name="slot" id="selected-slot-input" value="{{ old('slot') }}">
                            @foreach($selectedServiceIds as $sid)
                                <input type="hidden" name="service_ids[]" value="{{ $sid }}">
                            @endforeach
                            <input type="hidden" name="selected_combination" id="selected-combination-input" value="{{ old('selected_combination') }}">

                            <div>
                                <label for="selected_combination_select" class="mb-2 block text-sm font-medium text-slate-700">Staff Combination</label>
                                <select
                                    id="selected_combination_select"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                >
                                    <option value="">Select a time slot first</option>
                                </select>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="customer_full_name" class="mb-2 block text-sm font-medium text-slate-700">Customer Name</label>
                                    <input
                                        id="customer_full_name"
                                        name="customer_full_name"
                                        type="text"
                                        value="{{ old('customer_full_name') }}"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                    >
                                </div>

                                <div>
                                    <label for="customer_phone" class="mb-2 block text-sm font-medium text-slate-700">Customer Phone</label>
                                    <input
                                        id="customer_phone"
                                        name="customer_phone"
                                        type="text"
                                        value="{{ old('customer_phone') }}"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                    >
                                </div>
                            </div>

                            <div>
                                <label for="notes" class="mb-2 block text-sm font-medium text-slate-700">Notes</label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="4"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                >{{ old('notes') }}</textarea>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                                >
                                    Create Appointment
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </section>
    @endif

    <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-slate-900">
                Schedule for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Appointment groups for the selected date.
            </p>
        </div>

        <div class="overflow-x-auto">
            @if($appointmentGroups->count())
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Time</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Services</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach($appointmentGroups as $group)
                            @php
                                $servicesSummary = $group->items?->map(fn($item) => $item->service?->name)->filter()->unique()->implode(', ') ?: '-';
                                $staffSummary = $group->items?->map(fn($item) => $item->staff?->full_name)->filter()->unique()->implode(', ') ?: '-';
                                $currentStatus = is_object($group->status) ? $group->status->value : (string) $group->status;
                                $currentStatusLabel = is_object($group->status) && method_exists($group->status, 'label')
                                    ? $group->status->label()
                                    : ucfirst(str_replace('_', ' ', $currentStatus));
                            @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-4 text-sm font-medium text-slate-900">
                                    {{ optional($group->starts_at)->format('H:i') }} - {{ optional($group->ends_at)->format('H:i') }}
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700">
                                    <div>{{ $group->customer?->full_name ?? '-' }}</div>
                                    @if($group->customer?->phone)
                                        <div class="mt-1 text-xs text-slate-500">{{ $group->customer->phone }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-sm text-slate-700">{{ $servicesSummary }}</td>
                                <td class="px-5 py-4 text-sm text-slate-700">{{ $staffSummary }}</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium {{ $statusColors[$currentStatus] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                        {{ $currentStatusLabel }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <form method="POST" action="{{ route('app.appointments.status', $group) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select
                                            name="status"
                                            class="rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                        >
                                            @foreach($statusOptions as $status)
                                                @php
                                                    $statusValue = is_object($status) ? $status->value : (string) $status;
                                                    $statusLabel = is_object($status) && method_exists($status, 'label')
                                                        ? $status->label()
                                                        : ucfirst(str_replace('_', ' ', $statusValue));
                                                @endphp
                                                <option value="{{ $statusValue }}" @selected($statusValue === $currentStatus)>
                                                    {{ $statusLabel }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <button
                                            type="submit"
                                            class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                                        >
                                            Update
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                @if(method_exists($appointmentGroups, 'links'))
                    <div class="border-t border-slate-200 px-5 py-4">
                        {{ $appointmentGroups->links() }}
                    </div>
                @endif
            @else
                <div class="px-5 py-10 text-center">
                    <div class="text-sm font-semibold text-slate-700">No appointment groups found</div>
                    <p class="mt-1 text-sm text-slate-500">
                        Once bookings are created, they will appear here.
                    </p>
                </div>
            @endif
        </div>
    </section>
</div>

<script>
    (() => {
        const slotButtons = document.querySelectorAll('.slot-picker');
        const slotLabel = document.getElementById('selected-slot-label');
        const slotInput = document.getElementById('selected-slot-input');
        const comboInput = document.getElementById('selected-combination-input');
        const comboSelect = document.getElementById('selected_combination_select');

        const setCombinations = (combinations, preserved = '') => {
            comboSelect.innerHTML = '';

            if (!Array.isArray(combinations) || !combinations.length) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No valid staff combinations';
                comboSelect.appendChild(option);
                comboInput.value = '';
                return;
            }

            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Choose a staff combination';
            comboSelect.appendChild(placeholder);

            combinations.forEach((combo) => {
                const option = document.createElement('option');
                option.value = combo.payload;
                option.textContent = combo.label;
                if (preserved && preserved === combo.payload) {
                    option.selected = true;
                }
                comboSelect.appendChild(option);
            });

            comboInput.value = comboSelect.value || '';
        };

        comboSelect?.addEventListener('change', () => {
            comboInput.value = comboSelect.value || '';
        });

        slotButtons.forEach((button) => {
            button.addEventListener('click', () => {
                slotButtons.forEach((btn) => {
                    btn.classList.remove('border-slate-900', 'bg-slate-100');
                });

                button.classList.add('border-slate-900', 'bg-slate-100');

                const slot = button.dataset.slot || '';
                const combinations = JSON.parse(button.dataset.combinations || '[]');

                if (slotLabel) slotLabel.textContent = slot || '—';
                if (slotInput) slotInput.value = slot;

                setCombinations(combinations);
            });
        });

        const oldSlot = @json(old('slot'));
        const oldCombination = @json(old('selected_combination'));

        if (oldSlot) {
            const matchedButton = Array.from(slotButtons).find((button) => button.dataset.slot === oldSlot);
            if (matchedButton) {
                matchedButton.click();
                const combinations = JSON.parse(matchedButton.dataset.combinations || '[]');
                setCombinations(combinations, oldCombination || '');
            }
        }
    })();
</script>
@endsection

//doasolution