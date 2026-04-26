<div class="stack calendar-board-shell{{ !empty($embedded) ? ' calendar-board-shell--embedded' : '' }}">
    @if (session('success') && empty($embedded))
        <div class="flash flash--success">{{ session('success') }}</div>
    @endif

    <section class="panel calendar-hero-panel">
        <div class="panel-body calendar-hero-panel__body">
            <div class="calendar-hero">
                <div class="calendar-hero__summary">
                    <div class="calendar-title-line screen-only">
                        <span class="compact-label">Calendar</span>
                        <span class="calendar-title-line__date">{{ strtoupper($selectedDateLabel) }}</span>
                    </div>
                    <div class="print-header">
                        <h1 class="print-header__title">DAILY CLIENT SCHEDULE</h1>
                        <div class="print-header__date">{{ strtoupper($selectedDateLabel) }}</div>
                    </div>
                    <div class="small-note calendar-hero__note">Total treatments for the day: {{ $totalRows }}</div>
                </div>

                <div class="calendar-toolbar screen-only">
                    <a href="{{ route('app.calendar', ['date' => $previousDate, 'embedded' => !empty($embedded) ? 1 : null]) }}" class="btn btn-secondary">&larr; Previous day</a>
                    <form method="GET" action="{{ route('app.calendar') }}" class="calendar-toolbar__form">
                        @if (!empty($embedded))
                            <input type="hidden" name="embedded" value="1">
                        @endif
                        <input id="date" name="date" type="date" value="{{ $selectedDateIso }}" class="form-input calendar-toolbar__input" aria-label="View date">
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </form>
                    <a href="{{ route('app.calendar', ['date' => $nextDate, 'embedded' => !empty($embedded) ? 1 : null]) }}" class="btn btn-secondary">Next day &rarr;</a>
                    @if (empty($embedded))
                        <a href="{{ route('app.appointments.index', ['date' => $selectedDateIso]) }}" class="btn btn-secondary">Open booking</a>
                    @endif
                    <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
                </div>
            </div>
        </div>
    </section>

    <section class="panel screen-only">
        <div class="panel-body">
            <div class="calendar-stats-grid calendar-stats-grid--top">
                @foreach ($topSummaryCards as $card)
                    <div class="metric-card calendar-metric-card">
                        <div class="metric-card__label">{{ $card['label'] }}</div>
                        <div class="metric-card__value calendar-metric-card__value">{{ $card['value'] }}</div>
                        @if ($card['meta'])
                            <div class="metric-card__meta">{{ $card['meta'] }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="calendar-stats-grid calendar-stats-grid--bottom">
                @foreach ($bottomSummaryCards as $card)
                    <div class="metric-card calendar-metric-card">
                        <div class="metric-card__label">{{ $card['label'] }}</div>
                        <div class="metric-card__value calendar-metric-card__value">{{ $card['value'] }}</div>
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

                <div class="table-shell calendar-table-shell">
                    <div class="table-wrap calendar-table-wrap">
                        <table class="daily-schedule-table">
                            <colgroup>
                                <col style="width: 5%;">
                                <col style="width: 10%;">
                                <col style="width: 19%;">
                                <col style="width: 11%;">
                                <col style="width: 25%;">
                                <col style="width: 11%;">
                                <col style="width: 19%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Time</th>
                                    <th>Client</th>
                                    <th>M/SHIP</th>
                                    <th>Treatment</th>
                                    <th>PIC</th>
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

    .calendar-board-shell--embedded {
        padding: 1rem;
        background: linear-gradient(180deg, #fffdfd 0%, #fff7fa 100%);
        min-height: 100vh;
    }

    .calendar-board-shell--embedded .stack {
        gap: 1rem;
    }

    .calendar-hero-panel__body {
        padding-top: 1.1rem;
        padding-bottom: 1.1rem;
    }

    .calendar-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1.5rem;
        flex-wrap: wrap;
    }

    .calendar-hero__summary {
        flex: 1 1 300px;
        min-width: 280px;
    }

    .calendar-title-line {
        display: flex;
        align-items: baseline;
        gap: 0.45rem;
        flex-wrap: wrap;
    }

    .calendar-title-line__date {
        font-size: clamp(1.4rem, 2.1vw, 2rem);
        line-height: 1.05;
        color: #1a1317;
    }

    .calendar-hero__note {
        margin-top: 0.35rem;
    }

    .calendar-toolbar {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
        flex: 0 1 auto;
        flex-wrap: wrap;
    }

    .calendar-toolbar__form {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }

    .calendar-toolbar__input {
        width: 170px;
        min-width: 170px;
    }

    .calendar-stats-grid {
        display: grid;
        gap: 1rem;
    }

    .calendar-stats-grid + .calendar-stats-grid {
        margin-top: 1rem;
    }

    .calendar-stats-grid--top {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }

    .calendar-stats-grid--bottom {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .calendar-metric-card {
        padding: 1.05rem 1.1rem;
    }

    .calendar-metric-card__value {
        font-size: 1.25rem;
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

    .calendar-table-shell,
    .calendar-table-wrap {
        overflow: visible;
    }

    .daily-schedule-table {
        width: 100%;
        table-layout: fixed;
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
        word-break: normal;
        overflow-wrap: anywhere;
    }

    .daily-schedule-table tbody td:nth-child(2),
    .daily-schedule-table tbody td:nth-child(4) {
        white-space: nowrap;
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

        .calendar-stats-grid--bottom {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .daily-schedule-table {
            min-width: 980px;
        }
    }

    @media print {
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        html,
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
            font-size: 21px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: #000;
        }

        .print-header__date {
            margin-top: 10px;
            font-size: 17px;
            font-weight: 700;
            color: #000;
        }

        .stack {
            gap: 10px !important;
        }

        .panel,
        .panel-body,
        .table-shell,
        .table-wrap,
        .calendar-board-shell--embedded {
            background: #fff !important;
            box-shadow: none !important;
            border: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .calendar-board-shell--embedded {
            padding: 0 !important;
            min-height: 0 !important;
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
            font-size: 10.5px;
        }

        .daily-schedule-table thead th {
            background: #000 !important;
            color: #fff !important;
            border: 1px solid #000 !important;
            padding: 6px 5px;
            font-size: 9.5px;
        }

        .daily-schedule-table td,
        .daily-schedule-table th {
            border: 1px solid #000 !important;
            padding: 6px 5px;
            color: #000 !important;
            word-break: normal !important;
            overflow-wrap: anywhere !important;
            white-space: normal;
            hyphens: none;
        }

        .daily-schedule-table tbody td:nth-child(1),
        .daily-schedule-table tbody td:nth-child(2),
        .daily-schedule-table tbody td:nth-child(4) {
            white-space: nowrap;
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
