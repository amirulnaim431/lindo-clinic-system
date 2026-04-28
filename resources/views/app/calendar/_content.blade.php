<div class="stack calendar-board-shell{{ !empty($embedded) ? ' calendar-board-shell--embedded' : '' }}{{ !empty($compact) ? ' calendar-board-shell--compact' : '' }}">
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
                    <a href="{{ route('app.calendar', ['date' => $previousDate, 'embedded' => !empty($embedded) ? 1 : null, 'compact' => !empty($compact) ? 1 : null]) }}" class="btn btn-secondary">&larr; Previous day</a>
                    <form method="GET" action="{{ route('app.calendar') }}" class="calendar-toolbar__form">
                        @if (!empty($embedded))
                            <input type="hidden" name="embedded" value="1">
                        @endif
                        @if (!empty($compact))
                            <input type="hidden" name="compact" value="1">
                        @endif
                        <input id="date" name="date" type="date" value="{{ $selectedDateIso }}" class="form-input calendar-toolbar__input" aria-label="View date">
                        <button type="submit" class="btn btn-primary">Apply</button>
                    </form>
                    <a href="{{ route('app.calendar', ['date' => $nextDate, 'embedded' => !empty($embedded) ? 1 : null, 'compact' => !empty($compact) ? 1 : null]) }}" class="btn btn-secondary">Next day &rarr;</a>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
                </div>
            </div>
        </div>
    </section>

    <section class="panel screen-only {{ !empty($compact) ? 'calendar-summary-panel--compact' : '' }}">
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

    @if (!empty($compact))
        <section class="calendar-reference-board">
            @foreach ($availabilitySections as $section)
                <div class="planner-staff-card calendar-reference-card">
                    <div class="planner-staff-card__head">
                        <div>
                            <div class="selection-card__title">{{ $section['staff_name'] }}</div>
                            <div class="small-note">{{ $section['staff_role'] ?: 'Staff' }}</div>
                        </div>
                        <div class="small-note">{{ $section['booking_windows'] }} booking windows</div>
                    </div>

                    @foreach ($section['rows'] as $row)
                        <div class="planner-slot-row calendar-reference-row">
                            <div class="planner-slot-label">{{ $row['label'] }}</div>
                            @foreach ($row['boxes'] as $box)
                                <div class="planner-slot-box {{ $box['type'] === 'occupied' ? 'is-occupied' : ($box['type'] === 'blocked' ? 'is-blocked' : 'is-empty') }}">
                                    <div class="planner-slot-box__title">{{ $box['title'] }}</div>
                                    <div>{{ $box['body'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </section>
    @else
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
                                        <tr class="calendar-client-row"
                                            data-phone="{{ $row['phone'] }}"
                                            data-time="{{ $row['time'] }}"
                                            data-date="{{ $row['date_label'] }}"
                                            data-treatment="{{ $row['treatment'] }}"
                                            data-pic="{{ $row['pic'] }}"
                                        >
                                            <td>{{ collect($scheduleSections)->take($sectionIndex)->sum('count') + $rowIndex + 1 }}</td>
                                            <td>{{ $row['time'] }}</td>
                                            <td class="calendar-client-name">{{ $row['client'] }}</td>
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
                        <div class="empty-state__title">No appointments yet</div>
                        <div class="empty-state__body">Choose another date or add the first appointment from the booking page.</div>
                    </div>
                </div>
            </section>
        @endforelse
    @endif
</div>

<div id="whatsapp-reminder-modal" class="modal-shell hidden screen-only" aria-hidden="true">
    <div class="modal-backdrop"></div>
    <div class="modal-stage">
        <div class="modal-card whatsapp-reminder-modal">
            <div class="modal-header">
                <div>
                    <div class="modal-kicker">WhatsApp reminder</div>
                    <h3 class="modal-title" id="whatsapp-reminder-title">Appointment reminder</h3>
                    <p class="modal-subtitle" id="whatsapp-reminder-subtitle"></p>
                </div>
                <button type="button" class="modal-close" id="whatsapp-reminder-close" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body stack">
                <div class="confirm-remove-copy" id="whatsapp-reminder-details"></div>
                <textarea id="whatsapp-reminder-copy" class="form-input booking-textarea" rows="7" readonly></textarea>
                <div class="btn-row">
                    <button type="button" class="btn btn-primary" id="whatsapp-reminder-copy-button">Copy message</button>
                    <button type="button" class="btn btn-secondary" id="whatsapp-reminder-cancel">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .print-header {
        display: none;
    }

    .calendar-board-shell--embedded {
        padding: 0.75rem;
        background: linear-gradient(180deg, #fffdfd 0%, #fff7fa 100%);
        min-height: 100vh;
    }

    .calendar-board-shell--embedded.stack {
        gap: 1rem;
    }

    .calendar-hero-panel__body {
        padding: 0.85rem 1.1rem;
    }

    .calendar-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem 1.5rem;
        flex-wrap: nowrap;
    }

    .calendar-hero__summary {
        flex: 1 1 auto;
        min-width: 0;
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
        gap: 0.4rem;
        flex: 0 0 auto;
        flex-wrap: nowrap;
        white-space: nowrap;
    }

    .calendar-toolbar__form {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-wrap: nowrap;
    }

    .calendar-toolbar__input {
        width: 150px;
        min-width: 150px;
    }

    .calendar-toolbar .btn {
        min-height: 40px;
        padding: 0.62rem 0.9rem;
    }

    .calendar-board-shell--compact {
        font-size: 0.92rem;
    }

    .calendar-board-shell--compact .calendar-hero-panel__body {
        padding: 0.7rem 0.85rem;
    }

    .calendar-board-shell--compact .calendar-title-line__date {
        font-size: clamp(1.15rem, 1.8vw, 1.55rem);
    }

    .calendar-board-shell--compact .calendar-hero__note {
        margin-top: 0.18rem;
    }

    .calendar-board-shell--compact .calendar-toolbar .btn {
        min-height: 36px;
        padding: 0.5rem 0.72rem;
    }

    .calendar-board-shell--compact .calendar-toolbar__input {
        min-height: 36px;
        width: 140px;
        min-width: 140px;
    }

    .calendar-board-shell--compact .calendar-summary-panel--compact {
        display: none;
    }

    .calendar-board-shell--compact .schedule-section-head {
        padding: 0.7rem 0.9rem;
    }

    .calendar-board-shell--compact .daily-schedule-table td,
    .calendar-board-shell--compact .daily-schedule-table th {
        padding: 0.48rem 0.55rem;
    }

    .calendar-stats-grid {
        display: grid;
        gap: 1rem;
    }

    .calendar-stats-grid + .calendar-stats-grid {
        margin-top: 1rem;
    }

    .calendar-stats-grid--top {
        grid-template-columns: repeat(2, minmax(0, 1fr));
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

    .calendar-reference-board {
        display: grid;
        gap: 1rem;
    }

    .calendar-reference-card {
        border: 1px solid rgba(26, 19, 23, 0.08);
        border-radius: 28px;
        background: #fff;
        overflow: hidden;
        box-shadow: 0 24px 70px rgba(92, 58, 69, 0.08);
    }

    .planner-staff-card__head {
        padding: 1rem 1.15rem;
        border-bottom: 1px solid rgba(26, 19, 23, 0.06);
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .selection-card__title {
        font-weight: 700;
        color: #1a1317;
    }

    .planner-slot-row {
        display: grid;
        grid-template-columns: minmax(130px, 170px) repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        align-items: stretch;
        padding: 0.95rem 1.15rem;
        border-top: 1px solid rgba(26, 19, 23, 0.05);
    }

    .planner-slot-label {
        font-weight: 700;
        color: #1a1317;
        align-self: center;
    }

    .planner-slot-box {
        min-height: 88px;
        border-radius: 20px;
        border: 1px dashed rgba(26, 19, 23, 0.12);
        background: #fcfafb;
        padding: 0.8rem 0.9rem;
        font-size: 0.92rem;
        color: rgba(26, 19, 23, 0.74);
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0.3rem;
    }

    .planner-slot-box.is-empty {
        background: #fcfafb;
    }

    .planner-slot-box.is-occupied {
        border-style: solid;
        background: #f4f0f1;
        color: rgba(26, 19, 23, 0.7);
    }

    .planner-slot-box.is-blocked {
        border-style: solid;
        border-color: rgba(151, 51, 63, 0.25);
        background: #fff0f1;
        color: #7f2f3b;
    }

    .planner-slot-box__title {
        font-weight: 700;
        color: #1a1317;
    }

    .screen-only {
        display: initial;
    }

    .calendar-client-row {
        cursor: pointer;
    }

    .calendar-client-row:hover td {
        background: #fff8fa;
    }

    .whatsapp-reminder-modal {
        width: min(620px, calc(100vw - 32px));
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

        .calendar-hero {
            flex-wrap: wrap;
        }

        .calendar-toolbar {
            justify-content: flex-start;
            flex-wrap: wrap;
            white-space: normal;
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

        .planner-slot-row {
            grid-template-columns: 1fr;
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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('whatsapp-reminder-modal');
        const closeButtons = [
            document.getElementById('whatsapp-reminder-close'),
            document.getElementById('whatsapp-reminder-cancel'),
        ];
        const title = document.getElementById('whatsapp-reminder-title');
        const subtitle = document.getElementById('whatsapp-reminder-subtitle');
        const details = document.getElementById('whatsapp-reminder-details');
        const copyBox = document.getElementById('whatsapp-reminder-copy');
        const copyButton = document.getElementById('whatsapp-reminder-copy-button');

        function closeReminder() {
            modal?.classList.add('hidden');
            modal?.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }

        document.querySelectorAll('.calendar-client-row').forEach((row) => {
            row.addEventListener('click', function () {
                const customerName = row.querySelector('.calendar-client-name')?.textContent?.trim() || 'Customer';
                const reminder = `Hi ${customerName}, this is a friendly reminder for your appointment at Lindo Clinic on ${row.dataset.date || 'your appointment date'} at ${row.dataset.time || 'your appointment time'}. Your treatment is ${row.dataset.treatment || 'your treatment'} with ${row.dataset.pic || 'our team'}. If you need help or need to reschedule, just reply to this message. We look forward to seeing you soon.`;

                title.textContent = customerName;
                subtitle.textContent = row.dataset.phone ? `Phone: ${row.dataset.phone}` : 'No phone number saved';
                details.textContent = `${row.dataset.time || '-'} | ${row.dataset.treatment || '-'} | PIC: ${row.dataset.pic || '-'}`;
                copyBox.value = reminder;
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                window.setTimeout(() => copyBox?.select(), 80);
            });
        });

        closeButtons.forEach((button) => button?.addEventListener('click', closeReminder));
        modal?.addEventListener('click', function (event) {
            if (event.target === modal || event.target === modal.firstElementChild) {
                closeReminder();
            }
        });
        copyButton?.addEventListener('click', async function () {
            copyBox.select();
            try {
                await navigator.clipboard.writeText(copyBox.value);
                copyButton.textContent = 'Copied';
                window.setTimeout(() => copyButton.textContent = 'Copy message', 1200);
            } catch (error) {
                document.execCommand('copy');
            }
        });
    });
</script>
