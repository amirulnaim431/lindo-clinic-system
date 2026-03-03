<x-internal-layout :title="'Dashboard'" :subtitle="'Clinic Overview'">
    <div class="flex flex-col gap-6">

        {{-- Date filter --}}
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                <input
                    type="date"
                    name="date"
                    value="{{ $date ?? now()->toDateString() }}"
                    class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm"
                >
            </div>

            <button class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm hover:opacity-90">
                Apply
            </button>

            <a
                href="{{ url()->current() }}"
                class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50"
            >
                Reset
            </a>
        </form>

        {{-- KPI cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <div class="text-xs text-slate-500">Appointments Today</div>
                <div class="text-3xl font-semibold mt-2">{{ $kpi['today_total'] ?? 0 }}</div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <div class="text-xs text-slate-500">Completed</div>
                <div class="text-3xl font-semibold mt-2">{{ $kpi['today_completed'] ?? 0 }}</div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <div class="text-xs text-slate-500">Cancelled</div>
                <div class="text-3xl font-semibold mt-2">{{ $kpi['today_cancelled'] ?? 0 }}</div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <div class="text-xs text-slate-500">Active Staff</div>
                <div class="text-3xl font-semibold mt-2">{{ $kpi['active_staff'] ?? 0 }}</div>
            </div>
        </div>

        {{-- Today Preview --}}
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <div class="text-sm font-semibold">Today Preview</div>
                    <div class="text-xs text-slate-500">
                        @php $role = auth()->user()->role ?? null; @endphp

                        @if($role === 'staff')
                            Your upcoming appointments
                        @else
                            All upcoming appointments
                        @endif
                    </div>
                </div>

                {{-- IMPORTANT:
                     Do NOT call route('app.appointments.index') because your routes are not defined on staging yet.
                     Use a plain link for demo; you can wire it later once routes are stable.
                --}}
                <a
                    href="/app/appointments?date={{ $date ?? now()->toDateString() }}"
                    class="text-sm font-medium text-slate-900 hover:underline"
                >
                    View all →
                </a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse(($myNext ?? []) as $a)
                    <div class="px-5 py-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-sm font-medium truncate">
                                {{ optional($a->start_at)->format('H:i') ?? '—' }}
                                —
                                {{ $a->customer?->name ?? '—' }}
                            </div>
                            <div class="text-xs text-slate-500 truncate">
                                {{ $a->service?->name ?? '—' }}
                            </div>
                        </div>

                        @php
                            $statusVal = $a->status?->value ?? (is_string($a->status ?? null) ? $a->status : '');
                            $statusText = $statusVal ? ucwords(str_replace('_',' ', $statusVal)) : '—';
                        @endphp

                        <div class="text-xs px-3 py-1 rounded-full border border-slate-200 text-slate-700">
                            {{ $statusText }}
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-sm text-slate-500 text-center">
                        No appointments found for this date.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Quick links (safe URLs, no named routes) --}}
        <div class="flex flex-wrap gap-2">
            <a href="/app/dashboard" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50">
                Refresh dashboard
            </a>
            <a href="/app/appointments" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50">
                Appointments
            </a>
        </div>

    </div>
</x-internal-layout>