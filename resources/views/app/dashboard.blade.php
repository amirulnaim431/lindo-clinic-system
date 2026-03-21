<x-internal-layout
    :title="'Dashboard'"
    :subtitle="'Premium operational overview for appointments, clinic load, and staff activity.'">

    @php
        $selectedDate = request('date', $date ?? now()->toDateString());
        $selectedStaffId = request('staff_id');
        $selectedPeriod = request('period', $period ?? 'day');
        $periodOptions = [
            'day' => 'Day',
            'week' => 'Week',
            'month' => 'Month',
            'year' => 'Year',
        ];
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
                <x-section-heading
                    kicker="Clinic summary"
                    title="Operational overview"
                    subtitle="Use the shared dashboard filters to inspect appointment volume, staff coverage, and live scheduling for the selected period." />

                <form method="GET" action="{{ route('app.dashboard') }}" class="form-grid">
                    <div class="col-3 field-block">
                        <label class="field-label" for="period">Period</label>
                        <select id="period" name="period" class="form-select">
                            @foreach ($periodOptions as $value => $label)
                                <option value="{{ $value }}" @selected($selectedPeriod === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-3 field-block">
                        <label class="field-label" for="date">Anchor date</label>
                        <input id="date" name="date" type="date" class="form-input" value="{{ $selectedDate }}">
                    </div>

                    <div class="col-3 field-block">
                        <label class="field-label" for="staff_id">Staff focus</label>
                        <select id="staff_id" name="staff_id" class="form-select">
                            <option value="">All staff</option>
                            @foreach ($staffList as $s)
                                <option value="{{ $s->id }}" @selected((string) $selectedStaffId === (string) $s->id)>
                                    {{ $s->full_name }} ({{ $s->role_key }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-3 field-block" style="align-self:end;">
                        <div class="btn-row" style="justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary">Apply filters</button>
                            <a href="{{ route('app.dashboard') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="stats-grid">
            <x-stat-card label="Total appointments" :value="$kpi['total'] ?? 0" :meta="$periodLabel ?? 'Selected period'" />
            <x-stat-card label="Revenue" value="--" meta="Reserved for future billing integration." />

            @foreach ($statusCases as $st)
                @php
                    $statusKey = is_object($st) ? $st->value : (string) $st;
                    $statusLabel = method_exists($st, 'label')
                        ? $st->label()
                        : ucfirst(str_replace('_', ' ', $statusKey));
                @endphp
                <x-stat-card
                    :label="$statusLabel"
                    :value="$kpi['by_status'][$statusKey] ?? 0"
                    :meta="'Within '.strtolower($periodOptions[$selectedPeriod] ?? 'day').' view.'" />
            @endforeach
        </section>

        <section class="dashboard-grid">
            <div class="panel">
                <div class="panel-header">
                    <div class="filter-bar__head">
                        <x-section-heading
                            kicker="Schedule"
                            title="Appointments in focus"
                            :subtitle="'Appointment groups for '.($periodLabel ?? $selectedDate).'.'" />

                        <div class="page-actions">
                            <a href="{{ route('app.appointments.index') }}" class="btn btn-secondary">Manage appointments</a>
                            <a href="{{ route('app.calendar') }}" class="btn btn-secondary">Open calendar</a>
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
                                            <th style="width: 170px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($appointments as $g)
                                            @php
                                                $servicesSummary = $g->items?->map(fn($i) => $i->service?->name)->filter()->unique()->implode(', ') ?: '-';
                                                $staffSummary = $g->items?->map(fn($i) => $i->staff?->full_name)->filter()->unique()->implode(', ') ?: '-';
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
                                                <td>
                                                    <x-status-pill :label="$currentStatusLabel" :tone="$tone" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="empty-state empty-state--dashed">
                            <div class="empty-state__title">No appointments found</div>
                            <div class="empty-state__body">Adjust the period or staff filter to inspect another section of the clinic workload.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="stack">
                <div class="panel">
                    <div class="panel-header">
                        <x-section-heading
                            kicker="Workflow"
                            title="How to use this view"
                            subtitle="This page is the high-level checkpoint before moving into the booking desk or live calendar." />
                    </div>
                    <div class="panel-body stack">
                        <div class="summary-card">
                            <div class="selection-card__title">Use filters for decision making</div>
                            <div class="small-note">Switch between day, week, month, and year to understand demand and staff load.</div>
                        </div>
                        <div class="summary-card">
                            <div class="selection-card__title">Open the calendar for live movement</div>
                            <div class="small-note">Drag-based rescheduling and quick-create actions remain in the calendar board.</div>
                        </div>
                        <div class="summary-card">
                            <div class="selection-card__title">Use appointments for intake</div>
                            <div class="small-note">The booking desk remains the operational screen for service matching and slot confirmation.</div>
                        </div>
                    </div>
                </div>

                <x-stat-card label="Selected period" :value="$periodOptions[$selectedPeriod] ?? 'Day'" :meta="$periodLabel ?? 'Current scope'" />
            </div>
        </section>
    </div>
</x-internal-layout>
