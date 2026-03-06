<x-app-layout>
    <div class="min-h-screen bg-slate-50">
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <div class="mb-2 font-semibold">Fix the following:</div>
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Appointments</h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Phase 1 uses 1-hour concurrent booking windows from 09:00 to 17:00.
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        Select one or more services. Only slots with valid staff combinations will be shown.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <a href="{{ route('app.dashboard') }}"
                       class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition">
                        ← Back to Dashboard
                    </a>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-12">
                <div class="xl:col-span-5">
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-semibold text-slate-900">Create appointment</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Choose date and services, then pick a valid staff combination.
                            </p>
                        </div>

                        <div class="px-6 py-6">
                            <form method="GET" action="{{ route('app.appointments.index') }}" class="space-y-5">
                                <div>
                                    <label for="date" class="mb-2 block text-sm font-medium text-slate-700">Date</label>
                                    <input
                                        id="date"
                                        name="date"
                                        type="date"
                                        value="{{ $filters['date'] ?? now()->toDateString() }}"
                                        class="block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                    >
                                </div>

                                <div>
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <label class="block text-sm font-medium text-slate-700">Services</label>
                                        <span class="text-xs text-slate-500">Select all services needed in the same 1-hour slot</span>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @foreach($services as $svc)
                                            <label class="cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    name="service_ids[]"
                                                    value="{{ $svc->id }}"
                                                    class="peer sr-only"
                                                    @checked(in_array($svc->id, $filters['service_ids'] ?? []))
                                                >
                                                <div class="rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white hover:border-slate-400">
                                                    {{ $svc->name }}
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-3">
                                    <button
                                        type="submit"
                                        class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 transition"
                                    >
                                        Check availability
                                    </button>

                                    <a
                                        href="{{ route('app.appointments.index', ['date' => $filters['date'] ?? now()->toDateString()]) }}"
                                        class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition"
                                    >
                                        Clear
                                    </a>
                                </div>
                            </form>

                            <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="mb-4">
                                    <h3 class="text-sm font-semibold text-slate-900">Eligibility & availability</h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Slots only appear when every selected service can be covered by a different available staff member.
                                    </p>
                                </div>

                                @if(!empty($availability))
                                    @if(!empty($availability['services_without_eligible_staff']))
                                        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                                            No active eligible staff assigned for:
                                            <span class="font-semibold">{{ implode(', ', $availability['services_without_eligible_staff']) }}</span>
                                        </div>
                                    @endif

                                    <div class="mb-5 space-y-3">
                                        @foreach($availability['selected_services'] as $serviceSummary)
                                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                                <div class="text-sm font-semibold text-slate-900">{{ $serviceSummary['name'] }}</div>
                                                <div class="mt-1 text-xs text-slate-500">Eligible staff</div>

                                                @if(empty($serviceSummary['eligible_staff']))
                                                    <div class="mt-2 text-sm text-rose-600">No active staff assigned.</div>
                                                @else
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        @foreach($serviceSummary['eligible_staff'] as $staff)
                                                            <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                                                                {{ $staff['full_name'] }} ({{ $staff['role_key'] }})
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    @if(!empty($availability['fully_booked_message']))
                                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                            {{ $availability['fully_booked_message'] }}
                                        </div>
                                    @endif

                                    @if(!empty($availability['viable_slots']))
                                        <form method="POST" action="{{ route('app.appointments.store') }}" class="space-y-5">
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

                                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                                    @foreach($availability['viable_slots'] as $slot)
                                                        <label class="cursor-pointer">
                                                            <input
                                                                type="radio"
                                                                name="slot"
                                                                value="{{ $slot }}"
                                                                class="peer slot-radio sr-only"
                                                                @checked(old('slot') === $slot)
                                                            >
                                                            <div class="flex h-14 items-center justify-center rounded-2xl border border-slate-300 bg-white text-sm font-semibold text-slate-700 transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white hover:border-slate-400">
                                                                {{ $slot }}
                                                            </div>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>

                                            <div>
                                                <label for="selected_combination" class="mb-2 block text-sm font-medium text-slate-700">
                                                    Staff combination
                                                </label>
                                                <select
                                                    id="selected_combination"
                                                    name="selected_combination"
                                                    class="block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                                    required
                                                >
                                                    <option value="">Select a slot first</option>
                                                </select>
                                                <p id="combination_help" class="mt-2 text-xs text-slate-500">
                                                    For VIP/VVIP cases, front desk can choose the preferred valid combination.
                                                </p>
                                            </div>

                                            <div class="grid gap-5">
                                                <div>
                                                    <label for="customer_full_name" class="mb-2 block text-sm font-medium text-slate-700">Customer name</label>
                                                    <input
                                                        id="customer_full_name"
                                                        name="customer_full_name"
                                                        type="text"
                                                        value="{{ old('customer_full_name') }}"
                                                        class="block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                                        required
                                                    >
                                                </div>

                                                <div>
                                                    <label for="customer_phone" class="mb-2 block text-sm font-medium text-slate-700">Customer phone</label>
                                                    <input
                                                        id="customer_phone"
                                                        name="customer_phone"
                                                        type="text"
                                                        value="{{ old('customer_phone') }}"
                                                        class="block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                                        required
                                                    >
                                                </div>

                                                <div>
                                                    <label for="notes" class="mb-2 block text-sm font-medium text-slate-700">Notes (optional)</label>
                                                    <textarea
                                                        id="notes"
                                                        name="notes"
                                                        rows="4"
                                                        class="block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                                    >{{ old('notes') }}</textarea>
                                                </div>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
                                                All selected services will be booked in the same 1-hour slot. The selected staff combination must remain valid at booking time.
                                            </div>

                                            <div class="flex justify-end">
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-slate-800 transition"
                                                >
                                                    Book Appointment
                                                </button>
                                            </div>
                                        </form>
                                    @endif
                                @else
                                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">
                                        Select a date and one or more services, then click <span class="font-semibold text-slate-700">Check availability</span>.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-7">
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-6 py-5">
                            <h2 class="text-lg font-semibold text-slate-900">Daily schedule</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Showing {{ $filters['date'] ?? now()->toDateString() }}
                            </p>
                        </div>

                        <div class="px-6 py-6">
                            <form method="GET" action="{{ route('app.appointments.index') }}" class="mb-6 grid gap-4 lg:grid-cols-4">
                                <div>
                                    <label for="filter_date" class="mb-2 block text-sm font-medium text-slate-700">Date</label>
                                    <input
                                        id="filter_date"
                                        name="date"
                                        type="date"
                                        value="{{ $filters['date'] ?? now()->toDateString() }}"
                                        class="block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                    >
                                </div>

                                <div>
                                    <label for="staff_id" class="mb-2 block text-sm font-medium text-slate-700">Staff</label>
                                    <select
                                        id="staff_id"
                                        name="staff_id"
                                        class="block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                    >
                                        <option value="">All staff</option>
                                        @foreach($staffList as $s)
                                            <option value="{{ $s->id }}" @selected(($filters['staff_id'] ?? null) == $s->id)>
                                                {{ $s->full_name }} ({{ $s->role_key }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="status" class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                                    <select
                                        id="status"
                                        name="status"
                                        class="block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                    >
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
                                    <button
                                        type="submit"
                                        class="inline-flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 transition"
                                    >
                                        Apply Filters
                                    </button>
                                </div>
                            </form>

                            <div class="overflow-x-auto rounded-2xl border border-slate-200">
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
                                                    <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700">
                                                        {{ $currentStatusLabel }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-4">
                                                    <form method="POST" action="{{ route('app.appointments.status', $g) }}" class="flex flex-col gap-2 sm:flex-row">
                                                        @csrf
                                                        @method('PATCH')

                                                        <select
                                                            name="status"
                                                            class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500"
                                                        >
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

                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 transition"
                                                        >
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
    </div>

    @if(!empty($availability['slots']))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const slotDetails = @json($availability['slots']);
                const combinationSelect = document.getElementById('selected_combination');
                const helpText = document.getElementById('combination_help');
                const radios = document.querySelectorAll('.slot-radio');

                function updateCombinationOptions() {
                    if (!combinationSelect) return;

                    const selectedSlot = document.querySelector('.slot-radio:checked');

                    combinationSelect.innerHTML = '';

                    if (!selectedSlot) {
                        combinationSelect.innerHTML = '<option value="">Select a slot first</option>';
                        helpText.textContent = 'For VIP/VVIP cases, front desk can choose the preferred valid combination.';
                        return;
                    }

                    const slotKey = selectedSlot.value;
                    const combinations = (slotDetails[slotKey] && slotDetails[slotKey].combinations) ? slotDetails[slotKey].combinations : [];

                    if (!combinations.length) {
                        combinationSelect.innerHTML = '<option value="">No valid combinations for this slot</option>';
                        helpText.textContent = 'Please choose another slot.';
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

                    helpText.textContent = combinations.length + ' valid combination(s) available for ' + slotKey + '.';
                }

                radios.forEach(function (radio) {
                    radio.addEventListener('change', updateCombinationOptions);
                });

                updateCombinationOptions();
            });
        </script>
    @endif
</x-app-layout>