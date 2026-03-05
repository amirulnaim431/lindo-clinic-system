<x-app-layout>
    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-900 text-sm">
                    <div class="font-semibold mb-1">Fix the following:</div>
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Appointments</h1>
                    <p class="text-sm text-slate-600">Dev phase: 1-hour slots (09:00–17:00)</p>
                </div>
                <a href="/app/dashboard" class="text-sm font-medium text-slate-700 hover:text-slate-900">
                    ← Back to Dashboard
                </a>
            </div>

            {{-- CREATE APPOINTMENT --}}
            <div class="mb-6 bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-200">
                    <div class="font-semibold text-slate-900">Create appointment</div>
                    <div class="text-sm text-slate-600">Pick services, check slots, then click a slot to book.</div>
                </div>

                <div class="p-5">
                    <form method="GET" action="/app/appointments" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Date</label>
                            <input type="date" name="date" value="{{ $filters['date'] ?? now()->format('Y-m-d') }}"
                                class="w-full rounded-xl border-slate-300 focus:border-slate-400 focus:ring-slate-400" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Services (multi-select)</label>
                            <select name="service_ids[]" multiple
                                class="w-full h-28 rounded-xl border-slate-300 focus:border-slate-400 focus:ring-slate-400">
                                @foreach($services as $svc)
                                    <option value="{{ $svc->id }}"
                                        @selected(in_array((string)$svc->id, $filters['service_ids'] ?? []))>
                                        {{ $svc->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-1 text-xs text-slate-500">Hold Ctrl / Command to select multiple.</div>
                        </div>

                        <div class="flex items-end gap-2">
                            <button type="submit"
                                class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                Check slots
                            </button>
                            <a href="/app/appointments?date={{ $filters['date'] ?? now()->format('Y-m-d') }}"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                                Clear
                            </a>
                        </div>
                    </form>

                    @if(!empty($availability))
                        <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="font-semibold text-slate-900 mb-2">Available slots</div>

                            @if(empty($availability['viableSlots']))
                                <div class="text-sm text-slate-700">No slots available for selected services.</div>
                            @else
                                <form method="POST" action="{{ route('app.appointments.store') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                                    @csrf
                                    <input type="hidden" name="date" value="{{ $filters['date'] }}" />
                                    @foreach(($filters['service_ids'] ?? []) as $sid)
                                        <input type="hidden" name="service_ids[]" value="{{ $sid }}" />
                                    @endforeach

                                    <div class="md:col-span-2 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        @foreach($availability['viableSlots'] as $slot)
                                            <button type="submit" name="slot" value="{{ $slot }}"
                                                class="rounded-xl bg-white border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                                                {{ $slot }}
                                            </button>
                                        @endforeach
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer name</label>
                                        <input type="text" name="customer_full_name" value="{{ old('customer_full_name') }}"
                                            class="w-full rounded-xl border-slate-300 focus:border-slate-400 focus:ring-slate-400" />
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer phone</label>
                                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}"
                                            class="w-full rounded-xl border-slate-300 focus:border-slate-400 focus:ring-slate-400" />
                                    </div>

                                    <div class="md:col-span-4">
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Notes (optional)</label>
                                        <textarea name="notes" rows="2"
                                            class="w-full rounded-xl border-slate-300 focus:border-slate-400 focus:ring-slate-400">{{ old('notes') }}</textarea>
                                    </div>

                                    <div class="md:col-span-4 text-xs text-slate-600">
                                        Clicking a slot will book a 1-hour appointment and auto-assign the first available staff per required role (dev behavior).
                                    </div>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            {{-- LIST + FILTERS --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-200 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="font-semibold text-slate-900">Appointments</div>
                        <div class="text-sm text-slate-600">Showing {{ $filters['date'] ?? now()->toDateString() }}</div>
                    </div>

                    <form method="GET" action="/app/appointments" class="flex flex-col gap-2 md:flex-row md:items-center">
                        <input type="hidden" name="date" value="{{ $filters['date'] ?? now()->toDateString() }}" />
                        @foreach(($filters['service_ids'] ?? []) as $sid)
                            <input type="hidden" name="service_ids[]" value="{{ $sid }}" />
                        @endforeach

                        <select name="staff_id" class="rounded-xl border-slate-300 text-sm">
                            <option value="">All staff</option>
                            @foreach($staffList as $s)
                                <option value="{{ $s->id }}" @selected((string)$filters['staff_id'] === (string)$s->id)>
                                    {{ $s->full_name }} ({{ $s->role }})
                                </option>
                            @endforeach
                        </select>

                        <select name="status" class="rounded-xl border-slate-300 text-sm">
                            <option value="">All statuses</option>
                            @foreach($statusOptions as $st)
                                <option value="{{ $st->value }}" @selected((string)$filters['status'] === (string)$st->value)>
                                    {{ $st->label() }}
                                </option>
                            @endforeach
                        </select>

                        <button type="submit"
                            class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Filter
                        </button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Time</th>
                                <th class="px-4 py-3 text-left font-semibold">Customer</th>
                                <th class="px-4 py-3 text-left font-semibold">Services</th>
                                <th class="px-4 py-3 text-left font-semibold">Assigned staff</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($appointmentGroups as $g)
                                @php
                                    $servicesSummary = $g->items?->map(fn($i) => $i->service?->name)->filter()->unique()->implode(', ') ?: '-';
                                    $staffSummary = $g->items?->map(fn($i) => $i->staff?->full_name)->filter()->unique()->implode(', ') ?: '-';
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-900">
                                        {{ optional($g->starts_at)->format('H:i') }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-900">
                                        {{ $g->customer?->full_name ?? '-' }}
                                        <div class="text-xs text-slate-500">{{ $g->customer?->phone ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ $servicesSummary }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ $staffSummary }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                                            {{ $g->status?->label() ?? (is_string($g->status) ? ucfirst($g->status) : '-') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('app.appointments.status', $g->id) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="rounded-xl border-slate-300 text-sm">
                                                @foreach($statusOptions as $st)
                                                    <option value="{{ $st->value }}" @selected((string)($g->status?->value ?? $g->status) === (string)$st->value)>
                                                        {{ $st->label() }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit"
                                                class="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">
                                                Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-slate-600">
                                        No appointments found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4">
                    {{ $appointmentGroups->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>