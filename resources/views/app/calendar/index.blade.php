<x-internal-layout :title="$title" :subtitle="$subtitle">
    @php
        $weekQueryBase = request()->except(['week', 'date']);
        $timelineHasEvents = $timelineEvents->count() > 0;
    @endphp


    <div class="space-y-5">
        <section class="toolbar-card p-4">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div class="compact-label">Operational Board</div>
                    <h2 class="panel-title-display">{{ $selectedDateLabel }}</h2>
                    <p class="panel-subtitle">The live day board is the primary workspace. Service color shows treatment type, and the badge shows appointment status.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('app.calendar', array_merge($weekQueryBase, ['week' => $previousWeek, 'date' => \Carbon\Carbon::parse($previousWeek)->format('Y-m-d'), 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">&larr; Week</a>
                    <a href="{{ route('app.calendar', array_merge(request()->except(['week', 'date']), ['week' => $currentWeek, 'date' => now()->startOfWeek(\Carbon\Carbon::TUESDAY)->toDateString()])) }}" class="btn btn-secondary">Today</a>
                    <a href="{{ route('app.calendar', array_merge($weekQueryBase, ['week' => $nextWeek, 'date' => \Carbon\Carbon::parse($nextWeek)->format('Y-m-d'), 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">Week &rarr;</a>
                    <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="btn btn-primary">Booking Queue</a>
                </div>
            </div>

            <div class="mt-4 grid gap-3 xl:grid-cols-[minmax(0,1fr)_280px]">
                <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <div class="metric-chip"><div class="metric-chip__label">Total</div><div class="metric-chip__value">{{ $daySummary['total'] }}</div><div class="metric-chip__meta">Today</div></div>
                    <div class="metric-chip"><div class="metric-chip__label">Pending</div><div class="metric-chip__value">{{ $daySummary['pending'] }}</div><div class="metric-chip__meta">Awaiting action</div></div>
                    <div class="metric-chip"><div class="metric-chip__label">Confirmed</div><div class="metric-chip__value">{{ $daySummary['confirmed'] }}</div><div class="metric-chip__meta">Reserved</div></div>
                    <div class="metric-chip"><div class="metric-chip__label">Checked In</div><div class="metric-chip__value">{{ $daySummary['checked_in'] }}</div><div class="metric-chip__meta">On site</div></div>
                    <div class="metric-chip"><div class="metric-chip__label">Completed</div><div class="metric-chip__value">{{ $daySummary['completed'] }}</div><div class="metric-chip__meta">Finished</div></div>
                    <div class="metric-chip"><div class="metric-chip__label">Cancelled</div><div class="metric-chip__value">{{ $daySummary['cancelled_or_no_show'] }}</div><div class="metric-chip__meta">Released / lost</div></div>
                </div>

                <form method="GET" action="{{ route('app.calendar') }}" class="summary-card p-3">
                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                    <input type="hidden" name="date" value="{{ $selectedDateIso }}">
                    <label for="staff_id" class="mb-2 block compact-label">Staff filter</label>
                    <div class="flex gap-2">
                        <select id="staff_id" name="staff_id" class="form-select">
                            <option value="">All staff</option>
                            @foreach ($staffList as $staff)
                                <option value="{{ $staff->id }}" @selected((string) $staffId === (string) $staff->id)>{{ $staff->full_name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary">Go</button>
                    </div>
                    <div class="mt-2 small-note">{{ $selectedStaff ? $selectedStaff->full_name.' - '.($selectedStaff->job_title ?: 'Staff') : 'Showing full clinic workload' }}</div>
                </form>
            </div>
        </section>

        <section class="timeline-wrap">
            <div class="timeline-panel surface-card overflow-hidden">
                <div class="panel-header">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <div class="compact-label">Live Day Board</div>
                            <h3 class="panel-title-display">Daily operations timeline</h3>
                            @if ($canManageAppointments)
                                <p class="small-note" style="text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700;">Drag individual service blocks to reschedule. Occupied staff blocks are rejected automatically.</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($statusLegend as $item)
                                <span class="legend-pill" style="background: {{ $item['badge_bg'] }}; border-color: {{ $item['badge_border'] }}; color: {{ $item['badge_text'] }};"><span class="legend-dot" style="background: {{ $item['dot'] }};"></span>{{ $item['label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="px-5 py-5">
                    <div class="timeline-grid" style="height: {{ $timelineHeightPx }}px;">
                        @foreach ($slots as $index => $slot)
                            @php $rowTop = $index * $rowHeightPx; @endphp
                            <div class="timeline-time" style="top: {{ $rowTop + 18 }}px;">{{ $slot['label'] }}</div>
                            <div class="timeline-row" style="height: {{ $rowHeightPx }}px;">
                                <a href="{{ $slot['create_url'] }}" class="timeline-slot-link"><span>+ Quick Create</span></a>
                            </div>
                        @endforeach

                        <div class="event-layer">
                            @foreach ($timelineEvents as $event)
                                <button type="button" class="calendar-event calendar-event-btn text-left {{ $canManageAppointments ? 'is-draggable' : '' }}" style="top: {{ $event['top_px'] }}px; height: {{ $event['height_px'] }}px; left: calc({{ $event['left_pct'] }}% + 8px); width: calc({{ $event['width_pct'] }}% - 12px); background: {{ $event['service_styles']['surface'] }}; border-color: {{ $event['service_styles']['border'] }}; color: {{ $event['service_styles']['text'] }}; --service-accent: {{ $event['service_styles']['accent'] }};" data-event='@json($event)' data-original-top="{{ $event['top_px'] }}" title="{{ $canManageAppointments ? 'Drag to reschedule or click for details' : 'Click for details' }}">
                                    <div class="calendar-event__head">
                                        <span class="calendar-event__service-chip" style="background: {{ $event['service_styles']['chip_bg'] }}; color: {{ $event['service_styles']['chip_text'] }}; border-color: {{ $event['service_styles']['border'] }};">{{ $event['service_summary'] }}</span>
                                        <span class="calendar-event__status-chip" style="background: {{ $event['status_styles']['badge_bg'] }}; border-color: {{ $event['status_styles']['badge_border'] }}; color: {{ $event['status_styles']['badge_text'] }};"><span class="calendar-event__status-dot" style="background: {{ $event['status_styles']['dot'] }};"></span>{{ $event['status_label'] }}</span>
                                    </div>
                                    <div class="calendar-event__name truncate">{{ $event['customer_name'] }}</div>
                                    <div class="calendar-event__time truncate">{{ $event['start_time'] }} - {{ $event['end_time'] }}</div>
                                    <div class="mt-3 space-y-1 text-xs">
                                        <div class="calendar-event__meta truncate"><span class="font-bold text-white">Staff:</span> {{ $event['staff_summary'] }}</div>
                                        @if ($event['group_service_count'] > 1)
                                            <div class="calendar-event__meta truncate"><span class="font-bold text-white">Visit:</span> {{ $event['visit_summary'] }}</div>
                                        @endif
                                        @if ($event['membership_label'])
                                            <div class="calendar-event__meta truncate"><span class="font-bold text-white">Package:</span> {{ $event['membership_label'] }}</div>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        @if (! $timelineHasEvents)
                            <div class="timeline-empty-overlay">
                                <div class="timeline-empty-overlay__card">
                                    <div class="empty-state__title">No appointments on this day</div>
                                    <p class="empty-state__body">This still behaves like a real live board. Hover any time row to quick-create a booking directly from the empty schedule.</p>
                                    <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="btn btn-primary mt-5 pointer-events-auto">Create Appointment</a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <aside class="sidebar-stack">
                <div class="sidebar-card">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="compact-label">Staff Load</div>
                            <h3 class="panel-title-display" style="font-size: 1.45rem;">Booked staff</h3>
                        </div>
                        <span class="soft-pill">{{ $staffLoad->count() }} staff</span>
                    </div>
                    <div class="mt-3 space-y-3">
                        @forelse ($staffLoad as $load)
                            <div class="load-card">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-extrabold text-white">{{ $load['name'] }}</div>
                                        <div class="mt-1 small-note" style="text-transform: uppercase; letter-spacing: 0.08em;">{{ $load['job_title'] ?: 'Operational staff' }}</div>
                                    </div>
                                    <span class="soft-pill">{{ $load['appointments'] }} item{{ $load['appointments'] === 1 ? '' : 's' }}</span>
                                </div>
                                <div class="mt-3 text-sm text-[var(--app-text)]">{{ $load['hours_label'] }}</div>
                            </div>
                        @empty
                            <div class="summary-card px-4 py-5 text-center text-sm text-[var(--app-muted)]">No active staff assignments in the selected day and filter.</div>
                        @endforelse
                    </div>
                </div>

                <div class="sidebar-card">
                    <div class="compact-label">Week Strip</div>
                    <div class="mt-3 grid gap-2">
                        @foreach ($days as $day)
                            <a href="{{ $day['url'] }}" class="day-link {{ $day['is_selected'] ? 'is-selected' : '' }}">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-[var(--app-muted)]">{{ $day['full_label'] }}</div>
                                        <div class="mt-1 text-sm font-extrabold text-white">{{ $day['display_date'] }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if ($day['is_today'])
                                            <span class="soft-pill" style="border-color: rgba(94, 190, 145, 0.35); background: rgba(24, 80, 60, 0.42); color: #b9f1d2;">Today</span>
                                        @endif
                                        <span class="soft-pill">{{ $day['appointment_count'] }}</span>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </aside>
        </section>
    </div>

    <div id="calendar-detail-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card">
                <div id="modal-header" class="modal-header">
                    <div class="modal-header__row">
                        <div>
                            <div class="modal-kicker">Service Appointment</div>
                            <h3 id="modal-customer-name" class="modal-title">-</h3>
                            <p id="modal-service-summary" class="modal-subtitle">-</p>
                        </div>
                        <button type="button" id="calendar-detail-close-top" class="modal-close" aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="modal-meta-grid">
                        <div class="modal-panel modal-panel--wide"><div class="modal-panel__label">Date & Time</div><div id="modal-time" class="modal-panel__value">-</div></div>
                        <div class="modal-panel"><div class="modal-panel__label">Status</div><div id="modal-status" class="modal-panel__value" style="margin-top:10px;"></div></div>
                    </div>
                    <div class="modal-meta-grid">
                        <div class="modal-panel"><div class="modal-panel__label">Customer Phone</div><div id="modal-phone" class="modal-panel__value">-</div></div>
                        <div class="modal-panel"><div class="modal-panel__label">Package / Membership</div><div id="modal-membership" class="modal-panel__value">-</div></div>
                        <div class="modal-panel"><div class="modal-panel__label">Source</div><div id="modal-source" class="modal-panel__value">-</div></div>
                    </div>
                    <div class="modal-meta-grid">
                        <div class="modal-panel"><div class="modal-panel__label">Visit Services</div><div id="modal-services" class="modal-pill-list">-</div></div>
                        <div class="modal-panel"><div class="modal-panel__label">Assigned Staff</div><div id="modal-staff" class="modal-pill-list">-</div></div>
                    </div>
                    <div class="modal-panel"><div class="modal-panel__label">Linked Visit Flow</div><div id="modal-linked-services" class="modal-pill-list">-</div></div>
                    <div class="modal-panel"><div class="modal-panel__label">Notes</div><div id="modal-notes" class="modal-panel__value" style="white-space:pre-line;">-</div></div>
                </div>
                <div class="modal-actions">
                    <a id="modal-create-link" href="#" class="modal-btn modal-btn--secondary">New Booking At This Time</a>
                    <a id="modal-manage-link" href="#" class="modal-btn modal-btn--primary">Open Appointments</a>
                    <button type="button" id="calendar-detail-close-bottom" class="modal-btn modal-btn--secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('calendar-detail-modal');
            const modalHeader = document.getElementById('modal-header');
            const closeTop = document.getElementById('calendar-detail-close-top');
            const closeBottom = document.getElementById('calendar-detail-close-bottom');
            const manageLink = document.getElementById('modal-manage-link');
            const createLink = document.getElementById('modal-create-link');
            const timelineGrid = document.querySelector('.timeline-grid');
            const timelineButtons = Array.from(document.querySelectorAll('.calendar-event-btn'));
            const canManageAppointments = @json($canManageAppointments);
            const rowHeightPx = @json($rowHeightPx);
            const selectedDateIso = @json($selectedDateIso);
            const slots = @json($slots);
            const csrfToken = @json(csrf_token());

            const setText = (id, value, fallback = '-') => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value && String(value).trim() !== '' ? value : fallback;
                }
            };

            const setList = (id, values) => {
                const element = document.getElementById(id);
                if (!element) return;
                const items = Array.isArray(values) ? values.filter(Boolean) : [];
                if (!items.length) {
                    element.innerHTML = '<div>-</div>';
                    return;
                }
                element.innerHTML = items.map((item) => `<span class="modal-pill">${String(item)}</span>`).join('');
            };

            const setStatusChip = (eventData) => {
                const container = document.getElementById('modal-status');
                if (!container) return;
                container.innerHTML = `<span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-bold" style="background:${eventData.status_styles.badge_bg}; border-color:${eventData.status_styles.badge_border}; color:${eventData.status_styles.badge_text};"><span style="width:8px; height:8px; border-radius:999px; background:${eventData.status_styles.dot}; display:inline-block;"></span>${eventData.status_label || 'Status'}</span>`;
            };

            const openModal = (eventData) => {
                setText('modal-customer-name', eventData.customer_name);
                setText('modal-service-summary', eventData.service_summary);
                setText('modal-time', `${eventData.date_label || '-'} | ${eventData.start_time || '-'} - ${eventData.end_time || '-'}`);
                setStatusChip(eventData);
                setList('modal-services', eventData.service_names || []);
                setList('modal-staff', eventData.staff_details || eventData.staff_names || []);
                setList('modal-linked-services', eventData.linked_visit_services || []);
                setText('modal-phone', eventData.customer_phone, 'No phone recorded');
                setText('modal-membership', eventData.membership_label, 'No package or membership linked');
                setText('modal-source', eventData.source, 'Not recorded');
                setText('modal-notes', eventData.notes, 'No notes recorded.');
                manageLink.href = eventData.manage_url || '#';
                createLink.href = eventData.create_url || '#';
                modalHeader.style.background = `linear-gradient(180deg, ${eventData.service_styles.surface} 0%, #ffffff 100%)`;
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            const parseEventPayload = (button) => {
                try {
                    return JSON.parse(button.dataset.event || '{}');
                } catch (error) {
                    return {};
                }
            };

            const toMinutes = (time) => {
                const [hours, minutes] = String(time || '00:00').split(':').map(Number);
                return (hours * 60) + minutes;
            };

            const sharesStaff = (sourceEvent, otherEvent) => {
                const sourceStaff = new Set((sourceEvent.staff_ids || []).filter(Boolean));
                return (otherEvent.staff_ids || []).some((id) => sourceStaff.has(id));
            };

            const hasClientConflict = (sourceEvent, proposedStartMinutes, proposedEndMinutes) => {
                return timelineButtons.some((otherButton) => {
                    const otherEvent = parseEventPayload(otherButton);

                    if (!otherEvent.id || otherEvent.id === sourceEvent.id) {
                        return false;
                    }

                    if (!sharesStaff(sourceEvent, otherEvent)) {
                        return false;
                    }

                    const otherStart = toMinutes(otherEvent.start_24);
                    const otherEnd = otherStart + Number(otherEvent.duration_minutes || 0);

                    return proposedStartMinutes < otherEnd && proposedEndMinutes > otherStart;
                });
            };

            const attachDragBehavior = (button) => {
                const payload = parseEventPayload(button);

                if (!canManageAppointments || !payload.id || !payload.reschedule_url || !timelineGrid) {
                    button.addEventListener('click', () => openModal(payload));
                    return;
                }

                let dragState = null;

                button.addEventListener('pointerdown', (event) => {
                    if (event.button !== 0) {
                        return;
                    }

                    dragState = {
                        startY: event.clientY,
                        originalTop: Number(button.dataset.originalTop || payload.top_px || 0),
                        currentTop: Number(button.dataset.originalTop || payload.top_px || 0),
                        dragged: false,
                    };

                    button.setPointerCapture?.(event.pointerId);
                });

                button.addEventListener('pointermove', (event) => {
                    if (!dragState) {
                        return;
                    }

                    const deltaY = event.clientY - dragState.startY;

                    if (!dragState.dragged && Math.abs(deltaY) > 6) {
                        dragState.dragged = true;
                        button.classList.add('is-dragging');
                    }

                    if (!dragState.dragged) {
                        return;
                    }

                    const maxTop = Math.max(0, timelineGrid.offsetHeight - button.offsetHeight);
                    const rawTop = dragState.originalTop + deltaY;
                    const snappedTop = Math.max(0, Math.min(maxTop, Math.round(rawTop / rowHeightPx) * rowHeightPx));
                    const slotIndex = Math.max(0, Math.min(slots.length - 1, Math.round(snappedTop / rowHeightPx)));
                    const proposedStart = toMinutes(slots[slotIndex]?.time || payload.start_24);
                    const proposedEnd = proposedStart + Number(payload.duration_minutes || 0);
                    const conflict = hasClientConflict(payload, proposedStart, proposedEnd);

                    dragState.currentTop = snappedTop;
                    button.style.top = `${snappedTop}px`;
                    button.classList.toggle('is-conflict', conflict);
                });

                button.addEventListener('pointerup', async () => {
                    if (!dragState) {
                        return;
                    }

                    const wasDragged = dragState.dragged;
                    const finalTop = dragState.currentTop;

                    button.classList.remove('is-dragging');

                    if (!wasDragged) {
                        openModal(payload);
                        dragState = null;
                        return;
                    }

                    const slotIndex = Math.max(0, Math.min(slots.length - 1, Math.round(finalTop / rowHeightPx)));
                    const targetSlot = slots[slotIndex]?.time || payload.start_24;
                    const proposedStart = toMinutes(targetSlot);
                    const proposedEnd = proposedStart + Number(payload.duration_minutes || 0);
                    const conflict = hasClientConflict(payload, proposedStart, proposedEnd);

                    if (conflict) {
                        button.style.top = `${dragState.originalTop}px`;
                        button.classList.remove('is-conflict');
                        window.alert('That staff block is already occupied by another customer. Choose an empty time slot.');
                        dragState = null;
                        return;
                    }

                    if (targetSlot === payload.start_24) {
                        button.style.top = `${dragState.originalTop}px`;
                        button.classList.remove('is-conflict');
                        dragState = null;
                        return;
                    }

                    const confirmed = window.confirm(`Move this appointment to ${targetSlot}?`);

                    if (!confirmed) {
                        button.style.top = `${dragState.originalTop}px`;
                        button.classList.remove('is-conflict');
                        dragState = null;
                        return;
                    }

                    try {
                        const body = new URLSearchParams();
                        body.set('_method', 'PATCH');
                        body.set('_token', csrfToken);
                        body.set('starts_at', `${selectedDateIso} ${targetSlot}`);

                        const response = await fetch(payload.reschedule_url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: body.toString(),
                        });

                        const responseText = await response.text();
                        let result = {};

                        try {
                            result = responseText ? JSON.parse(responseText) : {};
                        } catch (parseError) {
                            result = {};
                        }

                        if (!response.ok) {
                            throw new Error(
                                result.message
                                || result.errors?.starts_at?.[0]
                                || result.errors?.[0]
                                || 'Unable to reschedule this appointment.'
                            );
                        }

                        window.location.reload();
                    } catch (error) {
                        button.style.top = `${dragState.originalTop}px`;
                        button.classList.remove('is-conflict');
                        window.alert(error.message || 'Unable to reschedule this appointment.');
                    } finally {
                        dragState = null;
                    }
                });

                button.addEventListener('pointercancel', () => {
                    if (!dragState) {
                        return;
                    }

                    button.style.top = `${dragState.originalTop}px`;
                    button.classList.remove('is-dragging', 'is-conflict');
                    dragState = null;
                });
            };

            timelineButtons.forEach((button) => attachDragBehavior(button));

            [closeTop, closeBottom].forEach((button) => button?.addEventListener('click', closeModal));
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
</x-internal-layout>
