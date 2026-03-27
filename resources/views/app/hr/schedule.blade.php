<x-internal-layout :title="$title" :subtitle="$subtitle">
    <div class="stack hr-schedule-page">
        <section class="panel hr-schedule-hero">
            <div class="panel-body">
                <div class="hr-schedule-hero__intro">
                    <div>
                        <div class="page-kicker">HR workspace</div>
                        <h2 class="panel-title-display">Staff Schedule</h2>
                        <p class="panel-subtitle">Mockup preview using current staff records. This gives HR and admin a clear weekly roster board now, while we wait for the final scheduling rules and controls.</p>
                    </div>

                    <div class="hr-schedule-toolbar">
                        <div class="calendar-switcher">
                            <a href="{{ route('app.hr.schedule', array_filter(['view' => 'month', 'date' => $selectedDateIso, 'search' => $filters['search'], 'department' => $filters['department'], 'status' => $filters['status']])) }}" class="btn {{ $viewMode === 'month' ? 'btn-primary' : 'btn-secondary' }}">Monthly view</a>
                            <a href="{{ route('app.hr.schedule', array_filter(['view' => 'week', 'date' => $selectedDateIso, 'search' => $filters['search'], 'department' => $filters['department'], 'status' => $filters['status']])) }}" class="btn {{ $viewMode === 'week' ? 'btn-primary' : 'btn-secondary' }}">Weekly view</a>
                        </div>

                        <div class="btn-row hr-schedule-nav">
                            <a href="{{ route('app.hr.schedule', array_filter(['view' => $viewMode, 'date' => $previousDate, 'search' => $filters['search'], 'department' => $filters['department'], 'status' => $filters['status']])) }}" class="btn btn-secondary">&larr; Previous {{ $viewMode }}</a>
                            <form method="GET" action="{{ route('app.hr.schedule') }}" class="hr-schedule-date-form">
                                <input type="hidden" name="view" value="{{ $viewMode }}">
                                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                <input type="hidden" name="department" value="{{ $filters['department'] }}">
                                <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                <input type="date" name="date" value="{{ $selectedDateIso }}" class="form-input hr-schedule-date-input" onchange="this.form.submit()">
                            </form>
                            <div class="topbar-badge">{{ $viewMode === 'month' ? $monthLabel : $weekLabel }}</div>
                            <a href="{{ route('app.hr.schedule', array_filter(['view' => $viewMode, 'date' => $nextDate, 'search' => $filters['search'], 'department' => $filters['department'], 'status' => $filters['status']])) }}" class="btn btn-secondary">Next {{ $viewMode }} &rarr;</a>
                        </div>
                    </div>
                </div>

                <div class="stats-grid hr-schedule-stats hr-schedule-stats--three">
                    @foreach ($hrSummaryCards as $card)
                        <button type="button" class="stat-card stat-card--interactive hr-summary-card" data-hr-summary='@json($card)'>
                            <div class="metric-label">{{ $card['label'] }}</div>
                            <div class="stat-value">{{ $card['value'] }}</div>
                            <div class="metric-meta">{{ $card['meta'] }}</div>
                            <div class="hr-summary-card__hint">Open detail list</div>
                        </button>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-body">
                <form method="GET" action="{{ route('app.hr.schedule') }}" class="form-grid">
                    <input type="hidden" name="view" value="{{ $viewMode }}">
                    <input type="hidden" name="date" value="{{ $selectedDateIso }}">

                    <div class="col-5 field-block">
                        <label class="field-label" for="search">Search team member</label>
                        <input id="search" name="search" type="text" class="form-input" value="{{ $filters['search'] }}" placeholder="Search by name, title, or department">
                    </div>

                    <div class="col-4 field-block">
                        <label class="field-label" for="department">Department</label>
                        <select id="department" name="department" class="form-select">
                            <option value="">All departments</option>
                            @foreach ($departmentOptions as $department)
                                <option value="{{ $department }}" @selected($filters['department'] === $department)>{{ $department }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-3 field-block">
                        <label class="field-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="active" @selected($filters['status'] === 'active')>Active staff</option>
                            <option value="all" @selected($filters['status'] === 'all')>All staff</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive only</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="filter-bar__head">
                            <div class="small-note">Designed as an easy weekly planning view for HR. Leave, off days, training, and working coverage are all visible at a glance.</div>
                            <div class="btn-row">
                                <button type="submit" class="btn btn-primary">Apply filters</button>
                                <a href="{{ route('app.hr.schedule') }}" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <div class="hr-schedule-summary-row">
            <section class="panel">
                <div class="panel-header">
                    <x-section-heading
                        kicker="Coverage snapshot"
                        title="Daily team balance"
                        subtitle="A quick check on how many people are working versus on leave across this week." />
                </div>
                <div class="panel-body">
                    <div class="hr-coverage-list">
                        @foreach ($coverageByDay as $coverage)
                            <div class="hr-coverage-card">
                                <div>
                                    <div class="selection-card__title">{{ $coverage['label'] }}</div>
                                    <div class="small-note">{{ $coverage['display'] }}</div>
                                </div>
                                <div class="hr-coverage-card__stats">
                                    <span class="hr-mini-pill hr-mini-pill--working">{{ $coverage['working'] }} working</span>
                                    <span class="hr-mini-pill hr-mini-pill--leave">{{ $coverage['leave'] }} leave</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <x-section-heading
                        kicker="Leave visibility"
                        title="Upcoming leave blocks"
                        subtitle="Built to make leave obvious for HR before scheduling conflicts happen." />
                </div>
                <div class="panel-body">
                    @if ($leaveHighlights->count())
                        <div class="hr-leave-list">
                            @foreach ($leaveHighlights as $entry)
                                <div class="hr-leave-item">
                                    <div class="selection-card__title">{{ $entry['staff']->full_name }}</div>
                                    <div class="small-note">{{ \Carbon\Carbon::parse($entry['shift']['date'])->format('D, d M') }} - {{ $entry['shift']['note'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state empty-state--dashed">
                            <div class="empty-state__title">No leave blocks in this preview week.</div>
                        </div>
                    @endif
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <x-section-heading
                        kicker="Module access"
                        title="HR controllers"
                        subtitle="Only admin and HR users can access this workspace." />
                </div>
                <div class="panel-body">
                    @if ($hrOwners->count())
                        <div class="inline-chip-row">
                            @foreach ($hrOwners as $owner)
                                <span class="chip">{{ $owner->full_name }}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="small-note">No dedicated HR staff found yet, so admin remains the fallback controller.</div>
                    @endif
                </div>
            </section>
        </div>

        <section class="panel">
            <div class="panel-header">
                <x-section-heading
                    kicker="{{ $viewMode === 'month' ? 'Monthly roster' : 'Weekly roster' }}"
                    title="{{ $viewMode === 'month' ? 'Monthly schedule calendar' : 'Weekly schedule board' }}"
                    subtitle="{{ $viewMode === 'month' ? 'Monthly view uses a real clinic calendar. Leave is color-coded so HR can scan busy and blocked dates faster.' : 'A premium planning board mockup built from your current staff list. Final editing tools can plug into this layout later.' }}" />
            </div>
            <div class="panel-body">
                @php
                    $activeBoardRows = $viewMode === 'month' ? $monthScheduleRows : $scheduleRows;
                    $activeBoardDays = $viewMode === 'month' ? $monthDays : $weekDays;
                    $dayCount = max(1, $activeBoardDays->count());
                @endphp

                @if ($viewMode === 'month')
                    <div class="month-grid hr-month-calendar-grid">
                        @foreach ($monthCalendarDays as $day)
                            <a href="{{ $day['url'] }}" class="month-day-card hr-month-calendar-card {{ $day['is_selected'] ? 'is-selected' : '' }} {{ $day['is_outside_month'] ? 'is-outside-month' : '' }} hr-month-calendar-card--{{ $day['tone'] }}">
                                <div class="month-day-card__head">
                                    <span class="month-day-card__number">{{ $day['day_number'] }}</span>
                                    <span class="small-note">{{ $day['label'] }}</span>
                                </div>
                                <div class="hr-month-calendar-card__stats">
                                    <span class="hr-mini-pill hr-mini-pill--working">{{ $day['working'] }} working</span>
                                    <span class="hr-mini-pill hr-mini-pill--leave">{{ $day['leave'] }} leave</span>
                                </div>
                                @if ($day['training'] > 0)
                                    <div class="small-note">{{ $day['training'] }} training</div>
                                @endif
                                @if ($day['leave_names']->count())
                                    <div class="hr-month-calendar-card__names">
                                        @foreach ($day['leave_names'] as $name)
                                            <span class="chip">{{ $name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="small-note">No leave recorded</div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @elseif ($activeBoardRows->count())
                    <div class="hr-schedule-board-wrap">
                        <div class="hr-schedule-board hr-schedule-board--{{ $viewMode }}" style="grid-template-columns: minmax(240px, 1.2fr) repeat({{ $dayCount }}, minmax(170px, 1fr));">
                            <div class="hr-schedule-board__header hr-schedule-board__header--staff">Team member</div>
                            @foreach ($activeBoardDays as $day)
                                <div class="hr-schedule-board__header">
                                    <div>{{ $day->format('D') }}</div>
                                    <div class="small-note">{{ $day->format('d M') }}</div>
                                </div>
                            @endforeach

                            @foreach ($activeBoardRows as $row)
                                <div class="hr-schedule-board__staff">
                                    <div class="selection-card__title">{{ $row['staff']->full_name }}</div>
                                    <div class="small-note">{{ $row['staff']->job_title ?: 'No title set' }}</div>
                                    <div class="small-note">{{ $row['staff']->department ?: 'No department' }}</div>
                                    <div class="hr-schedule-board__meta">
                                        <span class="chip">{{ $row['staff']->operational_role_label }}</span>
                                        @if (! $row['staff']->is_active)
                                            <span class="chip">Inactive</span>
                                        @endif
                                    </div>
                                </div>

                                @foreach ($row['days'] as $shift)
                                    <div class="hr-schedule-cell hr-schedule-cell--{{ $shift['tone'] }}" data-day-label="{{ \Carbon\Carbon::parse($shift['date'])->format('D d M') }}">
                                        <div class="hr-schedule-cell__label">{{ $shift['label'] }}</div>
                                        <div class="hr-schedule-cell__time">{{ $shift['time'] }}</div>
                                        <div class="hr-schedule-cell__note">{{ $shift['note'] }}</div>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <div id="hr-summary-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card revenue-detail-modal">
                <div class="modal-header">
                    <div class="modal-header__row">
                        <div>
                            <div class="modal-kicker">HR summary detail</div>
                            <h3 id="hr-summary-title" class="modal-title">-</h3>
                            <p id="hr-summary-subtitle" class="modal-subtitle">-</p>
                        </div>
                        <button type="button" id="hr-summary-close-top" class="modal-close" aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="btn-row btn-row--between revenue-detail-toolbar">
                        <div class="small-note">Detailed HR roster insight for the selected summary card.</div>
                        <div class="btn-row">
                            <button type="button" id="hr-summary-export" class="modal-btn modal-btn--secondary">Export CSV</button>
                            <button type="button" id="hr-summary-print" class="modal-btn modal-btn--secondary">Print</button>
                        </div>
                    </div>
                    <div class="table-shell">
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th>Detail</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="hr-summary-rows"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" id="hr-summary-close-bottom" class="modal-btn modal-btn--secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const summaryCards = document.querySelectorAll('[data-hr-summary]');
            const summaryModal = document.getElementById('hr-summary-modal');
            const summaryTitle = document.getElementById('hr-summary-title');
            const summarySubtitle = document.getElementById('hr-summary-subtitle');
            const summaryRows = document.getElementById('hr-summary-rows');
            const summaryExport = document.getElementById('hr-summary-export');
            const summaryPrint = document.getElementById('hr-summary-print');
            const summaryCloseTop = document.getElementById('hr-summary-close-top');
            const summaryCloseBottom = document.getElementById('hr-summary-close-bottom');
            let activeSummaryPayload = null;

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const closeSummaryModal = () => {
                summaryModal?.classList.add('hidden');
                summaryModal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            const buildSummaryTableRows = (details) => details.length
                ? details.map((detail) => `
                    <tr>
                        <td>${escapeHtml(detail.staff_name || '-')}</td>
                        <td>${escapeHtml(detail.job_title || '-')}</td>
                        <td>${escapeHtml(detail.department || '-')}</td>
                        <td>${escapeHtml(detail.role || '-')}</td>
                        <td>${escapeHtml(detail.detail_primary || '-')}</td>
                        <td>${escapeHtml(detail.detail_secondary || '-')}</td>
                    </tr>
                `).join('')
                : '<tr><td colspan="6" class="small-note" style="text-align:center;padding:1rem;">No detail recorded for this selection.</td></tr>';

            const downloadCsv = (payload) => {
                const details = Array.isArray(payload?.details) ? payload.details : [];
                const lines = [
                    ['Staff Member', 'Job Title', 'Department', 'Role', 'Detail', 'Notes'],
                    ...details.map((detail) => [
                        detail.staff_name || '',
                        detail.job_title || '',
                        detail.department || '',
                        detail.role || '',
                        detail.detail_primary || '',
                        detail.detail_secondary || '',
                    ]),
                ];

                const csv = lines
                    .map((line) => line.map((value) => `"${String(value).replace(/"/g, '""')}"`).join(','))
                    .join('\n');

                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `${(payload?.key || 'hr-summary').replace(/[^a-z0-9_-]+/gi, '-')}.csv`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            };

            const printSummary = (payload) => {
                const details = Array.isArray(payload?.details) ? payload.details : [];
                const printWindow = window.open('', '_blank', 'width=980,height=720');

                if (!printWindow) {
                    return;
                }

                printWindow.document.write(`
                    <html>
                        <head>
                            <title>${escapeHtml(payload?.label || 'HR summary detail')}</title>
                            <style>
                                body { font-family: Georgia, serif; padding: 24px; color: #36242d; }
                                h1 { margin: 0 0 8px; font-size: 28px; }
                                p { margin: 0 0 20px; color: #7c6670; }
                                table { width: 100%; border-collapse: collapse; }
                                th, td { border: 1px solid #d9c2cb; padding: 10px 12px; text-align: left; vertical-align: top; }
                                th { background: #fdf4f7; }
                            </style>
                        </head>
                        <body>
                            <h1>${escapeHtml(payload?.label || 'HR summary detail')}</h1>
                            <p>${escapeHtml(payload?.summary || '')}</p>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Job Title</th>
                                        <th>Department</th>
                                        <th>Role</th>
                                        <th>Detail</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${details.map((detail) => `
                                        <tr>
                                            <td>${escapeHtml(detail.staff_name || '-')}</td>
                                            <td>${escapeHtml(detail.job_title || '-')}</td>
                                            <td>${escapeHtml(detail.department || '-')}</td>
                                            <td>${escapeHtml(detail.role || '-')}</td>
                                            <td>${escapeHtml(detail.detail_primary || '-')}</td>
                                            <td>${escapeHtml(detail.detail_secondary || '-')}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.focus();
                printWindow.print();
            };

            const openSummaryModal = (payload) => {
                activeSummaryPayload = payload;
                const details = Array.isArray(payload?.details) ? payload.details : [];
                summaryTitle.textContent = payload?.label || 'HR summary detail';
                summarySubtitle.textContent = `${details.length} record${details.length === 1 ? '' : 's'} | ${payload?.summary || ''}`;
                summaryRows.innerHTML = buildSummaryTableRows(details);
                summaryModal.classList.remove('hidden');
                summaryModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            };

            summaryCards.forEach((button) => {
                button.addEventListener('click', () => {
                    let payload = {};

                    try {
                        payload = JSON.parse(button.dataset.hrSummary || '{}');
                    } catch (error) {
                        payload = {};
                    }

                    openSummaryModal(payload);
                });
            });

            summaryExport?.addEventListener('click', () => {
                if (activeSummaryPayload) {
                    downloadCsv(activeSummaryPayload);
                }
            });

            summaryPrint?.addEventListener('click', () => {
                if (activeSummaryPayload) {
                    printSummary(activeSummaryPayload);
                }
            });

            summaryCloseTop?.addEventListener('click', closeSummaryModal);
            summaryCloseBottom?.addEventListener('click', closeSummaryModal);
            summaryModal?.addEventListener('click', (event) => {
                if (event.target === summaryModal || event.target.classList.contains('modal-backdrop')) {
                    closeSummaryModal();
                }
            });
        })();
    </script>
</x-internal-layout>
