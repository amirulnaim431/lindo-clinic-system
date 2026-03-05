<x-app-layout>
    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Dashboard</h1>
                    <p class="text-sm text-slate-600">Operations overview for {{ $date }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="/app/appointments?date={{ $date }}"
                       class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Manage appointments
                    </a>
                    <a href="/app/calendar"
                       class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                        Calendar
                    </a>
                </div>
            </div>

            {{-- Filters --}}
            <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <form method="GET" action="/app/dashboard" class="flex flex-col gap-3 md:flex-row md:items-end">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                        <input type="date" name="date" value="{{ $date }}"
                               class="w-full rounded-xl border-slate-300 focus:border-slate-400 focus:ring-slate-400" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Staff</label>
                        <select name="staff_id" class="w-full rounded-xl border-slate-300 text-sm">
                            <option value="">All staff</option>
                            @foreach($staffList as $s)
                                <option value="{{ $s->id }}" @selected((string)$staffId === (string)$s->id)>
                                    {{ $s->full_name }} ({{ $s->role_key }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Apply
                        </button>
                        <a href="/app/dashboard"
                           class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- KPI --}}
            <div class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-semibold text-slate-500">Total</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900">{{ $kpi['total'] ?? 0 }}</div>
                </div>

                @foreach($statusCases as $st)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="text-xs font-semibold text-slate-500">
                            {{ method_exists($st, 'label') ? $st->label() : ucfirst(str_replace('_',' ', $st->value)) }}
                        </div>
                        <div class="mt-1 text-2xl font-semibold text-slate-900">
                            {{ $kpi['by_status'][$st->value] ?? 0 }}
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Schedule --}}
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-slate-900">Schedule</div>
                        <div class="text-sm text-slate-600">Appointments on {{ $date }}</div>
                    </div>
                    <a href="/app/appointments?date={{ $date }}"
                       class="text-sm font-semibold text-slate-800 hover:text-slate-900">
                        View all →
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Time</th>
                                <th class="px-4 py-3 text-left font-semibold">Customer</th>
                                <th class="px-4 py-3 text-left font-semibold">Services</th>
                                <th class="px-4 py-3 text-left font-semibold">Staff</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($appointments as $g)
                                @php
                                    $servicesSummary = $g->items?->map(fn($i) => $i->service?->name)->filter()->unique()->implode(', ') ?: '-';
                                    $staffSummary = $g->items?->map(fn($i) => $i->staff?->full_name)->filter()->unique()->implode(', ') ?: '-';
                                    $currentStatus = is_object($g->status) ? $g->status->value : (string)$g->status;
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-900">{{ optional($g->starts_at)->format('H:i') }}</td>
                                    <td class="px-4 py-3 text-slate-900">
                                        {{ $g->customer?->full_name ?? '-' }}
                                        <div class="text-xs text-slate-500">{{ $g->customer?->phone ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">{{ $servicesSummary }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $staffSummary }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                            {{ ucfirst(str_replace('_',' ', $currentStatus)) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-600">
                                        No appointments for this date.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>