<x-internal-layout :title="'Appointments'" :subtitle="'Schedule & Operations'">
@php
    $filters = $filters ?? ['date' => now()->format('Y-m-d'), 'service_ids' => [], 'slot' => null];
    $selectedServiceIds = collect($filters['service_ids'] ?? [])->map(fn ($id) => (string) $id)->values()->all();
    $selectedDate = $filters['date'] ?? now()->format('Y-m-d');
    $prefilledSlot = $filters['slot'] ?? null;
    $services = $services ?? collect();
    $availability = $availability ?? null;
    $appointmentGroups = $appointmentGroups ?? collect();
    $statusOptions = $statusOptions ?? [];
    $quickCreate = $quickCreate ?? ['prefilled_slot' => null, 'slot_is_available' => false, 'slot_combinations' => [], 'message' => null];
    $statusColors = [
        'booked' => 'bg-amber-100 text-amber-800 border-amber-200',
        'confirmed' => 'bg-sky-100 text-sky-800 border-sky-200',
        'checked_in' => 'bg-violet-100 text-violet-800 border-violet-200',
        'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
        'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
        'no_show' => 'bg-slate-100 text-slate-700 border-slate-200',
    ];
    $slotOptions = [];
    if (!empty($availability['viable_slots'])) {
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
@endphp

<style>
    .service-chip{border:1px solid #e2e8f0;background:#fff;border-radius:16px;padding:14px 16px;transition:all .18s ease;box-shadow:0 1px 2px rgba(15,23,42,.06)}
    .service-chip:hover{border-color:#cbd5e1;box-shadow:0 8px 24px rgba(15,23,42,.08)}
    .service-chip.is-selected{border-color:#0f172a;background:linear-gradient(180deg,#fff 0%,#eff6ff 100%);box-shadow:0 0 0 3px rgba(15,23,42,.08)}
    .slot-pill{border:1px solid #e2e8f0;background:linear-gradient(180deg,#fff 0%,#fbfdff 100%);border-radius:9999px;min-height:56px;padding:12px 16px;display:flex;align-items:center;justify-content:center;text-align:center;transition:all .18s ease;box-shadow:0 1px 2px rgba(15,23,42,.06)}
    .slot-pill:hover{border-color:#cbd5e1;box-shadow:0 8px 24px rgba(15,23,42,.08)}
    .slot-pill.is-selected{border-color:#0f172a;background:linear-gradient(180deg,#fff 0%,#eef4ff 100%);box-shadow:0 0 0 3px rgba(15,23,42,.08)}
</style>

@if (session('success'))
    <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm">
        <div class="mb-1 font-semibold">Please fix the following:</div>
        <ul class="ml-5 list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($quickCreate['message'])
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-sm">{{ $quickCreate['message'] }}</div>
@endif

<div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
    <div class="space-y-6 xl:col-span-7">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900">Check Availability</h2>
                        <p class="mt-1 text-sm text-slate-500">Choose services, keep the date in focus, then select a viable time bubble to create one booking fast.</p>
                    </div>
                    @if ($prefilledSlot)
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-800">Calendar slot selected: {{ $prefilledSlot }}</div>
                    @endif
                </div>
            </div>

            <div class="px-6 py-6">
                <form method="GET" action="{{ route('app.appointments.index') }}" class="space-y-6">
                    <input type="hidden" name="slot" value="{{ $prefilledSlot }}">
                    <div>
                        <label class="mb-3 block text-sm font-semibold text-slate-800">Services</label>
                        @if ($services->count())
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                @foreach ($services as $service)
                                    @php $isSelected = in_array((string) $service->id, $selectedServiceIds, true); @endphp
                                    <label class="block cursor-pointer service-chip {{ $isSelected ? 'is-selected' : '' }}">
                                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="service-checkbox sr-only" {{ $isSelected ? 'checked' : '' }}>
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-sm font-semibold text-slate-900">{{ $service->name }}</div>
                                                @if (!empty($service->description))
                                                    <div class="mt-1 text-xs text-slate-500">{{ $service->description }}</div>
                                                @endif
                                            </div>
                                            <div class="rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ (int) ($service->duration_minutes ?? 60) }} mins</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">No services available yet.</div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="date" class="mb-2 block text-sm font-semibold text-slate-800">Date</label>
                            <input id="date" name="date" type="date" value="{{ $selectedDate }}" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200" required>
                        </div>
                        <div class="flex items-end gap-3">
                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">Check Availability</button>
                            <a href="{{ route('app.appointments.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if (!empty($availability))
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Eligibility & Availability</h3>
                    <p class="mt-1 text-sm text-slate-500">Pick a viable time, review the valid service-staff combination, then complete one booking form.</p>
                </div>
                <div class="space-y-6 px-6 py-6">
                    @if(!empty($availability['services_without_eligible_staff']))
                        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">No active eligible staff assigned for: <span class="font-semibold">{{ implode(', ', $availability['services_without_eligible_staff']) }}</span></div>
                    @endif

                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach(($availability['selected_services'] ?? []) as $serviceSummary)
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $serviceSummary['name'] ?? 'Service' }}</div>
                                <div class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">Eligible staff</div>
                                @if(empty($serviceSummary['eligible_staff']))
                                    <div class="mt-2 text-sm text-rose-600">No active staff assigned.</div>
                                @else
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($serviceSummary['eligible_staff'] as $staff)
                                            <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700">{{ $staff['full_name'] }} ({{ $staff['role_key'] }})</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if(!empty($availability['fully_booked_message']))
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $availability['fully_booked_message'] }}</div>
                    @endif

                    @if(count($slotOptions))
                        <div class="space-y-4">
                            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-800">Choose Time</h4>
                                    <p class="mt-1 text-xs text-slate-500">{{ $prefilledSlot ? 'The calendar-selected slot is highlighted below when available.' : 'Select a slot to open the booking form.' }}</p>
                                </div>
                                @if ($selectedSlotRow)
                                    <div class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">Selected slot duration: {{ $selectedSlotRow['duration_minutes'] ?? ($availability['duration_minutes'] ?? 0) }} mins</div>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                                @foreach($slotOptions as $slot)
                                    <button type="button" class="slot-select-button text-left" data-slot-time="{{ $slot['time'] }}" data-combinations='@json($slot['combinations'])'>
                                        <div class="slot-pill {{ $slot['is_prefilled'] ? 'is-selected' : '' }}">
                                            <div>
                                                <div class="font-semibold text-slate-900">{{ $slot['time'] }}</div>
                                                <div class="text-[11px] text-slate-500">{{ $slot['count'] }} combo{{ $slot['count'] === 1 ? '' : 's' }}</div>
                                            </div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>

                            <div id="selected-slot-card" class="hidden rounded-3xl border border-slate-200 bg-slate-50 p-5">
                                <div class="mb-4">
                                    <h5 class="text-base font-semibold text-slate-900">Create Appointment</h5>
                                    <p class="mt-1 text-sm text-slate-500">Selected time: <span id="selected-slot-time-label" class="font-semibold text-slate-800">-</span></p>
                                </div>

                                <form method="POST" action="{{ route('app.appointments.store') }}" class="space-y-4">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ $selectedDate }}">
                                    <input type="hidden" name="slot" id="selected-slot-input" value="">
                                    <input type="hidden" name="selected_combination" id="selected-combination-input" value="">
                                    @foreach($selectedServiceIds as $serviceId)
                                        <input type="hidden" name="service_ids[]" value="{{ $serviceId }}">
                                    @endforeach

                                    <div>
                                        <label for="selected_combination_select" class="mb-2 block text-sm font-semibold text-slate-800">Staff Combination</label>
                                        <select id="selected_combination_select" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200" required></select>
                                    </div>

                                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <label for="customer_full_name" class="mb-2 block text-sm font-semibold text-slate-800">Customer Name</label>
                                            <input id="customer_full_name" type="text" name="customer_full_name" value="{{ old('customer_full_name') }}" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200" required>
                                        </div>
                                        <div>
                                            <label for="customer_phone" class="mb-2 block text-sm font-semibold text-slate-800">Customer Phone</label>
                                            <input id="customer_phone" type="text" name="customer_phone" value="{{ old('customer_phone') }}" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200" required>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="notes" class="mb-2 block text-sm font-semibold text-slate-800">Notes</label>
                                        <textarea id="notes" name="notes" rows="3" class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200">{{ old('notes') }}</textarea>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">Create Appointment</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="space-y-6 xl:col-span-5">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-5">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Schedule for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Appointment groups already booked for the selected day.</p>
                    </div>
                    <a href="{{ route('app.calendar', ['date' => $selectedDate]) }}" class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">Open Calendar</a>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse ($appointmentGroups as $group)
                    @php
                        $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');
                        $badgeClass = $statusColors[$statusValue] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                        $statusLabel = $group->status instanceof \App\Enums\AppointmentStatus ? $group->status->label() : \Illuminate\Support\Str::headline(str_replace('_', ' ', $statusValue));
                    @endphp
                    <div class="px-6 py-5">
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="text-sm font-semibold text-slate-900">{{ optional($group->starts_at)->format('h:i A') ?? '-' }}</div>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $badgeClass }}">{{ $statusLabel }}</span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-slate-800">{{ $group->customer?->full_name ?? 'Customer' }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $group->customer?->phone ?? '-' }}</div>
                            </div>
                            <div class="space-y-2">
                                @foreach($group->items as $item)
                                    <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                        <span class="font-semibold">{{ $item->service?->name ?? 'Service' }}</span>
                                        -
                                        {{ $item->staff?->full_name ?? 'Unassigned' }}
                                        @if($item->staff?->role_key)
                                            ({{ $item->staff->role_key }})
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <form method="POST" action="{{ route('app.appointments.status', $group) }}">
                                @csrf
                                @method('PATCH')
                                <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Update Status</label>
                                <select name="status" onchange="this.form.submit()" class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                                    @foreach ($statusOptions as $option)
                                        @php
                                            $optionValue = $option instanceof \BackedEnum ? $option->value : (is_string($option) ? $option : '');
                                            $optionLabel = $option instanceof \App\Enums\AppointmentStatus ? $option->label() : \Illuminate\Support\Str::headline(str_replace('_', ' ', $optionValue));
                                        @endphp
                                        <option value="{{ $optionValue }}" @selected($statusValue === $optionValue)>{{ $optionLabel }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-10 text-center">
                        <div class="text-sm font-medium text-slate-700">No appointment groups found</div>
                        <div class="mt-1 text-sm text-slate-500">Once bookings are created, they will appear here.</div>
                    </div>
                @endforelse
            </div>

            @if(method_exists($appointmentGroups, 'links'))
                <div class="border-t border-slate-200 px-6 py-4">{{ $appointmentGroups->links() }}</div>
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
    const slotButtons = document.querySelectorAll('.slot-select-button');
    const slotCard = document.getElementById('selected-slot-card');
    const slotTimeLabel = document.getElementById('selected-slot-time-label');
    const slotInput = document.getElementById('selected-slot-input');
    const comboInput = document.getElementById('selected-combination-input');
    const comboSelect = document.getElementById('selected_combination_select');
    const prefilledSlot = @json($prefilledSlot);
    const slotAvailable = @json($quickCreate['slot_is_available']);

    serviceCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', function () {
            this.closest('label')?.classList.toggle('is-selected', this.checked);
        });
    });

    function setCombinations(combinations) {
        if (!comboSelect) return;
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
        slotButtons.forEach((button) => button.querySelector('.slot-pill')?.classList.remove('is-selected'));
        triggerButton?.querySelector('.slot-pill')?.classList.add('is-selected');
        slotTimeLabel.textContent = time || '-';
        slotInput.value = time || '';
        setCombinations(combinations || []);
        slotCard.classList.remove('hidden');
        slotCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
});
</script>

</x-internal-layout>
