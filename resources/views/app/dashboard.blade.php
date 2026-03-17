@php
    $selectedDate = request('date', $date ?? now()->toDateString());
    $selectedStaffId = request('staff_id');
@endphp

<x-layouts.internal
    title="Dashboard"
    subtitle="Premium internal overview for appointments, clinic load, and staff operations."
>
    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Overview controls</div>
                    <p class="mt-1 text-sm text-slate-500">
                        Filter the dashboard by date and staff, then jump directly into the relevant operational pages.
                    </p>
                </div>
            </div>

            <form method="GET" action="{{ route('app.dashboard') }}" class="mt-5 grid gap-4 lg:grid-cols-[220px_260px_auto] lg:items-end">
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
                        @foreach ($staffList as $s)
                            <option value="{{ $s->id }}" @selected((string) $selectedStaffId === (string) $s->id)>
                                {{ $s->full_name }} ({{ $s->role_key }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-3">
                    <button
                        type="submit"
                        class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="{{ route('app.dashboard') }}"
                        class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-{{ 1 + count($statusCases) }}">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Total Appointments</div>
                <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $kpi['total'] ?? 0 }}</div>
                <div class="mt-1 text-sm text-slate-500">For selected date</div>
            </div>

            @foreach ($statusCases as $st)
                @php
                    $statusKey = is_object($st) ? $st->value : (string) $st;
                    $statusLabel = method_exists($st, 'label')
                        ? $st->label()
                        : ucfirst(str_replace('_', ' ', $statusKey));
                @endphp

                <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">{{ $statusLabel }}</div>
                    <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $kpi['by_status'][$statusKey] ?? 0 }}</div>
                    <div class="mt-1 text-sm text-slate-500">Current filter window</div>
                </div>
            @endforeach
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Schedule</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Appointment groups for {{ $selectedDate }}.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <a
                            href="{{ route('app.appointments.index', ['date' => $selectedDate, 'staff_id' => $selectedStaffId]) }}"
                            class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Manage appointments
                        </a>

                        <a
                            href="{{ route('app.calendar', ['week' => \Illuminate\Support\Carbon::parse($selectedDate)->startOfWeek(\Carbon\Carbon::TUESDAY)->toDateString(), 'staff_id' => $selectedStaffId]) }}"
                            class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            Open calendar
                        </a>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @if ($appointments->count())
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Services</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($appointments as $g)
                                @php
                                    $servicesSummary = $g->items?->map(fn($i) => $i->service?->name)->filter()->unique()->implode(', ') ?: '-';
                                    $staffSummary = $g->items?->map(fn($i) => $i->staff?->full_name)->filter()->unique()->implode(', ') ?: '-';
                                    $currentStatus = is_object($g->status) ? $g->status->value : (string) $g->status;
                                    $currentStatusLabel = is_object($g->status) && method_exists($g->status, 'label')
                                        ? $g->status->label()
                                        : ucfirst(str_replace('_', ' ', $currentStatus));
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-4 text-sm font-medium text-slate-900">
                                        {{ optional($g->starts_at)->format('H:i') ?: '-' }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-700">
                                        <div>{{ $g->customer?->full_name ?? '-' }}</div>
                                        @if ($g->customer?->phone)
                                            <div class="mt-1 text-xs text-slate-500">{{ $g->customer->phone }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-700">{{ $servicesSummary }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-700">{{ $staffSummary }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-700">{{ $currentStatusLabel }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="px-5 py-10 text-center">
                        <div class="text-sm font-semibold text-slate-700">No appointments scheduled</div>
                        <p class="mt-1 text-sm text-slate-500">
                            No appointments found for the selected filters.
                        </p>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-layouts.internal>