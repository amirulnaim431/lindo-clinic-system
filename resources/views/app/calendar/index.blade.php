<x-internal-layout :title="$title" :subtitle="$subtitle">
    @php
        $weekQueryBase = request()->except(['week', 'date']);
        $timelineHasEvents = $timelineEvents->count() > 0;
    @endphp

    <style>
        .toolbar-card{border:1px solid #e2e8f0;background:rgba(255,255,255,.94);backdrop-filter:blur(12px);border-radius:22px;box-shadow:0 1px 2px rgba(15,23,42,.05)}
        .surface-card{border:1px solid #e2e8f0;background:linear-gradient(180deg,#fff 0%,#fbfdff 100%);border-radius:24px;box-shadow:0 1px 2px rgba(15,23,42,.05)}
        .metric-chip{border:1px solid #e2e8f0;border-radius:18px;padding:10px 12px;background:linear-gradient(180deg,#fff 0%,#f8fbff 100%)}
        .metric-chip__label{font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#64748b}
        .metric-chip__value{margin-top:6px;font-size:20px;line-height:1;font-weight:800;letter-spacing:-.04em;color:#0f172a}
        .metric-chip__meta{margin-top:4px;font-size:11px;color:#64748b}
        .compact-label{font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#64748b}
        .timeline-wrap{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px}
        .timeline-panel{min-width:0}
        .sidebar-stack{display:grid;gap:18px}
        .timeline-grid{position:relative;margin-left:92px;border:1px solid #e2e8f0;border-radius:26px;overflow:hidden;background:linear-gradient(180deg,rgba(255,255,255,.99) 0%,rgba(248,250,252,.94) 100%)}
        .timeline-row{position:relative;border-bottom:1px solid #e2e8f0}
        .timeline-row:last-child{border-bottom:none}
        .timeline-slot-link{position:absolute;inset:10px 14px 10px 18px;display:flex;align-items:center;justify-content:flex-end;padding-right:10px;color:#94a3b8;opacity:0;transition:.16s ease;border-radius:18px;border:1px dashed transparent;background:rgba(255,255,255,0)}
        .timeline-row:hover .timeline-slot-link{opacity:1;color:#475569;border-color:#cbd5e1;background:rgba(255,255,255,.72)}
        .timeline-slot-link span{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
        .timeline-time{position:absolute;left:0;width:76px;transform:translateY(-50%);text-align:right;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#64748b}
        .event-layer{position:absolute;inset:0 0 0 92px;pointer-events:none}
        .calendar-event{position:absolute;padding:12px 14px 12px 16px;border-radius:20px;border:1px solid transparent;box-shadow:0 14px 32px rgba(15,23,42,.12);cursor:pointer;pointer-events:auto;overflow:hidden;transition:.16s ease;touch-action:none;user-select:none}
        .calendar-event:hover{transform:translateY(-1px);box-shadow:0 18px 36px rgba(15,23,42,.16)}
        .calendar-event.is-draggable{cursor:grab}
        .calendar-event.is-dragging{cursor:grabbing;z-index:40;box-shadow:0 22px 40px rgba(15,23,42,.22)}
        .calendar-event.is-conflict{outline:3px solid rgba(225,29,72,.22)}
        .calendar-event::before{content:"";position:absolute;inset:0 auto 0 0;width:5px;background:var(--service-accent)}
        .calendar-event__head{display:flex;justify-content:space-between;gap:12px;align-items:start}
        .calendar-event__service-chip,.calendar-event__status-chip,.legend-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:6px 10px;font-size:11px;font-weight:800;border:1px solid transparent;white-space:nowrap}
        .legend-pill{padding:7px 11px;font-size:11px;border-color:#e2e8f0;background:#fff;color:#334155}
        .calendar-event__status-dot,.legend-dot{width:8px;height:8px;border-radius:999px;display:inline-block;flex:0 0 auto}
        .timeline-empty-overlay{position:absolute;inset:0 0 0 92px;display:flex;align-items:center;justify-content:center;pointer-events:none}
        .timeline-empty-overlay__card{width:min(520px,calc(100% - 48px));border:1px dashed #cbd5e1;border-radius:24px;background:rgba(255,255,255,.9);padding:28px 24px;text-align:center;box-shadow:0 16px 36px rgba(15,23,42,.08)}
        .sidebar-card{border:1px solid #e2e8f0;border-radius:22px;background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);padding:16px}
        .load-card{border:1px solid #e2e8f0;border-radius:18px;padding:14px;background:linear-gradient(180deg,#fff 0%,#f8fafc 100%)}
        .day-link{display:block;border:1px solid #e2e8f0;border-radius:18px;padding:12px 14px;background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);transition:.18s ease}
        .day-link:hover{border-color:#cbd5e1;box-shadow:0 10px 24px rgba(15,23,42,.08);transform:translateY(-1px)}
        .day-link.is-selected{border-color:#0f172a;background:linear-gradient(180deg,#fff 0%,#eef4ff 100%);box-shadow:0 0 0 3px rgba(15,23,42,.06)}
        .modal-shell{position:fixed;inset:0;z-index:60}
        .modal-shell.hidden{display:none}
        .modal-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.56);backdrop-filter:blur(10px)}
        .modal-stage{position:relative;min-height:100%;display:flex;align-items:center;justify-content:center;padding:24px}
        .modal-card{width:min(760px,100%);border:1px solid #dbe4ef;border-radius:28px;background:#ffffff;box-shadow:0 28px 80px rgba(15,23,42,.26);overflow:hidden}
        .modal-header{padding:22px 24px 18px;border-bottom:1px solid #e2e8f0}
        .modal-header__row{display:flex;align-items:start;justify-content:space-between;gap:16px}
        .modal-close{appearance:none;border:1px solid #dbe4ef;background:rgba(255,255,255,.72);color:#475569;border-radius:16px;width:40px;height:40px;font-size:18px;font-weight:700;cursor:pointer;transition:.16s ease}
        .modal-close:hover{background:#f8fafc;color:#0f172a}
        .modal-kicker{font-size:11px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#64748b}
        .modal-title{margin-top:8px;font-size:28px;line-height:1.05;font-weight:800;letter-spacing:-.04em;color:#0f172a}
        .modal-subtitle{margin-top:8px;font-size:14px;color:#64748b}
        .modal-body{padding:20px 24px 22px;display:grid;gap:16px}
        .modal-meta-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .modal-panel{border:1px solid #e2e8f0;border-radius:20px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);padding:15px 16px}
        .modal-panel--wide{grid-column:span 2}
        .modal-panel__label{font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#64748b}
        .modal-panel__value{margin-top:8px;font-size:14px;font-weight:700;color:#0f172a;line-height:1.5}
        .modal-pill-list{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
        .modal-pill{display:inline-flex;align-items:center;border-radius:999px;border:1px solid #dbe4ef;background:#ffffff;padding:8px 11px;font-size:12px;font-weight:700;color:#334155}
        .modal-actions{padding:16px 24px 20px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;background:#fcfdff}
        .modal-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:16px;padding:11px 14px;font-size:13px;font-weight:800;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:.16s ease}
        .modal-btn--secondary{background:#ffffff;border-color:#cbd5e1;color:#0f172a}
        .modal-btn--secondary:hover{background:#f8fafc}
        .modal-btn--primary{background:#0f172a;border-color:#0f172a;color:#ffffff}
        .modal-btn--primary:hover{background:#1e293b}
        @media (max-width: 1280px){.timeline-wrap{grid-template-columns:1fr}.sidebar-stack{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width: 920px){.sidebar-stack{grid-template-columns:1fr}.modal-meta-grid{grid-template-columns:1fr}.modal-panel--wide{grid-column:span 1}.modal-stage{padding:14px}.modal-card{width:100%}.modal-title{font-size:24px}}
    </style>

    <div class="space-y-5">
        <section class="toolbar-card p-4">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <div class="compact-label">Operational Board</div>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-[-0.04em] text-slate-950">{{ $selectedDateLabel }}</h2>
                    <p class="mt-1 text-sm text-slate-500">The live day board is the primary workspace. Service color shows treatment type, and the badge shows appointment status.</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('app.calendar', array_merge($weekQueryBase, ['week' => $previousWeek, 'date' => \Carbon\Carbon::parse($previousWeek)->format('Y-m-d'), 'staff_id' => $staffId ?: null])) }}" class="inline-flex items-center rounded-2xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"><- Week</a>
                    <a href="{{ route('app.calendar', array_merge(request()->except(['week', 'date']), ['week' => $currentWeek, 'date' => now()->startOfWeek(\Carbon\Carbon::TUESDAY)->toDateString()])) }}" class="inline-flex items-center rounded-2xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">Today</a>
                    <a href="{{ route('app.calendar', array_merge($weekQueryBase, ['week' => $nextWeek, 'date' => \Carbon\Carbon::parse($nextWeek)->format('Y-m-d'), 'staff_id' => $staffId ?: null])) }}" class="inline-flex items-center rounded-2xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">Week -></a>
                    <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="inline-flex items-center rounded-2xl bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-slate-800">Booking Queue</a>
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

                <form method="GET" action="{{ route('app.calendar') }}" class="rounded-[18px] border border-slate-200 bg-slate-50 p-3">
                    <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                    <input type="hidden" name="date" value="{{ $selectedDateIso }}">
                    <label for="staff_id" class="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Staff filter</label>
                    <div class="flex gap-2">
                        <select id="staff_id" name="staff_id" class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm outline-none transition focus:border-slate-400 focus:ring-2 focus:ring-slate-200">
                            <option value="">All staff</option>
                            @foreach ($staffList as $staff)
                                <option value="{{ $staff->id }}" @selected((string) $staffId === (string) $staff->id)>{{ $staff->full_name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-900 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Go</button>
                    </div>
                    <div class="mt-2 text-xs text-slate-500">{{ $selectedStaff ? $selectedStaff->full_name.' - '.($selectedStaff->job_title ?: 'Staff') : 'Showing full clinic workload' }}</div>
                </form>
            </div>
        </section>

        <section class="timeline-wrap">
            <div class="timeline-panel surface-card overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <div class="compact-label">Live Day Board</div>
                            <h3 class="mt-1 text-2xl font-extrabold tracking-[-0.03em] text-slate-950">Daily operations timeline</h3>
                            @if ($canManageAppointments)
                                <p class="mt-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Drag whole appointment blocks to reschedule. Occupied staff blocks are rejected automatically.</p>
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
                                    <div class="mt-3 truncate text-sm font-extrabold text-slate-950">{{ $event['customer_name'] }}</div>
                                    <div class="mt-1 truncate text-xs font-semibold uppercase tracking-[0.08em] text-slate-600">{{ $event['start_time'] }} - {{ $event['end_time'] }}</div>
                                    <div class="mt-3 space-y-1 text-xs text-slate-700">
                                        <div class="truncate"><span class="font-bold text-slate-900">Staff:</span> {{ $event['staff_summary'] }}</div>
                                        @if ($event['membership_label'])
                                            <div class="truncate"><span class="font-bold text-slate-900">Package:</span> {{ $event['membership_label'] }}</div>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        @if (! $timelineHasEvents)
                            <div class="timeline-empty-overlay">
                                <div class="timeline-empty-overlay__card">
                                    <div class="text-lg font-extrabold text-slate-900">No appointments on this day</div>
                                    <p class="mt-2 text-sm text-slate-500">This still behaves like a real live board. Hover any time row to quick-create a booking directly from the empty schedule.</p>
                                    <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="mt-5 inline-flex items-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 pointer-events-auto">Create Appointment</a>
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
                            <h3 class="mt-1 text-lg font-extrabold tracking-[-0.03em] text-slate-950">Booked staff</h3>
                        </div>
                        <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-bold text-slate-600">{{ $staffLoad->count() }} staff</span>
                    </div>
                    <div class="mt-3 space-y-3">
                        @forelse ($staffLoad as $load)
                            <div class="load-card">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-extrabold text-slate-950">{{ $load['name'] }}</div>
                                        <div class="mt-1 text-xs uppercase tracking-[0.08em] text-slate-500">{{ $load['job_title'] ?: 'Operational staff' }}</div>
                                    </div>
                                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-[11px] font-bold text-slate-600">{{ $load['appointments'] }} item{{ $load['appointments'] === 1 ? '' : 's' }}</span>
                                </div>
                                <div class="mt-3 text-sm text-slate-600">{{ $load['hours_label'] }}</div>
                            </div>
                        @empty
                            <div class="rounded-[18px] border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center text-sm text-slate-500">No active staff assignments in the selected day and filter.</div>
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
                                        <div class="text-[11px] font-extrabold uppercase tracking-[0.16em] text-slate-400">{{ $day['full_label'] }}</div>
                                        <div class="mt-1 text-sm font-extrabold text-slate-950">{{ $day['display_date'] }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if ($day['is_today'])
                                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-700">Today</span>
                                        @endif
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2 py-1 text-[10px] font-bold text-slate-600">{{ $day['appointment_count'] }}</span>
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
                            <div class="modal-kicker">Appointment Details</div>
                            <h3 id="modal-customer-name" class="modal-title">-</h3>
                            <p id="modal-service-summary" class="modal-subtitle">-</p>
                        </div>
                        <button type="button" id="calendar-detail-close-top" class="modal-close" aria-label="Close">×</button>
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
                        <div class="modal-panel"><div class="modal-panel__label">Services</div><div id="modal-services" class="modal-pill-list">-</div></div>
                        <div class="modal-panel"><div class="modal-panel__label">Assigned Staff</div><div id="modal-staff" class="modal-pill-list">-</div></div>
                    </div>
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
                const sourceStaff = new Set((sourceEvent.staff_names || []).filter(Boolean));
                return (otherEvent.staff_names || []).some((name) => sourceStaff.has(name));
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
