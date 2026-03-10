<x-internal-layout :title="'Appointments'" :subtitle="'Book, review and manage clinic appointments'">

    @php
        $filters = $filters ?? [
            'date' => now()->format('Y-m-d'),
            'service_ids' => [],
            'staff_id' => null,
            'status' => null,
        ];

        $selectedServiceIds = collect($filters['service_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $selectedDate = $filters['date'] ?? now()->format('Y-m-d');

        $appointmentGroups = $appointmentGroups ?? collect();
        $statusOptions = $statusOptions ?? [];
        $services = $services ?? collect();
        $availability = $availability ?? null;

        $statusColors = [
            'booked' => 'bg-amber-100 text-amber-800 border-amber-200',
            'scheduled' => 'bg-amber-100 text-amber-800 border-amber-200',
            'confirmed' => 'bg-sky-100 text-sky-800 border-sky-200',
            'checked_in' => 'bg-violet-100 text-violet-800 border-violet-200',
            'completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
            'no_show' => 'bg-slate-200 text-slate-700 border-slate-300',
        ];

        $slotOptions = [];
        if (!empty($availability['viable_slots'])) {
            foreach ($availability['viable_slots'] as $slotTime) {
                $slotData = $availability['slots'][$slotTime] ?? [];
                $combinations = $slotData['combinations'] ?? [];

                $slotOptions[] = [
                    'time' => $slotTime,
                    'combinations' => $combinations,
                    'first_payload' => $combinations[0]['payload'] ?? '',
                    'first_label' => $combinations[0]['label'] ?? '',
                    'count' => count($combinations),
                ];
            }
        }
    @endphp

    <style>
        .appointment-service-card {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 1rem;
            padding: 1rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            transition: all 0.18s ease;
        }

        .appointment-service-card:hover {
            border-color: #e7b7b0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .appointment-service-card.is-selected {
            border-color: #d6a39a;
            background: linear-gradient(180deg, #fff8f6 0%, #fff1ee 100%);
            box-shadow: 0 0 0 3px rgba(214, 163, 154, 0.20);
        }

        .appointment-service-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            border: 1px solid #e2e8f0;
            padding: 0.25rem 0.625rem;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            background: #ffffff;
            transition: all 0.18s ease;
        }

        .appointment-service-card.is-selected .appointment-service-badge {
            border-color: #d6a39a;
            color: #9a5c52;
            background: #ffffff;
        }

        .appointment-service-card.is-selected .appointment-service-title {
            color: #7c3f35;
        }

        .appointment-service-card.is-selected .appointment-service-muted {
            color: #9a5c52;
        }

        .slot-pill {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 9999px;
            min-height: 52px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: 600;
            color: #0f172a;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            transition: all 0.18s ease;
        }

        .slot-pill:hover {
            border-color: #e7b7b0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .slot-pill.is-selected {
            border-color: #d6a39a;
            background: linear-gradient(180deg, #fff8f6 0%, #fff1ee 100%);
            box-shadow: 0 0 0 3px rgba(214, 163, 154, 0.20);
            color: #7c3f35;
        }

        .slot-pill-sub {
            display: block;
            margin-top: 0.2rem;
            font-size: 11px;
            font-weight: 500;
            color: #64748b;
        }

        .slot-pill.is-selected .slot-pill-sub {
            color: #9a5c52;
        }
    </style>

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 shadow-sm">
            <div class="mb-1 font-semibold">Please fix the following:</div>
            <ul class="ml-5 list-disc space-y-1">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-7 space-y-6">

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">Check Availability</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Select one or more services and a date. Then choose a time bubble and complete one booking form below.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600">
                            Compact booking flow
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <form method="GET" action="{{ route('app.appointments.index') }}" class="space-y-6">
                        <div>
                            <label class="mb-3 block text-sm font-semibold text-slate-800">
                                Services
                            </label>

                            @if($services->count())
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    @foreach ($services as $service)
                                        @php
                                            $isSelected = in_array((string) $service->id, $selectedServiceIds, true);
                                        @endphp

                                        <label class="block cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="service_ids[]"
                                                value="{{ $service->id }}"
                                                class="appointment-service-checkbox sr-only"
                                                {{ $isSelected ? 'checked' : '' }}
                                            >

                                            <div class="appointment-service-card {{ $isSelected ? 'is-selected' : '' }}">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="appointment-service-title text-sm font-semibold text-slate-900">
                                                            {{ $service->name }}
                                                        </div>
                                                        @if(!empty($service->description))
                                                            <div class="mt-1 text-xs text-slate-500">
                                                                {{ $service->description }}
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="appointment-service-badge">
                                                        {{ (int) ($service->duration_minutes ?? 60) }} mins
                                                    </div>
                                                </div>

                                                <div class="mt-3 flex items-center justify-between">
                                                    <div class="appointment-service-muted text-xs text-slate-500">
                                                        Click to include
                                                    </div>

                                                    <div class="appointment-service-badge appointment-service-status">
                                                        {{ $isSelected ? 'Selected' : 'Available' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    No services available yet.
                                </div>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="date" class="mb-2 block text-sm font-semibold text-slate-800">
                                    Date
                                </label>
                                <input
                                    id="date"
                                    name="date"
                                    type="date"
                                    value="{{ $selectedDate }}"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                                    required
                                >
                            </div>

                            <div class="flex items-end gap-3">
                                <button
                                    type="submit"
                                    class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                                    style="background-color:#0f172a;color:#ffffff;border-color:#0f172a;"
                                >
                                    Check Availability
                                </button>

                                <a
                                    href="{{ route('app.appointments.index') }}"
                                    class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50"
                                >
                                    Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            @if (!empty($availability))
                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-5">
                        <h3 class="text-lg font-semibold text-slate-900">Eligibility & Availability</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Choose one time bubble. Then the booking form appears once below.
                        </p>
                    </div>

                    <div class="px-6 py-6 space-y-6">
                        @if(!empty($availability['services_without_eligible_staff']))
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                                No active eligible staff assigned for:
                                <span class="font-semibold">{{ implode(', ', $availability['services_without_eligible_staff']) }}</span>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            @foreach(($availability['selected_services'] ?? []) as $serviceSummary)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-sm font-semibold text-slate-900">
                                        {{ $serviceSummary['name'] ?? 'Service' }}
                                    </div>
                                    <div class="mt-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                                        Eligible staff
                                    </div>

                                    @if(empty($serviceSummary['eligible_staff']))
                                        <div class="mt-2 text-sm text-rose-600">
                                            No active staff assigned.
                                        </div>
                                    @else
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach($serviceSummary['eligible_staff'] as $staff)
                                                <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700">
                                                    {{ $staff['full_name'] }} ({{ $staff['role_key'] }})
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if(!empty($availability['fully_booked_message']))
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                {{ $availability['fully_booked_message'] }}
                            </div>
                        @endif

                        @if(count($slotOptions))
                            <div class="space-y-4">
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-800">Choose Time</h4>
                                    <p class="mt-1 text-xs text-slate-500">Click a bubble from 9 AM to 5 PM. One booking form appears below.</p>
                                </div>

                                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
                                    @foreach($slotOptions as $slot)
                                        <button
                                            type="button"
                                            class="slot-select-button text-left"
                                            data-slot-time="{{ $slot['time'] }}"
                                            data-first-payload="{{ $slot['first_payload'] }}"
                                            data-combinations='@json($slot['combinations'])'
                                        >
                                            <div class="slot-pill">
                                                <div>
                                                    <div>{{ $slot['time'] }}</div>
                                                    <span class="slot-pill-sub">
                                                        {{ $slot['count'] }} combo{{ $slot['count'] === 1 ? '' : 's' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>

                                <div
                                    id="selected-slot-card"
                                    class="hidden rounded-3xl border border-slate-200 bg-slate-50 p-5"
                                >
                                    <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <h5 class="text-base font-semibold text-slate-900">Create Appointment</h5>
                                            <p class="mt-1 text-sm text-slate-500">
                                                Selected time:
                                                <span id="selected-slot-time-label" class="font-semibold text-slate-800">—</span>
                                            </p>
                                        </div>

                                        <div class="rounded-2xl bg-white px-3 py-2 text-xs font-medium text-slate-600 border border-slate-200">
                                            One form only
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('app.appointments.store') }}" class="space-y-4">
                                        @csrf

                                        <input type="hidden" name="date" value="{{ $selectedDate }}">
                                        <input type="hidden" name="slot" id="selected-slot-input" value="">
                                        <input type="hidden" name="selected_combination" id="selected-combination-input" value="">

                                        @foreach($selectedServiceIds as $sid)
                                            <input type="hidden" name="service_ids[]" value="{{ $sid }}">
                                        @endforeach

                                        <div>
                                            <label for="selected_combination_select" class="mb-2 block text-sm font-semibold text-slate-800">
                                                Staff Combination
                                            </label>
                                            <select
                                                id="selected_combination_select"
                                                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                                                required
                                            >
                                            </select>
                                            <p class="mt-2 text-xs text-slate-500">
                                                If more than one valid combination exists for this slot, choose the one you want to use.
                                            </p>
                                        </div>

                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                            <div>
                                                <label for="customer_full_name" class="mb-2 block text-sm font-semibold text-slate-800">
                                                    Customer Name
                                                </label>
                                                <input
                                                    id="customer_full_name"
                                                    type="text"
                                                    name="customer_full_name"
                                                    value="{{ old('customer_full_name') }}"
                                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                                                    required
                                                >
                                            </div>

                                            <div>
                                                <label for="customer_phone" class="mb-2 block text-sm font-semibold text-slate-800">
                                                    Customer Phone
                                                </label>
                                                <input
                                                    id="customer_phone"
                                                    type="text"
                                                    name="customer_phone"
                                                    value="{{ old('customer_phone') }}"
                                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                                                    required
                                                >
                                            </div>
                                        </div>

                                        <div>
                                            <label for="notes" class="mb-2 block text-sm font-semibold text-slate-800">
                                                Notes
                                            </label>
                                            <textarea
                                                id="notes"
                                                name="notes"
                                                rows="3"
                                                class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                                            >{{ old('notes') }}</textarea>
                                        </div>

                                        <div class="flex justify-end">
                                            <button
                                                type="submit"
                                                class="inline-flex items-center justify-center rounded-2xl border border-slate-900 bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800"
                                                style="background-color:#0f172a;color:#ffffff;border-color:#0f172a;"
                                            >
                                                Create Appointment
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif

                        @if(!count($slotOptions) && empty($availability['services_without_eligible_staff']) && empty($availability['fully_booked_message']))
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                No available slots found for the selected date.
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="xl:col-span-5 space-y-6">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Schedule for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Appointment groups for the selected date.
                    </p>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse ($appointmentGroups as $group)
                        @php
                            $statusValue = is_object($group->status) && method_exists($group->status, 'value')
                                ? $group->status->value
                                : (string) $group->status;

                            $badgeClass = $statusColors[$statusValue] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                            $customerName = $group->customer?->full_name ?? 'Customer';
                            $customerPhone = $group->customer?->phone ?? '—';
                        @endphp

                        <div class="px-6 py-5">
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="text-sm font-semibold text-slate-900">
                                        {{ optional($group->starts_at)->format('h:i A') ?? '—' }}
                                    </div>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $badgeClass }}">
                                        {{ str_replace('_', ' ', ucfirst($statusValue)) }}
                                    </span>
                                </div>

                                <div>
                                    <div class="text-sm font-medium text-slate-800">{{ $customerName }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $customerPhone }}</div>
                                </div>

                                <div class="space-y-2">
                                    @foreach($group->items as $item)
                                        <div class="rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                            <span class="font-semibold">{{ $item->service?->name ?? 'Service' }}</span>
                                            —
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

                                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Update Status
                                    </label>

                                    <select
                                        name="status"
                                        onchange="this.form.submit()"
                                        class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-rose-300 focus:ring-2 focus:ring-rose-100"
                                    >
                                        @foreach ($statusOptions as $option)
                                            @php
                                                $optionValue = is_object($option) && method_exists($option, 'value') ? $option->value : (string) $option;
                                                $optionLabel = \Illuminate\Support\Str::headline(str_replace('_', ' ', $optionValue));
                                            @endphp
                                            <option value="{{ $optionValue }}" @selected($statusValue === $optionValue)>
                                                {{ $optionLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center">
                            <div class="text-sm font-medium text-slate-700">No appointment groups found</div>
                            <div class="mt-1 text-sm text-slate-500">
                                Once bookings are created, they will appear here.
                            </div>
                        </div>
                    @endforelse
                </div>

                @if(method_exists($appointmentGroups, 'links'))
                    <div class="border-t border-slate-200 px-6 py-4">
                        {{ $appointmentGroups->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const serviceCheckboxes = document.querySelectorAll('.appointment-service-checkbox');

            const refreshServiceCardState = (checkbox) => {
                const card = checkbox.closest('label')?.querySelector('.appointment-service-card');
                if (!card) return;

                card.classList.toggle('is-selected', checkbox.checked);

                const status = card.querySelector('.appointment-service-status');
                if (status) {
                    status.textContent = checkbox.checked ? 'Selected' : 'Available';
                }
            };

            serviceCheckboxes.forEach((checkbox) => {
                refreshServiceCardState(checkbox);

                checkbox.addEventListener('change', function () {
                    refreshServiceCardState(this);
                });
            });

            const slotButtons = document.querySelectorAll('.slot-select-button');
            const slotCard = document.getElementById('selected-slot-card');
            const slotTimeLabel = document.getElementById('selected-slot-time-label');
            const slotInput = document.getElementById('selected-slot-input');
            const comboInput = document.getElementById('selected-combination-input');
            const comboSelect = document.getElementById('selected_combination_select');

            const clearSlotSelection = () => {
                slotButtons.forEach((btn) => {
                    const pill = btn.querySelector('.slot-pill');
                    if (pill) pill.classList.remove('is-selected');
                });
            };

            const setCombinations = (combinations) => {
                if (!comboSelect) return;

                comboSelect.innerHTML = '';

                combinations.forEach((combo, index) => {
                    const option = document.createElement('option');
                    option.value = combo.payload || '';
                    option.textContent = combo.label || `Combination ${index + 1}`;
                    comboSelect.appendChild(option);
                });

                comboInput.value = comboSelect.value || '';
            };

            if (comboSelect) {
                comboSelect.addEventListener('change', function () {
                    comboInput.value = this.value || '';
                });
            }

            slotButtons.forEach((btn) => {
                btn.addEventListener('click', function () {
                    const time = this.dataset.slotTime || '';
                    let combinations = [];

                    try {
                        combinations = JSON.parse(this.dataset.combinations || '[]');
                    } catch (e) {
                        combinations = [];
                    }

                    clearSlotSelection();

                    const pill = this.querySelector('.slot-pill');
                    if (pill) pill.classList.add('is-selected');

                    if (slotCard) slotCard.classList.remove('hidden');
                    if (slotTimeLabel) slotTimeLabel.textContent = time || '—';
                    if (slotInput) slotInput.value = time || '';

                    setCombinations(combinations);

                    if (slotCard) {
                        slotCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        });
    </script>

</x-internal-layout>