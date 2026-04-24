<x-internal-layout :title="$title" :subtitle="$subtitle">
    <div class="stack">
        @if (session('success'))
            <div class="flash flash--success">{{ session('success') }}</div>
        @endif

        <section class="panel">
            <div class="panel-body">
                <div class="filter-bar__head" style="align-items:flex-end;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <div class="compact-label">Daily Client Schedule</div>
                        <h2 class="panel-title-display">{{ strtoupper($selectedDateLabel) }}</h2>
                        <div class="small-note" style="margin-top:0.5rem;">Total treatments for the day: {{ $totalRows }}</div>
                    </div>

                    <div class="btn-row" style="align-items:flex-end;flex-wrap:wrap;">
                        <a href="{{ route('app.calendar', ['date' => $previousDate]) }}" class="btn btn-secondary">&larr; Previous day</a>
                        <form method="GET" action="{{ route('app.calendar') }}" style="display:flex;align-items:end;gap:0.75rem;flex-wrap:wrap;">
                            <div class="field-block" style="min-width:180px;">
                                <label class="field-label" for="date">View date</label>
                                <input id="date" name="date" type="date" value="{{ $selectedDateIso }}" class="form-input">
                            </div>
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </form>
                        <a href="{{ route('app.calendar', ['date' => $nextDate]) }}" class="btn btn-secondary">Next day &rarr;</a>
                        <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="btn btn-secondary">Open booking</a>
                    </div>
                </div>
            </div>
        </section>

        @forelse ($scheduleSections as $sectionIndex => $section)
            <section class="panel">
                <div class="panel-body" style="padding:0;">
                    <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap;padding:1rem 1.25rem;border-bottom:1px solid rgba(26, 19, 23, 0.08);background:#f6f3f4;">
                        <div style="font-weight:700;color:#1a1317;">PIC: {{ $section['staff_name'] }}</div>
                        <div class="small-note" style="font-weight:700;">Count: {{ $section['count'] }}</div>
                    </div>

                    <div class="table-shell">
                        <div class="table-wrap">
                            <table class="daily-schedule-table">
                                <thead>
                                    <tr>
                                        <th style="width:70px;">No.</th>
                                        <th style="width:140px;">Time</th>
                                        <th>Client</th>
                                        <th style="width:180px;">M/SHIP</th>
                                        <th>Treatment</th>
                                        <th style="width:160px;">PIC</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($section['rows'] as $rowIndex => $row)
                                        <tr>
                                            <td>{{ collect($scheduleSections)->take($sectionIndex)->sum('count') + $rowIndex + 1 }}</td>
                                            <td>{{ $row['time'] }}</td>
                                            <td>{{ $row['client'] }}</td>
                                            <td>{{ $row['membership'] }}</td>
                                            <td>{{ $row['treatment'] }}</td>
                                            <td>{{ $row['pic'] }}</td>
                                            <td>{{ $row['remarks'] ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>
        @empty
            <section class="panel">
                <div class="panel-body">
                    <div class="empty-state">
                        <div class="empty-state__title">No appointments scheduled for this date</div>
                        <div class="empty-state__body">Choose another date or open the booking page to add the first appointment.</div>
                    </div>
                </div>
            </section>
        @endforelse
    </div>

    <style>
        .daily-schedule-table thead th {
            background: #070707;
            color: #fff;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .daily-schedule-table td,
        .daily-schedule-table th {
            border: 1px solid rgba(7, 7, 7, 0.18);
            vertical-align: top;
        }

        .daily-schedule-table tbody td:nth-child(4) {
            background: #f8cf9f;
        }

        .daily-schedule-table tbody tr:nth-child(even) td:nth-child(4) {
            background: #f4dfa8;
        }

        @media (max-width: 900px) {
            .daily-schedule-table {
                min-width: 980px;
            }
        }
    </style>
</x-internal-layout>
