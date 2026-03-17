<x-internal-layout
    :title="'Dashboard'"
    :subtitle="'Premium internal overview for appointments, clinic load, and staff operations.'">

    @php
        $selectedDate = request('date', $date ?? now()->toDateString());
        $selectedStaffId = request('staff_id');
    @endphp

    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="panel">
            <div class="panel__header">
                <h2 class="panel__title">Overview controls</h2>
                <div class="panel__subtitle">
                    Filter the dashboard by date and staff, then jump directly into the relevant operational pages.
                </div>
            </div>

            <div class="panel__body">
                <form method="GET" action="{{ route('app.dashboard') }}" class="form-grid">
                    <div class="col-4">
                        <label class="field-label" for="date">Date</label>
                        <input
                            id="date"
                            name="date"
                            type="date"
                            class="field-input"
                            value="{{ $selectedDate }}"
                        >
                    </div>

                    <div class="col-4">
                        <label class="field-label" for="staff_id">Staff</label>
                        <select id="staff_id" name="staff_id" class="field-select">
                            <option value="">All staff</option>
                            @foreach ($staffList as $s)
                                <option value="{{ $s->id }}" @selected((string) $selectedStaffId === (string) $s->id)>
                                    {{ $s->full_name }} ({{ $s->role_key }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-4" style="display:flex; align-items:end;">
                        <div class="btn-row">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="{{ route('app.dashboard') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card__label">Total appointments</div>
                <div class="stat-card__value">{{ $kpi['total'] ?? 0 }}</div>
            </div>

            @foreach ($statusCases as $st)
                @php
                    $statusKey = is_object($st) ? $st->value : (string) $st;
                    $statusLabel = method_exists($st, 'label')
                        ? $st->label()
                        : ucfirst(str_replace('_', ' ', $statusKey));
                @endphp

                <div class="stat-card">
                    <div class="stat-card__label">{{ $statusLabel }}</div>
                    <div class="stat-card__value">{{ $kpi['by_status'][$statusKey] ?? 0 }}</div>
                </div>
            @endforeach
        </div>

        <div class="panel">
            <div class="panel__header" style="display:flex; justify-content:space-between; align-items:start; gap:16px; flex-wrap:wrap;">
                <div>
                    <h2 class="panel__title">Schedule</h2>
                    <div class="panel__subtitle">
                        Appointment groups for {{ $selectedDate }}.
                    </div>
                </div>

                <div class="btn-row">
                    <a href="{{ route('app.appointments.index') }}" class="btn btn-secondary">Manage appointments</a>
                    <a href="{{ route('app.calendar') }}" class="btn btn-secondary">Open calendar</a>
                </div>
            </div>

            <div class="panel__body">
                @forelse ($appointments as $g)
                    @break($loop->first)
                @empty
                    <div class="empty-state">
                        No appointments found for the selected filters.
                    </div>
                @endforelse

                @if ($appointments->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:120px;">Time</th>
                                    <th>Customer</th>
                                    <th>Services</th>
                                    <th>Staff</th>
                                    <th style="width:150px;">Status</th>
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
                                    @endphp

                                    <tr>
                                        <td>
                                            <strong>{{ optional($g->starts_at)->format('H:i') ?: '-' }}</strong>
                                        </td>
                                        <td>
                                            <div style="font-weight:700;">{{ $g->customer?->full_name ?? '-' }}</div>
                                            @if ($g->customer?->phone)
                                                <div class="text-muted">{{ $g->customer->phone }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $servicesSummary }}</td>
                                        <td>{{ $staffSummary }}</td>
                                        <td>
                                            <span class="chip chip--soft">{{ $currentStatusLabel }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-internal-layout>