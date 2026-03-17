<x-layouts.internal :title="$title" :subtitle="$subtitle">
    @php
        $queryBase = request()->except(['week']);
        $statusOptions = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
        ];
    @endphp

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                        Scheduler Window
                    </div>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-900">
                        {{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        Tue–Sat operational scheduler with real time slots, service color coding, and appointment detail actions.
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

                    <a
                        href="{{ route('app.appointments.index', ['date' => $weekStart->toDateString()]) }}"
                        class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        + New Appointment
                    </a>
                </div>
            </div>

            <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-400">Visible Days</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $days->count() }}</div>
                        <div class="mt-1 text-sm text-slate-500">Tuesday to Saturday</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-400">Appointments</div>
                        <div class="mt-2 text-2xl font-semibold text-slate-900">{{ $totalAppointments }}</div>
                        <div class="mt-1 text-sm text-slate-500">Current week window</div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-xs uppercase tracking-[0.18em] text-slate-400">View Mode</div>
                        <div class="mt-2 text-base font-semibold text-slate-900">Operational Scheduler</div>
                        <div class="mt-1 text-sm text-slate-500">See peak load by time, day, service and staff</div>
                    </div>
                </div>

                <form method="GET" action="{{ route('app.calendar') }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="text-sm font-semibold text-slate-900">Filter scheduler</div>
                    <p class="mt-1 text-sm text-slate-500">
                        Narrow the view by assigned staff without changing the week window.
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

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <div class="min-w-[1200px]">
                    <div class="grid border-b border-slate-200 bg-slate-50" style="grid-template-columns: 96px repeat(5, minmax(0, 1fr));">
                        <div class="border-r border-slate-200 px-4 py-4 text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                            Time
                        </div>

                        @foreach($days as $day)
                            <div class="border-r border-slate-200 px-4 py-4 last:border-r-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                                            {{ $day['full_label'] }}
                                        </div>
                                        <div class="mt-1 text-lg font-semibold text-slate-900">
                                            {{ $day['display_date'] }}
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-2">
                                        @if($day['is_today'])
                                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                Today
                                            </span>
                                        @endif

                                        <button
                                            type="button"
                                            class="open-slot-btn inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-600 transition hover:bg-slate-100"
                                            data-date="{{ $day['date'] }}"
                                            data-time="09:00"
                                            data-url="{{ $day['add_url'] }}"
                                        >
                                            + Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="grid" style="grid-template-columns: 96px repeat(5, minmax(0, 1fr));">
                        <div class="border-r border-slate-200 bg-white">
                            @foreach($slots as $slot)
                                <div
                                    class="flex items-start justify-end border-b border-slate-100 px-3 pt-2 text-xs font-medium text-slate-400"
                                    style="height: {{ $slotHeightPx }}px;"
                                >
                                    {{ $slot['label'] }}
                                </div>
                            @endforeach
                        </div>

                        @foreach($days as $day)
                            @php
                                $dayEvents = $eventsByDay[$day['date']] ?? collect();
                            @endphp

                            <div
                                class="scheduler-day-column relative border-r border-slate-200 last:border-r-0"
                                style="height: {{ $dayColumnHeightPx }}px;"
                            >
                                @foreach($slots as $slot)
                                    <button
                                        type="button"
                                        class="open-slot-btn absolute left-0 right-0 z-0 w-full border-b border-slate-100 text-left transition hover:bg-slate-50/80"
                                        style="top: {{ $loop->index * $slotHeightPx }}px; height: {{ $slotHeightPx }}px;"
                                        data-date="{{ $day['date'] }}"
                                        data-time="{{ $slot['time'] }}"
                                        data-url="{{ route('app.appointments.index', ['date' => $day['date']]) }}"
                                        aria-label="Add appointment on {{ $day['full_label'] }} at {{ $slot['label'] }}"
                                    ></button>
                                @endforeach

                                @forelse($dayEvents as $event)
                                    <button
                                        type="button"
                                        class="calendar-event-btn absolute left-2 right-2 z-10 overflow-hidden rounded-2xl border px-3 py-2 text-left shadow-sm transition {{ $event['color_card'] }}"
                                        style="top: {{ $event['top_px'] }}px; height: {{ $event['height_px'] }}px;"
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

                                                <div class="mt-1 truncate text-sm font-medium text-slate-900">
                                                    {{ $event['customer_name'] }}
                                                </div>

                                                <div class="mt-1 truncate text-xs text-slate-600">
                                                    {{ $event['staff_summary'] }}
                                                </div>
                                            </div>

                                            <span class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $event['color_badge'] }}">
                                                {{ $event['start_time'] }}
                                            </span>
                                        </div>

                                        <div class="mt-2 flex items-center justify-between gap-2 text-[11px] text-slate-600">
                                            <span>{{ $event['start_time'] }} – {{ $event['end_time'] }}</span>
                                            <span>{{ $event['status_label'] }}</span>
                                        </div>
                                    </button>
                                @empty
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div
        id="calendar-detail-modal"
        class="fixed inset-0 z-50 hidden"
        aria-hidden="true"
    >
        <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>

        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-2xl">
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
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Time</div>
                            <div id="modal-time" class="mt-2 text-sm font-semibold text-slate-900">—</div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Status</div>
                            <div id="modal-status" class="mt-2 text-sm font-semibold text-slate-900">—</div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Source</div>
                            <div id="modal-source" class="mt-2 text-sm text-slate-700">—</div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Services</div>
                            <div id="modal-services" class="mt-2 space-y-2 text-sm text-slate-700">—</div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Assigned Staff / Doctor</div>
                            <div id="modal-staff" class="mt-2 space-y-2 text-sm text-slate-700">—</div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Customer Phone</div>
                            <div id="modal-phone" class="mt-2 text-sm text-slate-700">—</div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Quick Status Adjustment</div>
                            <form id="modal-status-form" method="POST" action="#" class="mt-3 flex gap-2">
                                @csrf
                                @method('PATCH')
                                <select
                                    id="modal-status-select"
                                    name="status"
                                    class="min-w-0 flex-1 rounded-2xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200"
                                >
                                    @foreach($statusOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>

                                <button
                                    type="submit"
                                    class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-800"
                                >
                                    Update
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Notes</div>
                        <div id="modal-notes" class="mt-2 whitespace-pre-line text-sm text-slate-700">—</div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-wrap gap-3">
                        <a
                            id="modal-manage-link"
                            href="#"
                            class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Manage Day
                        </a>

                        <a
                            id="modal-add-link"
                            href="#"
                            class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                        >
                            Add New Appointment
                        </a>
                    </div>

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

    <div
        id="calendar-slot-modal"
        class="fixed inset-0 z-50 hidden"
        aria-hidden="true"
    >
        <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>

        <div class="relative flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                            New Appointment
                        </div>
                        <h3 id="slot-modal-title" class="mt-1 text-2xl font-semibold text-slate-900">
                            Selected Time Slot
                        </h3>
                        <p class="mt-2 text-sm text-slate-500">
                            Continue to the appointment desk to create a booking for this selected day and time.
                        </p>
                    </div>

                    <button
                        type="button"
                        id="calendar-slot-close-top"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                        aria-label="Close"
                    >
                        ✕
                    </button>
                </div>

                <div class="px-6 py-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Selected Slot</div>
                        <div id="slot-modal-datetime" class="mt-2 text-sm font-semibold text-slate-900">—</div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <a
                        id="slot-modal-open-link"
                        href="#"
                        class="inline-flex items-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
                    >
                        Open Appointment Desk
                    </a>

                    <button
                        type="button"
                        id="calendar-slot-close-bottom"
                        class="inline-flex items-center rounded-2xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const detailModal = document.getElementById('calendar-detail-modal');
            const slotModal = document.getElementById('calendar-slot-modal');

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

            const lockBody = () => document.body.classList.add('overflow-hidden');
            const unlockBody = () => document.body.classList.remove('overflow-hidden');

            const openDetailModal = (eventData) => {
                setText('modal-customer-name', eventData.customer_name);
                setText('modal-service-summary', eventData.service_summary);
                setText('modal-time', `${eventData.start_time || '—'} – ${eventData.end_time || '—'}`);
                setText('modal-status', eventData.status_label);
                setText('modal-source', eventData.source || '—');
                setText('modal-phone', eventData.customer_phone);
                setText('modal-notes', eventData.notes || 'No notes recorded.');
                setList('modal-services', eventData.service_names || []);
                setList('modal-staff', eventData.staff_details || eventData.staff_names || []);

                const statusForm = document.getElementById('modal-status-form');
                const statusSelect = document.getElementById('modal-status-select');
                const manageLink = document.getElementById('modal-manage-link');
                const addLink = document.getElementById('modal-add-link');

                if (statusForm) statusForm.action = eventData.status_url || '#';
                if (statusSelect) statusSelect.value = eventData.status_value || 'pending';
                if (manageLink) manageLink.href = eventData.manage_url || '#';
                if (addLink) addLink.href = eventData.manage_url || '#';

                detailModal.classList.remove('hidden');
                detailModal.setAttribute('aria-hidden', 'false');
                lockBody();
            };

            const closeDetailModal = () => {
                detailModal.classList.add('hidden');
                detailModal.setAttribute('aria-hidden', 'true');
                unlockBody();
            };

            const openSlotModal = ({ date, time, url }) => {
                setText('slot-modal-title', 'Selected Time Slot');
                setText('slot-modal-datetime', `${date} at ${time}`);
                const link = document.getElementById('slot-modal-open-link');
                if (link) {
                    const target = new URL(url, window.location.origin);
                    target.searchParams.set('date', date);
                    target.searchParams.set('slot', time);
                    link.href = target.toString();
                }

                slotModal.classList.remove('hidden');
                slotModal.setAttribute('aria-hidden', 'false');
                lockBody();
            };

            const closeSlotModal = () => {
                slotModal.classList.add('hidden');
                slotModal.setAttribute('aria-hidden', 'true');
                unlockBody();
            };

            document.querySelectorAll('.calendar-event-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    try {
                        const payload = JSON.parse(button.dataset.event || '{}');
                        openDetailModal(payload);
                    } catch (error) {
                        console.error('Failed to open appointment detail modal.', error);
                    }
                });
            });

            document.querySelectorAll('.open-slot-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    openSlotModal({
                        date: button.dataset.date,
                        time: button.dataset.time,
                        url: button.dataset.url,
                    });
                });
            });

            ['calendar-detail-close-top', 'calendar-detail-close-bottom'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('click', closeDetailModal);
            });

            ['calendar-slot-close-top', 'calendar-slot-close-bottom'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('click', closeSlotModal);
            });

            detailModal?.addEventListener('click', (event) => {
                if (event.target === detailModal || event.target === detailModal.firstElementChild) {
                    closeDetailModal();
                }
            });

            slotModal?.addEventListener('click', (event) => {
                if (event.target === slotModal || event.target === slotModal.firstElementChild) {
                    closeSlotModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    if (detailModal && !detailModal.classList.contains('hidden')) {
                        closeDetailModal();
                    }

                    if (slotModal && !slotModal.classList.contains('hidden')) {
                        closeSlotModal();
                    }
                }
            });
        })();
    </script>
</x-layouts.internal>