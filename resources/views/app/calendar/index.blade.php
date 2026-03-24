<x-internal-layout :title="$title" :subtitle="$subtitle">
    @php
        $queryBase = request()->except(['date', 'anchor', 'view']);
        $timelineHasEvents = $timelineEvents->count() > 0;
        $weekAnchor = $weekStart->toDateString();
        $monthAnchor = $monthStart->toDateString();
    @endphp

    <div class="stack">
        <section class="toolbar-card">
            <div class="ops-card__body stack">
                <div class="filter-bar__head">
                    <div>
                        <div class="compact-label">Operational board</div>
                        <h2 class="panel-title-display">{{ $selectedDateLabel }}</h2>
                    </div>

                    <div class="page-actions">
                        <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'week', 'date' => $selectedDateIso, 'anchor' => $weekAnchor, 'staff_id' => $staffId ?: null])) }}" class="btn {{ $viewMode === 'week' ? 'btn-primary' : 'btn-secondary' }}">Week view</a>
                        <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'month', 'date' => $selectedDateIso, 'anchor' => $monthAnchor, 'staff_id' => $staffId ?: null])) }}" class="btn {{ $viewMode === 'month' ? 'btn-primary' : 'btn-secondary' }}">Month view</a>
                        <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="btn btn-secondary">Booking queue</a>
                    </div>
                </div>

                <div class="calendar-control-grid">
                    <div class="stack">
                        <div class="btn-row btn-row--between">
                            @if ($viewMode === 'month')
                                <div class="btn-row">
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'month', 'date' => \Carbon\Carbon::parse($previousMonth)->startOfMonth()->toDateString(), 'anchor' => $previousMonth, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">&larr; Previous month</a>
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'month', 'date' => now()->toDateString(), 'anchor' => $currentMonth, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">Today</a>
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'month', 'date' => \Carbon\Carbon::parse($nextMonth)->startOfMonth()->toDateString(), 'anchor' => $nextMonth, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">Next month &rarr;</a>
                                </div>
                            @else
                                <div class="btn-row" style="align-items: flex-end;">
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'week', 'date' => \Carbon\Carbon::parse($previousWeek)->toDateString(), 'anchor' => $previousWeek, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">&larr; Previous week</a>
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'week', 'date' => now()->toDateString(), 'anchor' => $currentWeek, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">Today</a>
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'week', 'date' => \Carbon\Carbon::parse($nextWeek)->toDateString(), 'anchor' => $nextWeek, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">Next week &rarr;</a>
                                    <form method="GET" action="{{ route('app.calendar') }}" style="display:flex;align-items:end;gap:0.65rem;">
                                        <input type="hidden" name="view" value="{{ $viewMode }}">
                                        <input type="hidden" name="anchor" value="{{ $weekAnchor }}">
                                        @if ($staffId)
                                            <input type="hidden" name="staff_id" value="{{ $staffId }}">
                                        @endif
                                        <div class="field-block" style="min-width: 170px;">
                                            <label for="board_date" class="field-label">Pick date</label>
                                            <input id="board_date" name="date" type="date" value="{{ $selectedDateIso }}" class="form-input" style="padding: 0.8rem 0.9rem;">
                                        </div>
                                        <button type="submit" class="btn btn-secondary">Go</button>
                                    </form>
                                </div>
                            @endif
                        </div>

                        @if ($viewMode === 'month')
                            <div class="month-grid">
                                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                                    <div class="metric-label">{{ $dow }}</div>
                                @endforeach

                                @foreach ($monthDays as $day)
                                    <a href="{{ $day['url'] }}" class="month-day-card {{ $day['is_selected'] ? 'is-selected' : '' }} {{ $day['is_outside_month'] ? 'is-outside-month' : '' }}">
                                        <div class="filter-bar__head">
                                            <div>
                                                <div class="selection-card__title">{{ $day['day_number'] }}</div>
                                                <div class="small-note">{{ $day['label'] }}</div>
                                            </div>
                                            @if ($day['is_today'])
                                                <span class="soft-pill">Today</span>
                                            @endif
                                        </div>
                                        <div class="small-note" style="margin-top: 0.85rem;">{{ $day['appointment_count'] }} appointment{{ $day['appointment_count'] === 1 ? '' : 's' }}</div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="calendar-days-row">
                                @foreach ($weekDays as $day)
                                    <a href="{{ $day['url'] }}" class="day-control-card {{ $day['is_selected'] ? 'is-selected' : '' }}">
                                        <div class="metric-label">{{ $day['full_label'] }}</div>
                                        <div class="selection-card__title" style="margin-top: 0.45rem;">{{ $day['display_date'] }}</div>
                                        <div class="small-note" style="margin-top: 0.55rem;">{{ $day['appointment_count'] }} appointment{{ $day['appointment_count'] === 1 ? '' : 's' }}</div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <form method="GET" action="{{ route('app.calendar') }}" class="report-card">
                        <input type="hidden" name="view" value="{{ $viewMode }}">
                        <input type="hidden" name="date" value="{{ $selectedDateIso }}">
                        <input type="hidden" name="anchor" value="{{ $viewMode === 'month' ? $monthAnchor : $weekAnchor }}">

                        <div class="metric-label">Staff filter</div>
                        <select id="staff_id" name="staff_id" class="form-select" style="margin-top: 0.9rem;">
                            <option value="">All staff</option>
                            @foreach ($staffList as $staff)
                                <option value="{{ $staff->id }}" @selected((string) $staffId === (string) $staff->id)>{{ $staff->full_name }}</option>
                            @endforeach
                        </select>
                        <div class="report-card__meta">{{ $selectedStaff ? $selectedStaff->full_name.' - '.($selectedStaff->job_title ?: 'Staff') : 'Showing full clinic workload' }}</div>
                        <div class="btn-row" style="margin-top: 1rem;">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="{{ route('app.calendar', ['view' => $viewMode, 'date' => $selectedDateIso, 'anchor' => $viewMode === 'month' ? $monthAnchor : $weekAnchor]) }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="summary-stat-grid">
            <x-stat-card label="Total" :value="$daySummary['total']" :meta="$selectedDate->format('d M Y')" />
            <x-stat-card label="Pending" :value="$daySummary['pending']" meta="Awaiting action" />
            <x-stat-card label="Confirmed" :value="$daySummary['confirmed']" meta="Reserved" />
            <x-stat-card label="Checked In" :value="$daySummary['checked_in']" meta="On site" />
            <x-stat-card label="Completed" :value="$daySummary['completed']" :meta="$daySummary['cancelled_or_no_show'].' cancelled / no-show'" />
        </section>

        <section class="panel">
            <div class="panel-header">
                <div class="filter-bar__head">
                    <div>
                        <div class="compact-label">Daily timeline</div>
                        <h3 class="panel-title-display">Appointments for {{ $selectedDate->format('d M Y') }}</h3>
                    </div>
                    @if ($canManageAppointments)
                        <div class="small-note">Drag blocks to reschedule.</div>
                    @endif
                </div>
            </div>

            <div class="panel-body">
                <div class="timeline-grid" style="height: {{ $timelineHeightPx }}px;">
                    @foreach ($slots as $index => $slot)
                        @php $rowTop = $index * $rowHeightPx; @endphp
                        <div class="timeline-time" style="top: {{ $rowTop + 18 }}px;">{{ $slot['label'] }}</div>
                        <div class="timeline-row" style="height: {{ $rowHeightPx }}px;">
                            @if (! ($slot['is_closing_marker'] ?? false) && ! empty($slot['create_url']))
                                <a href="{{ $slot['create_url'] }}" class="timeline-slot-link"><span>+ Create</span></a>
                            @endif
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
                                    <div class="calendar-event__meta truncate"><span class="calendar-event__meta-label">Staff:</span> {{ $event['staff_summary'] }}</div>
                                    @if ($event['group_service_count'] > 1)
                                        <div class="calendar-event__meta truncate"><span class="calendar-event__meta-label">Visit:</span> {{ $event['visit_summary'] }}</div>
                                    @endif
                                </div>
                            </button>
                        @endforeach
                    </div>

                    @if (! $timelineHasEvents)
                        <div class="timeline-empty-overlay">
                            <div class="timeline-empty-overlay__card">
                                <div class="empty-state__title">No appointments on this day</div>
                                <p class="empty-state__body">Use any open row to create a booking directly from the board.</p>
                                <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="btn btn-primary mt-5 pointer-events-auto">Create appointment</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    </div>

    <div id="calendar-detail-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card">
                <div id="modal-header" class="modal-header">
                    <div class="modal-header__row">
                        <div>
                            <div class="modal-kicker">Appointment</div>
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
                    <a id="modal-create-link" href="#" class="modal-btn modal-btn--secondary">Book this time</a>
                    <a id="modal-manage-link" href="#" class="modal-btn modal-btn--primary">Open appointments</a>
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
                modalHeader.style.background = `radial-gradient(circle at top left, ${eventData.service_styles.surface_strong || eventData.service_styles.surface} 0%, rgba(255, 247, 250, 0.88) 38%, transparent 62%)`;
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
