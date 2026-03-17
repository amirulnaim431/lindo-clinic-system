<x-internal-layout :title="'Calendar'" :subtitle="'Week View'">

    @php
        // Count appointments per day for header
        $dayCounts = [];
        for ($i=0; $i<7; $i++) {
            $k = $start->copy()->addDays($i)->toDateString();
            $dayCounts[$k] = ($appointmentsByDay[$k] ?? collect())->count();
        }
    @endphp

    <div class="flex flex-col gap-6">

        {{-- Header + Controls --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="text-lg font-semibold">
                    {{ $start->format('d M') }} – {{ $end->format('d M Y') }}
                </div>
                <div class="text-sm text-slate-500">Weekly schedule overview</div>
            </div>

            <div class="flex flex-wrap items-end gap-2">
                <a href="{{ route('app.calendar', ['week' => $start->copy()->subWeek()->toDateString(), 'staff_id' => $staffId]) }}"
                   class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50">
                    ← Previous
                </a>

                <a href="{{ route('app.calendar', ['week' => now()->toDateString(), 'staff_id' => $staffId]) }}"
                   class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50">
                    This Week
                </a>

                <a href="{{ route('app.calendar', ['week' => $start->copy()->addWeek()->toDateString(), 'staff_id' => $staffId]) }}"
                   class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm hover:bg-slate-50">
                    Next →
                </a>

                @if(!$isStaffUser)
                    <form method="GET" action="{{ route('app.calendar') }}" class="flex items-end gap-2 ml-2">
                        <input type="hidden" name="week" value="{{ $start->toDateString() }}">
                        <select name="staff_id" class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm min-w-56">
                            <option value="">All staff</option>
                            @foreach($staffList as $s)
                                <option value="{{ $s->id }}" @selected((string)$staffId === (string)$s->id)>
                                    {{ $s->full_name }}
                                </option>
                            @endforeach
                        </select>
                        <button class="px-3 py-2 rounded-xl bg-slate-900 text-white text-sm hover:opacity-90">
                            Filter
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Calendar Table --}}
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-[1200px] w-full text-sm table-fixed">
                    <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="w-24 text-left font-semibold px-4 py-3 border-b border-slate-200 align-middle">
                            Time
                        </th>

                        @for($i=0; $i<7; $i++)
                            @php
                                $d = $start->copy()->addDays($i);
                                $k = $d->toDateString();
                                $count = $dayCounts[$k] ?? 0;
                            @endphp
                            <th class="text-center font-semibold px-4 py-3 border-b border-slate-200 align-middle">
                                <div class="leading-tight">{{ $d->format('D') }}</div>
                                <div class="text-xs font-normal text-slate-500 leading-tight">{{ $d->format('d M') }}</div>
                                <div class="mt-1 inline-flex text-[11px] px-2 py-0.5 rounded-full border border-slate-200 bg-white text-slate-700">
                                    {{ $count }} appt
                                </div>
                            </th>
                        @endfor
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($hours as $h)
                        @php $label = str_pad($h, 2, '0', STR_PAD_LEFT).':00'; @endphp
                        <tr class="align-top">
                            <td class="w-24 px-4 py-3 border-b border-slate-100 text-slate-600 font-medium">
                                {{ $label }}
                            </td>

                            @for($i=0; $i<7; $i++)
                                @php
                                    $dateKey = $start->copy()->addDays($i)->toDateString();
                                    $items = ($appointmentsByDay[$dateKey] ?? collect())
                                        ->filter(fn($a) => (int)$a->starts_at->format('H') === (int)$h);
                                @endphp

                                <td class="px-3 py-2 border-b border-slate-100">
                                    <div class="min-h-[56px] flex flex-col gap-2">
                                        @foreach($items as $a)
                                            @php
                                                $statusVal = $a->status?->value ?? '';
                                                $statusText = $statusVal ? ucwords(str_replace('_',' ', $statusVal)) : '—';

                                                $statusColor = match($statusVal) {
                                                    'completed' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
                                                    'cancelled' => 'bg-rose-50 border-rose-200 text-rose-800',
                                                    default => 'bg-blue-50 border-blue-200 text-blue-800'
                                                };
                                            @endphp

                                            <div class="rounded-xl border px-3 py-2 {{ $statusColor }}">
                                                <div class="text-xs font-semibold truncate">
                                                    {{ $a->customer?->full_name ?? '—' }}
                                                </div>
                                                <div class="text-[11px] opacity-80 truncate">
                                                    {{ $a->service?->name ?? '—' }}
                                                </div>
                                                <div class="text-[10px] opacity-70 mt-1">
                                                    {{ $a->starts_at->format('H:i') }}–{{ $a->ends_at->format('H:i') }}
                                                    <span class="ml-2">({{ $statusText }})</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</x-internal-layout>

