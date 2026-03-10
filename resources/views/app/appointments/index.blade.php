<x-internal-layout :title="'Appointments'" :subtitle="'Smart booking with service-to-staff matching'">
    @php
        $selectedIds = collect($filters['service_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
    @endphp

    @if (session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <div class="mb-2 font-semibold">Fix the following:</div>
            <ul class="ml-5 list-disc space-y-1">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-5 space-y-6">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Create appointment</div>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">Find valid slots</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Choose one or more services. The system only shows times where every selected service has an eligible and available staff member.
                    </p>
                </div>

                <form method="GET" action="{{ url('/app/appointments') }}" class="space-y-6 px-6 py-6">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Date</label>
                        <input
                            type="date"
                            name="date"
                            value="{{ $filters['date'] ?? now()->toDateString() }}"
                            class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none ring-0 transition focus:border-slate-900"
                        >
                    </div>

                    <div>
                        <div class="mb-2 block text-sm font-medium text-slate-700">Services</div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($services as $svc)
                                @php
                                    $checked = in_array((string) $svc->id, $selectedIds, true);
                                @endphp
                                <label class="flex cursor-pointer items-start gap-3 rounded-2xl border {{ $checked ? 'border-slate-900 bg-slate-50' : 'border-slate-200 bg-white' }} px-4 py-3 transition hover:border-slate-300">
                                    <input
                                        type="checkbox"
                                        name="service_ids[]"
                                        value="{{ $svc->id }}"
                                        {{ $checked ? 'checked' : '' }}
                                        class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                                    >
                                    <span class="block min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900">{{ $svc->name }}</span>
                                        <span class="mt-1 block text-xs text-slate-500">
                                            {{ (int) ($svc->duration_minutes ?: 60) }} min
                                            @if(!is_null($svc->price))
                                                · RM {{ number_format($svc->price / 100, 2) }}
                                            @endif
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                            Check availability
                        </button>
                        <a href="{{ url('/app/appointments') }}" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                            Clear
                        </a>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <h3 class="text-lg font-semibold text-slate-900">Selection summary</h3>
                    <p class="mt-1 text-sm text-slate-600">Quick check before you book.</p>
                </div>

                <div class="space-y-4 px-6 py-6">
                    @if (empty($availability))
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-sm text-slate-600">
                            Select a date and at least one service to see eligible staff and valid appointment slots.
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-3">
                            @foreach ($availability['selected_services'] as $serviceSummary)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold text-slate-900">{{ $serviceSummary['name'] }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $serviceSummary['duration_minutes'] }} min</div>
                                        </div>
                                        <div class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">
                                            {{ count($serviceSummary['eligible_staff']) }} eligible
                                        </div>
                                    </div>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @forelse ($serviceSummary['eligible_staff'] as $staff)
                                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700">
                                                {{ $staff['full_name'] }} · {{ $staff['role_key'] }}
                                            </span>
                                        @empty
                                            <span class="text-xs font-medium text-rose-700">No active staff assigned to this service.</span>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Booking window</div>
                                <div class="mt-2 text-xl font-semibold text-slate-900">{{ $availability['duration_minutes'] ?? 60 }} min</div>
                                <div class="mt-1 text-xs text-slate-500">System uses the longest selected service duration.</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Available slots</div>
                                <div class="mt-2 text-xl font-semibold text-slate-900">{{ count($availability['viable_slots'] ?? []) }}</div>
                                <div class="mt-1 text-xs text-slate-500">30-minute step intervals.</div>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Rule</div>
                                <div class="mt-2 text-sm font-semibold text-slate-900">1 staff per selected service</div>
                                <div class="mt-1 text-xs text-slate-500">No double-booking in the same time window.</div>
                            </div>
                        </div>

                        @if (!empty($availability['services_without_eligible_staff']))
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                                No active eligible staff assigned for: {{ implode(', ', $availability['services_without_eligible_staff']) }}
                            </div>
                        @endif

                        @if (!empty($availability['fully_booked_message']))
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-900">
                                {{ $availability['fully_booked_message'] }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="xl:col-span-7 space-y-6">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Eligibility & availability</div>
                            <h2 class="mt-2 text-2xl font-semibold text-slate-900">Book selected slot</h2>
                            <p class="mt-2 text-sm text-slate-600">
                                The slot list only shows combinations that satisfy your service-to-staff rules.
                            </p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            Date: <span class="font-semibold text-slate-900">{{ $filters['date'] ?? now()->toDateString() }}</span>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6">
                    @if (!empty($availability) && !empty($availability['viable_slots']))
                        <form method="POST" action="{{ url('/app/appointments') }}" class="space-y-6">
                            @csrf

                            <input type="hidden" name="date" value="{{ $filters['date'] ?? now()->toDateString() }}">
                            @foreach (($filters['service_ids'] ?? []) as $sid)
                                <input type="hidden" name="service_ids[]" value="{{ $sid }}">
                            @endforeach

                            <div>
                                <label class="mb-3 block text-sm font-medium text-slate-700">Choose slot</label>
                                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                                    @foreach ($availability['viable_slots'] as $slot)
                                        @php
                                            $slotMeta = $availability['slots'][$slot] ?? null;
                                        @endphp
                                        <label class="cursor-pointer">
                                            <input
                                                type="radio"
                                                name="slot"
                                                value="{{ $slot }}"
                                                class="peer sr-only"
                                                {{ old('slot') === $slot ? 'checked' : '' }}
                                            >
                                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-center transition peer-checked:border-slate-900 peer-checked:bg-slate-900 peer-checked:text-white hover:border-slate-400">
                                                <div class="text-sm font-semibold">{{ $slot }}</div>
                                                <div class="mt-1 text-xs opacity-80">
                                                    to {{ $slotMeta['end'] ?? '' }}
                                                </div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <label class="mb-3 block text-sm font-medium text-slate-700">Choose staff combination</label>
                                <select
                                    name="selected_combination"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900"
                                    required
                                >
                                    <option value="">Select a valid combination</option>
                                    @foreach ($availability['viable_slots'] as $slot)
                                        @php
                                            $combinations = $availability['slots'][$slot]['combinations'] ?? [];
                                        @endphp
                                        @foreach ($combinations as $combo)
                                            <option
                                                value="{{ $combo['payload'] }}"
                                                {{ old('selected_combination') === $combo['payload'] ? 'selected' : '' }}
                                            >
                                                {{ $slot }} — {{ $combo['label'] }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                                <p class="mt-2 text-xs text-slate-500">
                                    Safer than free-picking staff: only valid combinations are listed.
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Customer name</label>
                                    <input
                                        type="text"
                                        name="customer_full_name"
                                        value="{{ old('customer_full_name') }}"
                                        class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900"
                                        required
                                    >
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium text-slate-700">Customer phone</label>
                                    <input
                                        type="text"
                                        name="customer_phone"
                                        value="{{ old('customer_phone') }}"
                                        class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900"
                                        required
                                    >
                                </div>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-slate-700">Notes</label>
                                <textarea
                                    name="notes"
                                    rows="4"
                                    class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900"
                                >{{ old('notes') }}</textarea>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
                                All selected services are booked into the same time window. This version enforces service eligibility and staff availability, but it does not yet chain services sequentially.
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                                    Book appointment
                                </button>
                            </div>
                        </form>
                    @elseif (!empty($availability))
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-sm text-slate-600">
                            No valid slot is available for the current service selection on this date.
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-6 text-sm text-slate-600">
                            Select date and services first, then click <span class="font-semibold">Check availability</span>.
                        </div>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900">Daily schedule</h3>
                            <p class="mt-1 text-sm text-slate-600">Filter today’s booked appointments by date, staff, or status.</p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            Showing {{ $filters['date'] ?? now()->toDateString() }}
                        </div>
                    </div>
                </div>

                <div class="border-b border-slate-200 px-6 py-5">
                    <form method="GET" action="{{ url('/app/appointments') }}" class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Date</label>
                            <input
                                type="date"
                                name="date"
                                value="{{ $filters['date'] ?? now()->toDateString() }}"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900"
                            >
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Staff</label>
                            <select
                                name="staff_id"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900"
                            >
                                <option value="">All staff</option>
                                @foreach ($staffList as $s)
                                    <option value="{{ $s->id }}" {{ ($filters['staff_id'] ?? '') == $s->id ? 'selected' : '' }}>
                                        {{ $s->full_name }} ({{ $s->role_key }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                            <select
                                name="status"
                                class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-900"
                            >
                                <option value="">All statuses</option>
                                @foreach ($statusOptions as $statusOption)
                                    @php
                                        $statusValue = method_exists($statusOption, 'value') ? $statusOption->value : $statusOption->name;
                                    @endphp
                                    <option value="{{ $statusValue }}" {{ ($filters['status'] ?? '') === $statusValue ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $statusValue)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-3">
                            <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800">
                                Apply
                            </button>
                            <a href="{{ url('/app/appointments?date=' . ($filters['date'] ?? now()->toDateString())) }}" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Time</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Customer</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Services</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Assigned staff</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Status</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse ($appointmentGroups as $group)
                                <tr class="align-top">
                                    <td class="px-6 py-5 text-sm text-slate-700">
                                        <div class="font-semibold text-slate-900">{{ optional($group->starts_at)->format('H:i') }}</div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            to {{ optional($group->ends_at)->format('H:i') }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-700">
                                        <div class="font-semibold text-slate-900">{{ $group->customer->full_name ?? '-' }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $group->customer->phone ?? '-' }}</div>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-700">
                                        <div class="flex flex-wrap gap-2">
                                            @forelse ($group->items as $item)
                                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700">
                                                    {{ $item->service->name ?? 'Service' }}
                                                </span>
                                            @empty
                                                <span class="text-slate-400">-</span>
                                            @endforelse
                                        </div>
                                        @if (!empty($group->notes))
                                            <div class="mt-3 text-xs text-slate-500">{{ $group->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-700">
                                        <div class="space-y-2">
                                            @forelse ($group->items as $item)
                                                <div class="rounded-2xl border border-slate-200 bg-white px-3 py-2">
                                                    <div class="text-xs font-semibold text-slate-900">{{ $item->staff->full_name ?? 'Unassigned' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        {{ $item->required_role ?: ($item->staff->role_key ?? '-') }}
                                                    </div>
                                                </div>
                                            @empty
                                                <span class="text-slate-400">-</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-700">
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                            {{ ucfirst(str_replace('_', ' ', (string) $group->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-sm text-slate-700">
                                        <form method="POST" action="{{ url('/app/appointments/' . $group->id . '/status') }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <select
                                                name="status"
                                                class="w-full rounded-2xl border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                                            >
                                                @foreach ($statusOptions as $statusOption)
                                                    @php
                                                        $statusValue = method_exists($statusOption, 'value') ? $statusOption->value : $statusOption->name;
                                                    @endphp
                                                    <option value="{{ $statusValue }}" {{ (string) $group->status === $statusValue ? 'selected' : '' }}>
                                                        {{ ucfirst(str_replace('_', ' ', $statusValue)) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900">
                                                Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">
                                        No appointments found for the selected filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (method_exists($appointmentGroups, 'links'))
                    <div class="border-t border-slate-200 px-6 py-5">
                        {{ $appointmentGroups->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-internal-layout>
