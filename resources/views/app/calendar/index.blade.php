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
                        <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'week', 'date' => $selectedDateIso, 'anchor' => $weekAnchor, 'staff_id' => $staffId ?: null])) }}" class="btn {{ $viewMode === 'week' ? 'btn-primary' : 'btn-secondary' }}">Weekly view</a>
                        <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'month', 'date' => $selectedDateIso, 'anchor' => $monthAnchor, 'staff_id' => $staffId ?: null])) }}" class="btn {{ $viewMode === 'month' ? 'btn-primary' : 'btn-secondary' }}">Monthly view</a>
                        <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="btn btn-secondary">Schedule</a>
                    </div>
                </div>

                <div class="calendar-control-grid calendar-control-grid--single calendar-control-grid--with-popover">
                    <form method="GET" action="{{ route('app.calendar') }}" class="calendar-filter-popover hidden" data-calendar-pic-panel>
                        <input type="hidden" name="view" value="{{ $viewMode }}">
                        <input type="hidden" name="date" value="{{ $selectedDateIso }}">
                        <input type="hidden" name="anchor" value="{{ $viewMode === 'month' ? $monthAnchor : $weekAnchor }}">
                        <div class="field-block">
                            <label for="staff_id" class="field-label">PIC</label>
                            <select id="staff_id" name="staff_id" class="form-select">
                                <option value="">All PIC</option>
                                @foreach ($staffList as $staff)
                                    <option value="{{ $staff->id }}" @selected((string) $staffId === (string) $staff->id)>{{ $staff->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="small-note">{{ $selectedStaff ? $selectedStaff->full_name.' - '.($selectedStaff->job_title ?: 'PIC') : 'Showing the full operational board' }}</div>
                        <div class="btn-row btn-row--compact-mobile">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="{{ route('app.calendar', ['view' => $viewMode, 'date' => $selectedDateIso, 'anchor' => $viewMode === 'month' ? $monthAnchor : $weekAnchor]) }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                    <div class="stack">
                        <div class="btn-row btn-row--between">
                            @if ($viewMode === 'month')
                                <div class="btn-row">
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'month', 'date' => \Carbon\Carbon::parse($previousMonth)->startOfMonth()->toDateString(), 'anchor' => $previousMonth, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">&larr; Previous month</a>
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'month', 'date' => now()->toDateString(), 'anchor' => $currentMonth, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">Today</a>
                                    <a href="{{ route('app.calendar', array_merge($queryBase, ['view' => 'month', 'date' => \Carbon\Carbon::parse($nextMonth)->startOfMonth()->toDateString(), 'anchor' => $nextMonth, 'staff_id' => $staffId ?: null])) }}" class="btn btn-secondary">Next month &rarr;</a>
                                    <button type="button" class="btn btn-secondary" data-calendar-pic-toggle>PIC</button>
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
                                    <button type="button" class="btn btn-secondary" data-calendar-pic-toggle>PIC</button>
                                </div>
                            @endif
                        </div>

                        @if ($viewMode === 'month')
                            <div class="month-grid">
                                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow)
                                    <div class="metric-label">{{ $dow }}</div>
                                @endforeach

                                @foreach ($monthDays as $day)
                                    @if ($day['is_clickable'])
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
                                    @else
                                        <div class="month-day-card month-day-card--disabled {{ $day['is_outside_month'] ? 'is-outside-month' : '' }}">
                                            <div class="filter-bar__head">
                                                <div>
                                                    <div class="selection-card__title">{{ $day['day_number'] }}</div>
                                                    <div class="small-note">{{ $day['label'] }}</div>
                                                </div>
                                            </div>
                                            <div class="small-note" style="margin-top: 0.85rem;">Unavailable</div>
                                        </div>
                                    @endif
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
                </div>
            </div>
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

                        @foreach ($overflowSummaries as $overflow)
                            <button
                                type="button"
                                class="calendar-overflow-btn"
                                style="top: {{ $overflow['top_px'] }}px; height: {{ $overflow['height_px'] }}px; left: calc({{ $overflow['left_pct'] }}% + 8px); width: calc({{ $overflow['width_pct'] }}% - 12px);"
                                data-overflow='@json($overflow)'
                                title="Open hidden appointments"
                            >
                                <span class="calendar-overflow-btn__count">+{{ $overflow['count'] }}</span>
                                <span class="calendar-overflow-btn__label">more appointments</span>
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
            <div class="modal-card calendar-editor-modal">
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
                    <div data-calendar-detail-view>
                        <div class="modal-meta-grid">
                            <div class="modal-panel modal-panel--wide"><div class="modal-panel__label">Date & Time</div><div id="modal-time" class="modal-panel__value">-</div></div>
                            <div class="modal-panel"><div class="modal-panel__label">Status</div><div id="modal-status" class="modal-panel__value" style="margin-top:10px;"></div></div>
                        </div>
                        <div class="modal-meta-grid">
                            <div class="modal-panel"><div class="modal-panel__label">Customer Phone</div><div id="modal-phone" class="modal-panel__value">-</div></div>
                            <div class="modal-panel"><div class="modal-panel__label">Package / Membership</div><div id="modal-membership" class="modal-panel__value">-</div></div>
                            <div class="modal-panel"><div class="modal-panel__label">Source</div><div id="modal-source" class="modal-panel__value">-</div></div>
                        </div>
                        @if ($canViewMembershipBalance)
                            <div class="modal-meta-grid">
                                <div class="modal-panel modal-panel--wide">
                                    <div class="modal-panel__label">Total Balance Membership</div>
                                    <div id="modal-membership-balance" class="modal-panel__value">Coming soon</div>
                                    <div id="modal-membership-balance-note" class="small-note" style="margin-top: 0.55rem;">Pending input</div>
                                </div>
                            </div>
                        @endif
                        <div class="modal-meta-grid">
                            <div class="modal-panel"><div class="modal-panel__label">Visit Services</div><div id="modal-services" class="modal-pill-list">-</div></div>
                            <div class="modal-panel"><div class="modal-panel__label">Assigned Staff</div><div id="modal-staff" class="modal-pill-list">-</div></div>
                        </div>
                        <div class="modal-panel"><div class="modal-panel__label">Linked Visit Flow</div><div id="modal-linked-services" class="modal-pill-list">-</div></div>
                        <div class="modal-panel"><div class="modal-panel__label">Notes</div><div id="modal-notes" class="modal-panel__value" style="white-space:pre-line;">-</div></div>
                    </div>

                    @if ($canManageAppointments)
                    <div class="calendar-editor hidden" data-calendar-edit-view>
                        <div id="calendar-edit-feedback" class="flash flash--error hidden"></div>

                        <div class="calendar-editor__grid">
                            <section class="calendar-editor__panel calendar-editor__panel--customer">
                                <div class="modal-panel__label">Customer</div>
                                <div class="calendar-editor__fields">
                                    <div class="field-block customer-picker">
                                        <label for="calendar-edit-customer-name" class="field-label">Customer name</label>
                                        <input id="calendar-edit-customer-id" type="hidden">
                                        <input id="calendar-edit-customer-name" type="text" class="field-input" autocomplete="off" placeholder="Search customer or type a new name">
                                        <div id="calendar-edit-customer-suggestions" class="customer-suggestions hidden" role="listbox" aria-label="Customer suggestions"></div>
                                        <div id="calendar-edit-customer-selected" class="customer-picked hidden"></div>
                                    </div>
                                    <div class="field-block">
                                        <label for="calendar-edit-customer-phone" class="field-label">Customer phone</label>
                                        <input id="calendar-edit-customer-phone" type="text" class="field-input" placeholder="Phone number">
                                    </div>
                                    <div class="field-block">
                                        <label class="field-label">Membership</label>
                                        <div id="calendar-edit-membership-summary" class="calendar-editor__summary-card">No package or membership linked</div>
                                    </div>
                                </div>
                            </section>

                            <section class="calendar-editor__panel">
                                <div class="modal-panel__label">Visit Settings</div>
                                <div class="calendar-editor__fields calendar-editor__fields--compact">
                                    <div class="field-block calendar-editor__control">
                                        <label for="calendar-edit-date" class="field-label">Date</label>
                                        <div class="calendar-editor__control-shell calendar-editor__control-shell--date">
                                            <input id="calendar-edit-date" type="date" class="field-input calendar-editor__input">
                                        </div>
                                    </div>
                                    <div class="field-block calendar-editor__control">
                                        <label for="calendar-edit-status" class="field-label">Status</label>
                                        <div class="calendar-editor__control-shell calendar-editor__control-shell--select">
                                            <select id="calendar-edit-status" class="field-input select-input calendar-editor__input"></select>
                                        </div>
                                    </div>
                                    <div class="field-block calendar-editor__control">
                                        <label for="calendar-edit-source" class="field-label">Source</label>
                                        <div class="calendar-editor__control-shell calendar-editor__control-shell--select">
                                            <select id="calendar-edit-source" class="field-input select-input calendar-editor__input"></select>
                                        </div>
                                    </div>
                                </div>
                                <div class="field-block" style="margin-top: 1rem;">
                                    <label for="calendar-edit-notes" class="field-label">Notes</label>
                                    <textarea id="calendar-edit-notes" class="field-input booking-textarea" style="min-height: 120px;"></textarea>
                                </div>
                            </section>
                        </div>

                        <section class="calendar-editor__panel">
                            <div class="filter-bar__head">
                                <div>
                                    <div class="modal-panel__label">Linked Visit Flow</div>
                                    <div class="small-note" style="margin-top: 0.35rem;">Adjust service, assigned PIC, and exact timing directly here.</div>
                                </div>
                            </div>
                            <div id="calendar-edit-items" class="calendar-editor__items"></div>
                        </section>
                    </div>
                    @endif
                </div>
                <div class="modal-actions">
                    <a id="modal-create-link" href="#" class="modal-btn modal-btn--secondary">Book this time</a>
                    @if ($canManageAppointments)
                    <button type="button" id="modal-edit-link" class="modal-btn modal-btn--secondary">Edit</button>
                    <button type="button" id="modal-edit-cancel" class="modal-btn modal-btn--secondary hidden">Cancel</button>
                    <button type="button" id="modal-edit-save" class="modal-btn modal-btn--primary hidden">Save changes</button>
                    @endif
                    <a id="modal-manage-link" href="#" class="modal-btn modal-btn--primary">Open appointments</a>
                    <button type="button" id="calendar-detail-close-bottom" class="modal-btn modal-btn--secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="calendar-overflow-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card">
                <div class="modal-header">
                    <div class="modal-header__row">
                        <div>
                            <div class="modal-kicker">Busy time slot</div>
                            <h3 id="overflow-modal-title" class="modal-title">More appointments</h3>
                            <p class="modal-subtitle">Extra appointments hidden to keep the board readable.</p>
                        </div>
                        <button type="button" id="calendar-overflow-close-top" class="modal-close" aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="table-shell">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Service</th>
                                        <th>Time</th>
                                        <th>Staff</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="overflow-modal-list"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" id="calendar-overflow-close-bottom" class="modal-btn modal-btn--secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('calendar-detail-modal');
            const overflowModal = document.getElementById('calendar-overflow-modal');
            const modalHeader = document.getElementById('modal-header');
            const detailView = document.querySelector('[data-calendar-detail-view]');
            const editView = document.querySelector('[data-calendar-edit-view]');
            const closeTop = document.getElementById('calendar-detail-close-top');
            const closeBottom = document.getElementById('calendar-detail-close-bottom');
            const overflowCloseTop = document.getElementById('calendar-overflow-close-top');
            const overflowCloseBottom = document.getElementById('calendar-overflow-close-bottom');
            const manageLink = document.getElementById('modal-manage-link');
            const createLink = document.getElementById('modal-create-link');
            const editLink = document.getElementById('modal-edit-link');
            const editCancel = document.getElementById('modal-edit-cancel');
            const editSave = document.getElementById('modal-edit-save');
            const editFeedback = document.getElementById('calendar-edit-feedback');
            const editCustomerId = document.getElementById('calendar-edit-customer-id');
            const editCustomerName = document.getElementById('calendar-edit-customer-name');
            const editCustomerPhone = document.getElementById('calendar-edit-customer-phone');
            const editCustomerSuggestions = document.getElementById('calendar-edit-customer-suggestions');
            const editCustomerSelected = document.getElementById('calendar-edit-customer-selected');
            const editMembershipSummary = document.getElementById('calendar-edit-membership-summary');
            const editDate = document.getElementById('calendar-edit-date');
            const editStatus = document.getElementById('calendar-edit-status');
            const editSource = document.getElementById('calendar-edit-source');
            const editNotes = document.getElementById('calendar-edit-notes');
            const editItems = document.getElementById('calendar-edit-items');
            const timelineGrid = document.querySelector('.timeline-grid');
            const timelineButtons = Array.from(document.querySelectorAll('.calendar-event-btn'));
            const overflowButtons = Array.from(document.querySelectorAll('.calendar-overflow-btn'));
            const picPanels = Array.from(document.querySelectorAll('[data-calendar-pic-panel]'));
            const picToggles = Array.from(document.querySelectorAll('[data-calendar-pic-toggle]'));
            const canManageAppointments = @json($canManageAppointments);
            const rowHeightPx = @json($rowHeightPx);
            const selectedDateIso = @json($selectedDateIso);
            const slots = @json($slots);
            const csrfToken = @json(csrf_token());
            const customerSearchUrl = @json(route('app.appointments.customer-search'));
            const serviceOptions = @json($serviceOptions);
            const staffOptions = @json($staffOptions);
            const statusOptions = @json($statusOptions);
            const sourceOptions = @json($sourceOptions);
            let activeCustomerRequest = null;
            let currentEventData = null;

            const setText = (id, value, fallback = '-') => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value && String(value).trim() !== '' ? value : fallback;
                }
            };

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const extractResponseErrorMessage = (result, responseText, fallback) => {
                const firstError = Object.values(result?.errors || {}).flat()[0];

                if (result?.message || firstError) {
                    return result.message || firstError;
                }

                if (typeof responseText === 'string' && responseText.trim() !== '') {
                    const titleMatch = responseText.match(/<title[^>]*>(.*?)<\/title>/i);

                    if (titleMatch?.[1]) {
                        return titleMatch[1].trim();
                    }

                    const plainText = responseText
                        .replace(/<[^>]+>/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();

                    if (plainText !== '') {
                        return plainText.slice(0, 220);
                    }
                }

                return fallback;
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

            const populateSelect = (select, options, selectedValue, placeholder = null) => {
                if (!select) {
                    return;
                }

                const optionMarkup = [];

                if (placeholder !== null) {
                    optionMarkup.push(`<option value="">${escapeHtml(placeholder)}</option>`);
                }

                optionMarkup.push(...(options || []).map((option) => {
                    const value = String(option.value ?? option.id ?? '');
                    const label = option.label ?? option.name ?? option.full_name ?? value;
                    const selected = value === String(selectedValue ?? '') ? ' selected' : '';
                    return `<option value="${escapeHtml(value)}"${selected}>${escapeHtml(label)}</option>`;
                }));

                select.innerHTML = optionMarkup.join('');
            };

            const formatMembershipBalance = (value) => {
                const raw = String(value ?? '').trim();

                if (!raw) {
                    return 'Pending input';
                }

                const normalized = raw.replace(/[^0-9.\-]/g, '');

                if (normalized && !Number.isNaN(Number(normalized))) {
                    return `RM ${Number(normalized).toLocaleString()}`;
                }

                return raw;
            };

            const setMembershipSummary = (customer) => {
                if (!editMembershipSummary) {
                    return;
                }

                const membership = [customer?.membership_type, customer?.membership_code].filter(Boolean).join(' | ') || 'No package or membership linked';
                const balance = formatMembershipBalance(customer?.current_package);
                editMembershipSummary.innerHTML = `
                    <div class="calendar-editor__summary-title">${escapeHtml(membership)}</div>
                    <div class="calendar-editor__summary-meta">Membership balance: ${escapeHtml(balance)}</div>
                `;
            };

            const renderSelectedCustomer = (customer) => {
                if (!editCustomerSelected) {
                    return;
                }

                const parts = [customer?.full_name || 'Customer'];

                if (customer?.phone) {
                    parts.push(customer.phone);
                }

                if (customer?.membership_code) {
                    parts.push(`Member ${customer.membership_code}`);
                }

                editCustomerSelected.textContent = `Linked customer: ${parts.join(' | ')}`;
                editCustomerSelected.classList.remove('hidden');
                setMembershipSummary(customer);
            };

            const clearSelectedCustomer = () => {
                if (editCustomerId) {
                    editCustomerId.value = '';
                }

                editCustomerSelected?.classList.add('hidden');
                if (editCustomerSelected) {
                    editCustomerSelected.textContent = '';
                }
                setMembershipSummary({ membership_type: '', membership_code: '', current_package: '' });
            };

            const hideCustomerSuggestions = () => {
                editCustomerSuggestions?.classList.add('hidden');
                if (editCustomerSuggestions) {
                    editCustomerSuggestions.innerHTML = '';
                }
            };

            const selectCustomer = (customer) => {
                if (editCustomerId) {
                    editCustomerId.value = customer.id || '';
                }

                if (editCustomerName) {
                    editCustomerName.value = customer.full_name || '';
                }

                if (editCustomerPhone && customer.phone) {
                    editCustomerPhone.value = customer.phone;
                }

                renderSelectedCustomer(customer);
                hideCustomerSuggestions();
            };

            const renderCustomerSuggestions = (customers) => {
                if (!editCustomerSuggestions) {
                    return;
                }

                editCustomerSuggestions.innerHTML = '';

                if (!customers.length) {
                    hideCustomerSuggestions();
                    return;
                }

                customers.forEach((customer) => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'customer-suggestion';
                    option.innerHTML = `
                        <div class="customer-suggestion__name">${escapeHtml(customer.full_name || 'Customer')}</div>
                        <div class="customer-suggestion__meta">
                            ${escapeHtml(customer.phone || 'No phone')}
                            ${customer.membership_code ? ` | Member ${escapeHtml(customer.membership_code)}` : ''}
                            ${customer.current_package ? ` | ${escapeHtml(customer.current_package)}` : ''}
                        </div>
                    `;
                    option.addEventListener('click', () => selectCustomer(customer));
                    editCustomerSuggestions.appendChild(option);
                });

                editCustomerSuggestions.classList.remove('hidden');
            };

            const renderEditItems = (items = []) => {
                if (!editItems) {
                    return;
                }

                editItems.innerHTML = (items || []).map((item, index) => `
                    <div class="calendar-editor__item" data-edit-item-id="${escapeHtml(item.id)}">
                        <div class="calendar-editor__item-head">
                            <div>
                                <div class="calendar-editor__item-title">Service ${index + 1}</div>
                                <div class="calendar-editor__item-meta">Linked visit item</div>
                            </div>
                        </div>
                        <div class="calendar-editor__item-grid">
                            <div class="field-block calendar-editor__control">
                                <label class="field-label">Service</label>
                                <div class="calendar-editor__control-shell calendar-editor__control-shell--select">
                                    <select class="field-input select-input calendar-editor__input" data-edit-field="service_id"></select>
                                </div>
                            </div>
                            <div class="field-block calendar-editor__control">
                                <label class="field-label">PIC</label>
                                <div class="calendar-editor__control-shell calendar-editor__control-shell--select">
                                    <select class="field-input select-input calendar-editor__input" data-edit-field="staff_id"></select>
                                </div>
                            </div>
                            <div class="field-block calendar-editor__control">
                                <label class="field-label">Start</label>
                                <div class="calendar-editor__control-shell calendar-editor__control-shell--time">
                                    <input type="time" class="field-input calendar-editor__input" data-edit-field="start_time" value="${escapeHtml(item.start_time || '09:00')}">
                                </div>
                            </div>
                            <div class="field-block calendar-editor__control">
                                <label class="field-label">End</label>
                                <div class="calendar-editor__control-shell calendar-editor__control-shell--time">
                                    <input type="time" class="field-input calendar-editor__input" data-edit-field="end_time" value="${escapeHtml(item.end_time || '09:30')}">
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');

                Array.from(editItems.querySelectorAll('[data-edit-item-id]')).forEach((row, index) => {
                    const item = items[index];
                    populateSelect(row.querySelector('[data-edit-field="service_id"]'), serviceOptions, item?.service_id);
                    populateSelect(row.querySelector('[data-edit-field="staff_id"]'), staffOptions.map((staff) => ({ value: staff.id, label: staff.label })), item?.staff_id, 'Unassigned');
                });
            };

            const setEditMode = (enabled) => {
                detailView?.classList.toggle('hidden', enabled);
                editView?.classList.toggle('hidden', !enabled);
                createLink?.classList.toggle('hidden', enabled);
                editLink?.classList.toggle('hidden', enabled || !canManageAppointments);
                manageLink?.classList.toggle('hidden', enabled);
                editCancel?.classList.toggle('hidden', !enabled);
                editSave?.classList.toggle('hidden', !enabled);
                if (!enabled && editFeedback) {
                    editFeedback.classList.add('hidden');
                    editFeedback.textContent = '';
                }
            };

            const prepareEditForm = (eventData) => {
                if (editCustomerId) {
                    editCustomerId.value = eventData.customer_id || '';
                }
                if (editCustomerName) {
                    editCustomerName.value = eventData.customer_name || '';
                }
                if (editCustomerPhone) {
                    editCustomerPhone.value = eventData.customer_phone && eventData.customer_phone !== 'No phone recorded' ? eventData.customer_phone : '';
                }
                if (editDate) {
                    editDate.value = eventData.date_iso || selectedDateIso;
                }
                if (editNotes) {
                    editNotes.value = eventData.notes || '';
                }

                populateSelect(editStatus, statusOptions, eventData.status_value);
                populateSelect(editSource, sourceOptions, eventData.source_value || 'admin');
                renderSelectedCustomer({
                    id: eventData.customer_id || '',
                    full_name: eventData.customer_name || '',
                    phone: eventData.customer_phone && eventData.customer_phone !== 'No phone recorded' ? eventData.customer_phone : '',
                    membership_type: eventData.membership_type || '',
                    membership_code: eventData.membership_code || '',
                    current_package: eventData.membership_balance || '',
                });
                renderEditItems(eventData.editable_items || []);
            };

            const openModal = (eventData) => {
                currentEventData = eventData;
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
                @if ($canViewMembershipBalance)
                    setText('modal-membership-balance', 'Coming soon');
                    setText('modal-membership-balance-note', 'Pending input', 'Pending input');
                @endif
                manageLink.href = eventData.manage_url || '#';
                createLink.href = eventData.create_url || '#';
                modalHeader.style.background = `radial-gradient(circle at top left, ${eventData.service_styles.surface_strong || eventData.service_styles.surface} 0%, rgba(255, 247, 250, 0.88) 38%, transparent 62%)`;
                prepareEditForm(eventData);
                setEditMode(false);
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            const closeModal = () => {
                currentEventData = null;
                hideCustomerSuggestions();
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            const closeOverflowModal = () => {
                overflowModal.classList.add('hidden');
                overflowModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            const closePicPanels = () => {
                picPanels.forEach((panel) => panel.classList.add('hidden'));
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
                            throw new Error(extractResponseErrorMessage(result, responseText, 'Unable to reschedule this appointment.'));
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

            const collectEditPayload = () => {
                const activeDate = editDate?.value || selectedDateIso;

                return {
                    customer_id: editCustomerId?.value || null,
                    customer_full_name: editCustomerName?.value || '',
                    customer_phone: editCustomerPhone?.value || '',
                    status: editStatus?.value || '',
                    source: editSource?.value || '',
                    notes: editNotes?.value || '',
                    items: Array.from(editItems?.querySelectorAll('[data-edit-item-id]') || []).map((row) => ({
                        id: row.dataset.editItemId,
                        service_id: row.querySelector('[data-edit-field="service_id"]')?.value || '',
                        staff_id: row.querySelector('[data-edit-field="staff_id"]')?.value || '',
                        date: activeDate,
                        start_time: row.querySelector('[data-edit-field="start_time"]')?.value || '',
                        end_time: row.querySelector('[data-edit-field="end_time"]')?.value || '',
                    })),
                };
            };

            editLink?.addEventListener('click', () => {
                if (!currentEventData || !canManageAppointments) {
                    return;
                }

                setEditMode(true);
            });

            editCancel?.addEventListener('click', () => {
                if (!currentEventData) {
                    return;
                }

                prepareEditForm(currentEventData);
                setEditMode(false);
            });

            editSave?.addEventListener('click', async () => {
                if (!currentEventData?.update_url) {
                    return;
                }

                const payload = collectEditPayload();
                editSave.disabled = true;
                editFeedback?.classList.add('hidden');

                try {
                    const body = new URLSearchParams();
                    body.set('_method', 'PATCH');
                    body.set('_token', csrfToken);
                    body.set('customer_id', payload.customer_id || '');
                    body.set('customer_full_name', payload.customer_full_name || '');
                    body.set('customer_phone', payload.customer_phone || '');
                    body.set('status', payload.status || '');
                    body.set('source', payload.source || '');
                    body.set('notes', payload.notes || '');

                    (payload.items || []).forEach((item, index) => {
                        body.set(`items[${index}][id]`, item.id || '');
                        body.set(`items[${index}][service_id]`, item.service_id || '');
                        body.set(`items[${index}][staff_id]`, item.staff_id || '');
                        body.set(`items[${index}][date]`, item.date || '');
                        body.set(`items[${index}][start_time]`, item.start_time || '');
                        body.set(`items[${index}][end_time]`, item.end_time || '');
                    });

                    const response = await fetch(currentEventData.update_url, {
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
                        throw new Error(extractResponseErrorMessage(result, responseText, 'Unable to save calendar changes.'));
                    }

                    window.location.reload();
                } catch (error) {
                    if (editFeedback) {
                        editFeedback.textContent = error.message || 'Unable to save calendar changes.';
                        editFeedback.classList.remove('hidden');
                    }
                } finally {
                    editSave.disabled = false;
                }
            });

            editCustomerName?.addEventListener('input', async () => {
                const query = editCustomerName.value.trim();

                if (editCustomerId?.value) {
                    clearSelectedCustomer();
                }

                if (query.length < 2) {
                    hideCustomerSuggestions();
                    return;
                }

                activeCustomerRequest?.abort?.();
                activeCustomerRequest = new AbortController();

                try {
                    const url = new URL(customerSearchUrl, window.location.origin);
                    url.searchParams.set('q', query);

                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: activeCustomerRequest.signal,
                    });

                    const result = await response.json().catch(() => ({ customers: [] }));
                    renderCustomerSuggestions(Array.isArray(result.customers) ? result.customers : []);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        hideCustomerSuggestions();
                    }
                }
            });

            editCustomerPhone?.addEventListener('input', () => {
                if (editCustomerId?.value) {
                    clearSelectedCustomer();
                }
            });

            timelineButtons.forEach((button) => attachDragBehavior(button));
            picToggles.forEach((toggle) => {
                toggle.addEventListener('click', (event) => {
                    event.preventDefault();
                    const targetPanel = picPanels[0];
                    const isHidden = targetPanel?.classList.contains('hidden');
                    closePicPanels();
                    if (targetPanel && isHidden) {
                        targetPanel.classList.remove('hidden');
                    }
                });
            });
            overflowButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    let payload = {};

                    try {
                        payload = JSON.parse(button.dataset.overflow || '{}');
                    } catch (error) {
                        payload = {};
                    }

                    document.getElementById('overflow-modal-title').textContent = `+${payload.count || 0} more appointments`;
                    document.getElementById('overflow-modal-list').innerHTML = (payload.items || []).map((item) => `
                        <tr>
                            <td>${item.customer_name || '-'}</td>
                            <td>${item.service_summary || '-'}</td>
                            <td>${item.start_time || '-'} - ${item.end_time || '-'}</td>
                            <td>${item.staff_summary || '-'}</td>
                            <td>${item.status_label || '-'}</td>
                        </tr>
                    `).join('');

                    overflowModal.classList.remove('hidden');
                    overflowModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                });
            });
            [closeTop, closeBottom].forEach((button) => button?.addEventListener('click', closeModal));
            [overflowCloseTop, overflowCloseBottom].forEach((button) => button?.addEventListener('click', closeOverflowModal));

            modal?.addEventListener('click', (event) => {
                if (event.target === modal || event.target === modal.firstElementChild) {
                    closeModal();
                }
            });

            overflowModal?.addEventListener('click', (event) => {
                if (event.target === overflowModal || event.target === overflowModal.firstElementChild) {
                    closeOverflowModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }

                if (event.key === 'Escape' && overflowModal && !overflowModal.classList.contains('hidden')) {
                    closeOverflowModal();
                }

                if (event.key === 'Escape') {
                    closePicPanels();
                }
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('.customer-picker')) {
                    hideCustomerSuggestions();
                }

                if (!event.target.closest('[data-calendar-pic-toggle]') && !event.target.closest('[data-calendar-pic-panel]')) {
                    closePicPanels();
                }
            });
        })();
    </script>
</x-internal-layout>
