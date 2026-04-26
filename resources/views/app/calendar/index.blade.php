<x-internal-layout :title="$title" :subtitle="$subtitle">
    <div class="stack">
        @if (session('success'))
            <div class="flash flash--success">{{ session('success') }}</div>
        @endif

        <section class="panel">
            <div class="panel-body">
                <div class="calendar-hero">
                    <div class="calendar-hero__summary">
                        <div class="compact-label screen-only">Calendar</div>
                        <div class="print-header">
                            <h1 class="print-header__title">DAILY CLIENT SCHEDULE</h1>
                            <div class="print-header__date">{{ strtoupper($selectedDateLabel) }}</div>
                        </div>
                        <h2 class="panel-title-display screen-only">{{ strtoupper($selectedDateLabel) }}</h2>
                        <div class="small-note calendar-hero__note">Total treatments for the day: {{ $totalRows }}</div>
                    </div>

                    <div class="calendar-toolbar screen-only">
                        <a href="{{ route('app.calendar', ['date' => $previousDate]) }}" class="btn btn-secondary">&larr; Previous day</a>
                        <form method="GET" action="{{ route('app.calendar') }}" class="calendar-toolbar__form">
                            <input id="date" name="date" type="date" value="{{ $selectedDateIso }}" class="form-input calendar-toolbar__input" aria-label="View date">
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </form>
                        <a href="{{ route('app.calendar', ['date' => $nextDate]) }}" class="btn btn-secondary">Next day &rarr;</a>
                        <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="btn btn-secondary">Open booking</a>
                        <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel screen-only">
            <div class="panel-body">
                <div class="calendar-stats-grid">
                    @foreach ($summaryCards as $card)
                        <div class="metric-card">
                            <div class="metric-card__label">{{ $card['label'] }}</div>
                            <div class="metric-card__value" style="font-size:22px;">{{ $card['value'] }}</div>
                            @if ($card['meta'])
                                <div class="metric-card__meta">{{ $card['meta'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @forelse ($scheduleSections as $sectionIndex => $section)
            <section class="panel print-schedule-section">
                <div class="panel-body" style="padding:0;">
                    <div class="schedule-section-head">
                        <div class="schedule-section-head__pic">PIC: {{ $section['staff_name'] }}</div>
                        <div class="schedule-section-head__count">Count: {{ $section['count'] }}</div>
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
        .print-header {
            display: none;
        }

        .calendar-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem 2rem;
            flex-wrap: wrap;
        }

        .calendar-hero__summary {
            flex: 1 1 420px;
            min-width: 280px;
        }

        .calendar-hero__note {
            margin-top: 0.5rem;
        }

        .calendar-toolbar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            flex: 0 1 auto;
            flex-wrap: wrap;
        }

        .calendar-toolbar__form {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: nowrap;
        }

        .calendar-toolbar__input {
            width: 190px;
            min-width: 190px;
        }

        .calendar-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .screen-only {
            display: initial;
        }

        .schedule-section-head {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(26, 19, 23, 0.08);
            background: #f6f3f4;
        }

        .schedule-section-head__pic,
        .schedule-section-head__count {
            font-weight: 700;
            color: #1a1317;
        }

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
            .calendar-hero,
            .calendar-toolbar {
                align-items: stretch;
            }

            .calendar-toolbar {
                justify-content: flex-start;
            }

            .calendar-toolbar__form {
                flex-wrap: wrap;
            }

            .calendar-toolbar__input {
                width: 100%;
                min-width: 0;
            }

            .calendar-stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .daily-schedule-table {
                min-width: 980px;
            }
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm;
            }

            body {
                background: #fff !important;
            }

            .screen-only,
            .sidebar-shell,
            .internal-topbar,
            .app-shell__chrome,
            .page-header,
            .small-note.screen-only,
            .calendar-stats-grid {
                display: none !important;
            }

            .print-header {
                display: block;
                text-align: center;
            }

            .print-header__title {
                margin: 0;
                font-size: 22px;
                font-weight: 700;
                letter-spacing: 0.02em;
                color: #000;
            }

            .print-header__date {
                margin-top: 10px;
                font-size: 18px;
                font-weight: 700;
                color: #000;
            }

            .stack {
                gap: 10px !important;
            }

            .panel,
            .panel-body,
            .table-shell,
            .table-wrap {
                background: #fff !important;
                box-shadow: none !important;
                border: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .print-schedule-section {
                page-break-inside: avoid;
                break-inside: avoid;
                margin-top: 10px;
            }

            .schedule-section-head {
                background: #e9eaee !important;
                border: 1px solid #000;
                border-bottom: 0;
                padding: 6px 10px;
            }

            .daily-schedule-table {
                width: 100%;
                min-width: 0 !important;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 12px;
            }

            .daily-schedule-table thead th {
                background: #000 !important;
                color: #fff !important;
                border: 1px solid #000 !important;
                padding: 8px 6px;
                font-size: 11px;
            }

            .daily-schedule-table td,
            .daily-schedule-table th {
                border: 1px solid #000 !important;
                padding: 7px 6px;
                color: #000 !important;
                word-break: break-word;
            }

            .daily-schedule-table tbody td:nth-child(4) {
                background: #f8cf9f !important;
            }

            .daily-schedule-table tbody tr:nth-child(even) td:nth-child(4) {
                background: #f8cf9f !important;
            }

            a {
                color: #000 !important;
                text-decoration: none !important;
            }
        }
    </style>
</x-internal-layout>
