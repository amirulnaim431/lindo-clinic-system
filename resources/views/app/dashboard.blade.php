<x-layouts.internal
    title="Dashboard"
    subtitle="Premium internal overview for appointments, clinic load, and staff operations."
>
    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Overview controls</div>
                    <p class="mt-1 text-sm text-slate-500">
                        Filter the dashboard by date and staff, then jump directly into the relevant operational pages.
                    </p>
                </div>
            </div>

            <form method="GET" class="mt-5 grid gap-4 lg:grid-cols-[220px_240px_auto] lg:items-end">
                <div>
                    <label for="date" class="mb-2 block text-sm font-medium text-slate-700">Date</label>
                    <input
                        id="date"
                        name="date"
                        type="date"
                        value="{{ request('date', isset($selectedDate) ? \Illuminate\Support\Carbon::parse($selectedDate)->format('Y-m-d') : now()->format('Y-m-d')) }}"
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
                        @foreach(($staffOptions ?? []) as $staff)
                            <option value="{{ $staff->id }}" @selected((string) request('staff_id', $selectedStaffId ?? '') === (string) $staff->id)>
                                {{ $staff->full_name }}
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
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Total Appointments</div>
                <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $todayAppointments ?? 0 }}</div>
                <div class="mt-1 text-sm text-slate-500">For selected date</div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Total Staff</div>
                <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $staffCount ?? 0 }}</div>
                <div class="mt-1 text-sm text-slate-500">Active in system</div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Upcoming</div>
                <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $upcomingAppointments ?? 0 }}</div>
                <div class="mt-1 text-sm text-slate-500">Upcoming appointments</div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Completed Today</div>
                <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $completedToday ?? 0 }}</div>
                <div class="mt-1 text-sm text-slate-500">Completed for selected date</div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Today’s Schedule</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Appointment groups for {{ isset($selectedDate) ? \Illuminate\Support\Carbon::parse($selectedDate)->format('d M Y') : now()->format('d M Y') }}.
                        </p>
                    </div>

                    <a
                        href="{{ route('app.calendar') }}"
                        class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Open Calendar
                    </a>
                </div>
            </div>

            <div class="overflow-x-auto">
                @if(isset($todayList) && count($todayList))
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Customer</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Service</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($todayList as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-4 text-sm font-medium text-slate-900">{{ $row->start_time }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-700">{{ $row->customer_name }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-700">{{ $row->service_name }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-700">{{ $row->staff_name }}</td>
                                    <td class="px-5 py-4 text-sm text-slate-700">{{ $row->status }}</td>
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