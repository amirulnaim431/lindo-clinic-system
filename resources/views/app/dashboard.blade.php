<x-internal-layout
    :title="'Dashboard'"
    :subtitle="null">

    @php
        $selectedDate = request('date', $date ?? now()->toDateString());
        $selectedDateFrom = request('date_from', $dateFrom ?? $selectedDate);
        $selectedDateTo = request('date_to', $dateTo ?? $selectedDate);
        $selectedStaffId = request('staff_id');
        $topFocus = $serviceFocus->first();
        $membershipSummary = $membershipSummary ?? ['bronze' => 0, 'silver' => 0, 'black' => 0];
        $revenueBreakdown = $revenueBreakdown ?? ['total' => 0, 'groups' => collect()];
    @endphp

    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <section class="hero-panel">
            <div class="panel-body stack">
                <div class="filter-bar__head">
                    <div class="page-actions">
                        <a href="{{ route('app.dashboard', array_filter(['date_from' => $selectedDateFrom, 'date_to' => $selectedDateTo, 'staff_id' => $selectedStaffId, 'export' => 'csv'])) }}" class="btn btn-secondary">Export CSV</a>
                        <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
                        <a href="{{ route('app.calendar', ['date' => $selectedDateTo ?: $selectedDateFrom ?: $selectedDate]) }}" class="btn btn-secondary">Open calendar</a>
                    </div>
                </div>

                <form method="GET" action="{{ route('app.dashboard') }}" class="form-grid">
                    <div class="col-3 field-block">
                        <label class="field-label" for="date_from">From Date</label>
                        <input id="date_from" name="date_from" type="date" class="form-input" value="{{ $selectedDateFrom }}">
                    </div>

                    <div class="col-3 field-block">
                        <label class="field-label" for="date_to">To Date</label>
                        <input id="date_to" name="date_to" type="date" class="form-input" value="{{ $selectedDateTo }}">
                    </div>

                    <div class="col-3 field-block">
                        <label class="field-label" for="staff_id">Person In Charge</label>
                        <select id="staff_id" name="staff_id" class="form-select">
                            <option value="">All PIC</option>
                            @foreach ($staffList as $s)
                                <option value="{{ $s->id }}" @selected((string) $selectedStaffId === (string) $s->id)>
                                    {{ $s->full_name }} ({{ $s->job_title ?: $s->role_key }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-3 field-block" style="align-self:end;">
                        <div class="btn-row btn-row--end btn-row--compact-mobile">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="{{ route('app.dashboard') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="summary-stat-grid">
            <x-stat-card label="New Customers" :value="$kpi['new_customers'] ?? 0" meta="First visit in this period" />
            <x-stat-card label="Existing Customers" :value="$kpi['existing_customers'] ?? 0" meta="Returning customers in this period" />
            <div class="stat-card stat-card--membership">
                <div class="metric-label">Membership</div>
                <div class="membership-stat-list">
                    <div class="membership-stat-row">
                        <span class="membership-stat-row__label">Bronze</span>
                        <span class="membership-stat-row__value">{{ $membershipSummary['bronze'] ?? 0 }}</span>
                    </div>
                    <div class="membership-stat-row">
                        <span class="membership-stat-row__label">Silver</span>
                        <span class="membership-stat-row__value">{{ $membershipSummary['silver'] ?? 0 }}</span>
                    </div>
                    <div class="membership-stat-row">
                        <span class="membership-stat-row__label">Black</span>
                        <span class="membership-stat-row__value">{{ $membershipSummary['black'] ?? 0 }}</span>
                    </div>
                </div>
                <div class="metric-meta">{{ $periodLabel }}</div>
            </div>
            <x-stat-card label="Top Focus" :value="$topFocus['service_name'] ?? '-'" :meta="($topFocus['appointments'] ?? 0).' service items'" />
        </section>

        <section class="revenue-focus-grid">
            <div class="panel panel--revenue-focus">
                <div class="panel-body">
                    <div class="metric-label">Total Group Revenue</div>
                    <div class="revenue-focus__date">Date Range</div>
                    <div class="revenue-focus__range">{{ $periodLabel }}</div>
                    <div class="revenue-focus__total">RM {{ number_format($revenueBreakdown['total'] ?? 0, 0) }}</div>
                </div>
            </div>

            <div class="stack">
                @php
                    $revenueGroups = collect([
                        ['key' => 'wellness', 'label' => 'Wellness'],
                        ['key' => 'aesthetic', 'label' => 'Aesthetic'],
                        ['key' => 'spa_beauty', 'label' => 'Spa & Beauty'],
                    ])->map(function ($item) use ($revenueBreakdown) {
                        $match = collect($revenueBreakdown['groups'] ?? [])->firstWhere('key', $item['key']);
                        return [
                            'label' => $item['label'],
                            'amount' => $match['amount'] ?? 0,
                        ];
                    });
                @endphp

                @foreach ($revenueGroups as $group)
                    <div class="revenue-group-card">
                        <div class="revenue-group-card__label">{{ $group['label'] }}</div>
                        <div class="revenue-group-card__value">RM {{ number_format($group['amount'], 0) }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="report-grid">
            <div class="panel">
                <div class="panel-header">
                    <x-section-heading
                        kicker="Service demand"
                        title="Appointment focus"
                        subtitle="Where the clinic load is concentrating for the selected reporting window." />
                </div>

                <div class="panel-body">
                    @if ($serviceFocus->isNotEmpty())
                        <div class="table-shell">
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Service</th>
                                            <th style="width: 160px;">Appointments</th>
                                            <th style="width: 160px;">Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($serviceFocus->take(8) as $service)
                                            <tr>
                                                <td>{{ $service['service_name'] }}</td>
                                                <td>{{ $service['appointments'] }}</td>
                                                <td>RM {{ number_format($service['sales_amount'], 0) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="empty-state empty-state--dashed">
                            <div class="empty-state__title">No service activity yet</div>
                            <div class="empty-state__body">Once appointments exist in this period, the demand ranking will appear here.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <x-section-heading
                        kicker="Channel mix"
                        title="Booking sources"
                        subtitle="Useful for reviewing where current demand is coming from." />
                </div>
                <div class="panel-body stack">
                    @forelse ($sourceBreakdown as $source)
                        <div class="summary-pill">
                            <span class="summary-pill__label">{{ $source['source'] }}</span>
                            <span class="summary-pill__value">{{ $source['appointments'] }} appointment{{ $source['appointments'] === 1 ? '' : 's' }}</span>
                        </div>
                    @empty
                        <div class="small-note">No source data recorded for this period.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="dashboard-grid">
            <div class="panel">
                <div class="panel-header">
                    <div class="filter-bar__head">
                        <x-section-heading
                            kicker="Recent activity"
                            title="Appointments in scope"
                            :subtitle="'Latest appointment groups inside '.$periodLabel.'.'" />

                        <div class="page-actions">
                            <a href="{{ route('app.appointments.index', ['date' => $selectedDateTo ?: $selectedDateFrom ?: $selectedDate]) }}" class="btn btn-secondary">Manage appointments</a>
                        </div>
                    </div>
                </div>

                <div class="panel-body">
                    @if ($appointments->count())
                        <div class="table-shell">
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th style="width: 130px;">Time</th>
                                            <th>Customer</th>
                                            <th>Services</th>
                                            <th>Staff</th>
                                            <th style="width: 130px;">Sales</th>
                                            <th style="width: 170px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($appointments as $g)
                                            @php
                                                $servicesSummary = $g->items?->map(fn($i) => $i->service?->name)->filter()->unique()->implode(', ') ?: '-';
                                                $staffSummary = $g->items?->map(fn($i) => $i->staff?->full_name)->filter()->unique()->implode(', ') ?: '-';
                                                $salesAmount = (int) $g->items?->sum(fn($i) => (int) ($i->service?->price ?? 0));
                                                $currentStatus = is_object($g->status) ? $g->status->value : (string) $g->status;
                                                $currentStatusLabel = is_object($g->status) && method_exists($g->status, 'label')
                                                    ? $g->status->label()
                                                    : ucfirst(str_replace('_', ' ', $currentStatus));
                                                $tone = match ($currentStatus) {
                                                    'confirmed', 'completed' => 'success',
                                                    'checked_in' => 'info',
                                                    'cancelled', 'no_show' => 'danger',
                                                    default => 'warning',
                                                };
                                            @endphp
                                            <tr>
                                                <td>
                                                    <div class="selection-card__title">{{ optional($g->starts_at)->format('H:i') ?: '-' }}</div>
                                                    <div class="small-note">{{ optional($g->starts_at)->format('d M Y') ?: 'Date unavailable' }}</div>
                                                </td>
                                                <td>
                                                    <div class="selection-card__title">{{ $g->customer?->full_name ?? '-' }}</div>
                                                    @if ($g->customer?->phone)
                                                        <div class="small-note">{{ $g->customer->phone }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $servicesSummary }}</td>
                                                <td>{{ $staffSummary }}</td>
                                                <td>RM {{ number_format($salesAmount, 0) }}</td>
                                                <td><x-status-pill :label="$currentStatusLabel" :tone="$tone" /></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="empty-state empty-state--dashed">
                            <div class="empty-state__title">No appointments found</div>
                            <div class="empty-state__body">Adjust the filters to review another window of operational activity.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title-display" style="font-size:24px;">Capacity Overview</h3>
                </div>

                <div class="panel-body">
                    <div class="staff-review-grid">
                        @forelse ($staffReview as $review)
                            <button
                                type="button"
                                class="staff-review-card"
                                data-staff-review='@json($review)'
                            >
                                <div class="metric-label">{{ $review['job_title'] }}</div>
                                <div class="staff-review-card__value">{{ $review['staff_name'] }}</div>
                                <div class="staff-review-card__meta">{{ $review['appointments'] }} appointments | {{ $review['hours_label'] }} | RM {{ number_format($review['sales_amount'], 0) }}</div>
                            </button>
                        @empty
                            <div class="empty-state empty-state--dashed" style="grid-column: 1 / -1;">
                                <div class="empty-state__title">No staff load found</div>
                                <div class="empty-state__body">This view fills automatically once matching appointments exist.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <div class="print-only small-note">
            Printed from Lindo Clinic dashboard for {{ $periodLabel }}.
        </div>
    </div>

    <div id="staff-review-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card">
                <div class="modal-header">
                    <div class="modal-header__row">
                        <div>
                            <div class="modal-kicker">Staff review</div>
                            <h3 id="staff-review-name" class="modal-title">-</h3>
                            <p id="staff-review-summary" class="modal-subtitle">-</p>
                        </div>
                        <button type="button" id="staff-review-close-top" class="modal-close" aria-label="Close">&times;</button>
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
                                        <th>Status</th>
                                        <th>Sales</th>
                                    </tr>
                                </thead>
                                <tbody id="staff-review-details"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" id="staff-review-close-bottom" class="modal-btn modal-btn--secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('staff-review-modal');
            const closeTop = document.getElementById('staff-review-close-top');
            const closeBottom = document.getElementById('staff-review-close-bottom');
            const nameTarget = document.getElementById('staff-review-name');
            const summaryTarget = document.getElementById('staff-review-summary');
            const detailTarget = document.getElementById('staff-review-details');

            const closeModal = () => {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            document.querySelectorAll('[data-staff-review]').forEach((button) => {
                button.addEventListener('click', () => {
                    let payload = {};

                    try {
                        payload = JSON.parse(button.dataset.staffReview || '{}');
                    } catch (error) {
                        payload = {};
                    }

                    nameTarget.textContent = payload.staff_name || '-';
                    summaryTarget.textContent = `${payload.job_title || 'Operational staff'} | ${payload.appointments || 0} appointments | ${payload.hours_label || '-'}`;
                    detailTarget.innerHTML = (payload.details || []).map((detail) => `
                        <tr>
                            <td>${detail.customer_name || '-'}</td>
                            <td>${detail.service_name || '-'}</td>
                            <td>${detail.time_label || '-'}</td>
                            <td>${detail.status_label || '-'}</td>
                            <td>${detail.sales_label || '-'}</td>
                        </tr>
                    `).join('');

                    modal.classList.remove('hidden');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                });
            });

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
