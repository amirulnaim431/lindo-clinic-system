<x-layouts.internal :title="$title" :subtitle="$subtitle">
    @php
        $queryBase = request()->except(['week']);
    @endphp

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                        Calendar Window
                    </div>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-900">
                        {{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        Operational calendar view for clinic working days only. Monday and Sunday are hidden from this view.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a
                        href="{{ route('app.calendar', array_merge($queryBase, ['week' => $previousWeek])) }}"
                        class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        ← Previous
                    </a>

                    <a
                        href="{{ route('app.calendar', array_merge(request()->except(['week', 'staff_id']), ['week' => $currentWeek])) }}"
                        class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        This Week
                    </a>

                    <a
                        href="{{ route('app.calendar', array_merge($queryBase, ['week' => $nextWeek])) }}"
                        class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Next →
                    </a>
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-400">
                            Visible Days
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">
                            {{ $days->count() }}
                        </div>
                        <div class="mt-1 text-sm text-slate-500">
                            Tue to Sat
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-400">
                            Total Appointments
                        </div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">
                            {{ $totalAppointments }}
                        </div>
                        <div class="mt-1 text-sm text-slate-500">
                            In current calendar window
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-400">
                            Mode
                        </div>
                        <div class="mt-2 text-base font-semibold text-slate-900">
                            Operational Weekly View
                        </div>
                        <div class="mt-1 text-sm text-slate-500">
                            Customer, service, doctor/staff, and schedule in one place
                        </div>
                    </div>
                </div>

                <form method="GET" action="{{ route('app.calendar') }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">
                        Filter calendar
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        Narrow the calendar by assigned staff while keeping the same week view.
                    </p>

                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">

                    <div class="mt-4">
                        <label for="staff_id" class="mb-2 block text-sm font-medium text-slate-700">
                            Assigned staff
                        </label>
                        <select
                            id="staff_id"
                            name="staff_id"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                        >
                            <option value="">All staff</option>
                            @foreach($staffList as $staff)
                                <option value="{{ $staff->id }}" @selected((string) $staffId === (string) $staff->id)>
                                    {{ $staff->full_name }}@if($staff->role_key) ({{ $staff->role_key }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-4 flex gap-3">
                        <button
                            type="submit"
                            class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
                        >
                            Apply Filter
                        </button>

                        <a
                            href="{{ route('app.calendar', ['week' => $weekStart->toDateString()]) }}"
                            class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-white"
                        >
                            Clear
                        </a>
                    </div>
                </form>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-5">
            @foreach($days as $day)
                @php
                    $dayEvents = $eventsByDay[$day['date']] ?? collect();
                @endphp

                <div class="flex min-h-[520px] flex-col rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                                    {{ $day['full_label'] }}
                                </div>
                                <div class="mt-1 text-lg font-semibold text-slate-900">
                                    {{ $day['display_date'] }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if($day['is_today'])
                                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                        Today
                                    </span>
                                @endif

                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {{ $dayEvents->count() }} appt
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex-1 space-y-3 px-4 py-4">
                        @forelse($dayEvents as $event)
                            <button
                                type="button"
                                class="calendar-event-btn block w-full rounded-2xl border p-4 text-left shadow-sm transition {{ $event['color_card'] }}"
                                data-event='@json($event)'
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 rounded-full {{ $event['color_dot'] }}"></span>
                                            <div class="truncate text-sm font-semibold">
                                                {{ $event['service_summary'] }}
                                            </div>
                                        </div>

                                        <div class="mt-2 text-sm font-medium text-slate-800">
                                            {{ $event['customer_name'] }}
                                        </div>

                                        <div class="mt-1 text-xs text-slate-600">
                                            {{ $event['staff_summary'] }}
                                        </div>
                                    </div>

                                    <span class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $event['color_badge'] }}">
                                        {{ $event['start_time'] }}
                                    </span>
                                </div>

                                <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-600">
                                    <span>{{ $event['start_time'] }} – {{ $event['end_time'] }}</span>
                                    <span>{{ $event['status_label'] }}</span>
                                </div>
                            </button>
                        @empty
                            <div class="flex h-full min-h-[180px] items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-6 text-center">
                                <div>
                                    <div class="text-sm font-semibold text-slate-700">
                                        No appointments
                                    </div>
                                    <p class="mt-1 text-sm text-slate-500">
                                        This clinic day has no bookings for the current filter.
                                    </p>
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </section>
    </div>

    <div
        id="calendar-detail-modal"
        class="fixed inset-0 z-50 hidden"
        aria-hidden="true"
    >
        <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>

        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                            Appointment Detail
                        </div>
                        <h3 id="modal-customer-name" class="mt-1 text-2xl font-semibold text-slate-900">
                            —
                        </h3>
                        <p id="modal-service-summary" class="mt-2 text-sm text-slate-500">
                            —
                        </p>
                    </div>

                    <button
                        type="button"
                        id="calendar-detail-close-top"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                        aria-label="Close"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-6 px-6 py-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Time
                            </div>
                            <div id="modal-time" class="mt-2 text-sm font-semibold text-slate-900">
                                —
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Status
                            </div>
                            <div id="modal-status" class="mt-2 text-sm font-semibold text-slate-900">
                                —
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Services
                            </div>
                            <div id="modal-services" class="mt-2 space-y-2 text-sm text-slate-700">
                                —
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Assigned Staff / Doctor
                            </div>
                            <div id="modal-staff" class="mt-2 space-y-2 text-sm text-slate-700">
                                —
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Customer Phone
                            </div>
                            <div id="modal-phone" class="mt-2 text-sm text-slate-700">
                                —
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Source
                            </div>
                            <div id="modal-source" class="mt-2 text-sm text-slate-700">
                                —
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                            Notes
                        </div>
                        <div id="modal-notes" class="mt-2 whitespace-pre-line text-sm text-slate-700">
                            —
                        </div>
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 px-6 py-4">
                    <button
                        type="button"
                        id="calendar-detail-close-bottom"
                        class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('calendar-detail-modal');
            const closeTop = document.getElementById('calendar-detail-close-top');
            const closeBottom = document.getElementById('calendar-detail-close-bottom');

            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el) {
                    el.textContent = value && String(value).trim() !== '' ? value : '—';
                }
            };

            const setList = (id, values) => {
                const el = document.getElementById(id);
                if (!el) return;

                const items = Array.isArray(values) ? values.filter(Boolean) : [];
                if (!items.length) {
                    el.innerHTML = '<div>—</div>';
                    return;
                }

                el.innerHTML = items
                    .map(item => `<div class="rounded-xl bg-slate-50 px-3 py-2">${String(item)}</div>`)
                    .join('');
            };

            const openModal = (eventData) => {
                setText('modal-customer-name', eventData.customer_name);
                setText('modal-service-summary', eventData.service_summary);
                setText('modal-time', `${eventData.start_time || '—'} – ${eventData.end_time || '—'}`);
                setText('modal-status', eventData.status_label);
                setList('modal-services', eventData.service_names || []);
                setList('modal-staff', eventData.staff_details || eventData.staff_names || []);
                setText('modal-phone', eventData.customer_phone);
                setText('modal-source', eventData.source || '—');
                setText('modal-notes', eventData.notes || 'No notes recorded.');

                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            document.querySelectorAll('.calendar-event-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    try {
                        const payload = JSON.parse(button.dataset.event || '{}');
                        openModal(payload);
                    } catch (error) {
                        console.error('Failed to open appointment detail modal.', error);
                    }
                });
            });

            [closeTop, closeBottom].forEach((button) => {
                if (button) {
                    button.addEventListener('click', closeModal);
                }
            });

            modal?.addEventListener('click', (event) => {
                if (event.target === modal || event.target === modal.firstElementChild) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });
        })();
    </script>
</x-layouts.internal>