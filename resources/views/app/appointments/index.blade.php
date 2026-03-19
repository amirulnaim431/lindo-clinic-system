<x-internal-layout :title="'Appointments'" :subtitle="'Operational booking desk for same-day scheduling, availability checks, and quick front desk handoff.'">
    @php
        $filters = $filters ?? ['date' => now()->format('Y-m-d'), 'service_ids' => [], 'slot' => null];
        $selectedServiceIds = collect($filters['service_ids'] ?? [])->map(fn ($id) => (string) $id)->values()->all();
        $selectedDate = $filters['date'] ?? now()->format('Y-m-d');
        $prefilledSlot = $filters['slot'] ?? null;
        $services = $services ?? collect();
        $availability = $availability ?? null;
        $appointmentGroups = $appointmentGroups ?? collect();
        $statusOptions = $statusOptions ?? [];
        $quickCreate = $quickCreate ?? ['prefilled_slot' => null, 'slot_is_available' => false, 'slot_combinations' => [], 'message' => null];
        $statusPalette = [
            'booked' => ['bg' => '#fff7ed', 'border' => '#fed7aa', 'text' => '#c2410c', 'label' => 'Pending'],
            'confirmed' => ['bg' => '#f0f9ff', 'border' => '#bae6fd', 'text' => '#0369a1', 'label' => 'Confirmed'],
            'checked_in' => ['bg' => '#f5f3ff', 'border' => '#ddd6fe', 'text' => '#6d28d9', 'label' => 'Checked In'],
            'completed' => ['bg' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#047857', 'label' => 'Completed'],
            'cancelled' => ['bg' => '#fff1f2', 'border' => '#fecdd3', 'text' => '#be123c', 'label' => 'Cancelled'],
            'no_show' => ['bg' => '#f8fafc', 'border' => '#cbd5e1', 'text' => '#475569', 'label' => 'No-show'],
        ];
        $slotOptions = [];
        if (! empty($availability['viable_slots'])) {
            foreach ($availability['viable_slots'] as $slotTime) {
                $slotData = $availability['slots'][$slotTime] ?? [];
                $slotOptions[] = [
                    'time' => $slotTime,
                    'combinations' => $slotData['combinations'] ?? [],
                    'count' => count($slotData['combinations'] ?? []),
                    'is_prefilled' => $prefilledSlot === $slotTime,
                ];
            }
        }
        $selectedSlotRow = $prefilledSlot ? ($availability['slots'][$prefilledSlot] ?? null) : null;
        $dailyStatusCounts = [
            'total' => $appointmentGroups->count(),
            'booked' => 0,
            'confirmed' => 0,
            'checked_in' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        foreach ($appointmentGroups as $group) {
            $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');
            if (array_key_exists($statusValue, $dailyStatusCounts)) {
                $dailyStatusCounts[$statusValue]++;
            } elseif ($statusValue === 'no_show') {
                $dailyStatusCounts['cancelled']++;
            } else {
                $dailyStatusCounts['booked']++;
            }
        }
        $selectedServiceLabels = $services->whereIn('id', $selectedServiceIds)->pluck('name')->values()->all();
    @endphp

    <style>
        .ops-shell{display:grid;gap:22px}
        .ops-card{border:1px solid #e2e8f0;border-radius:28px;background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);box-shadow:0 1px 2px rgba(15,23,42,.05)}
        .ops-card--hero{background:radial-gradient(circle at top left, rgba(14,165,233,.12), transparent 32%),radial-gradient(circle at top right, rgba(15,23,42,.06), transparent 28%),linear-gradient(180deg,#ffffff 0%,#f8fbff 100%)}
        .ops-card__header{padding:22px 24px;border-bottom:1px solid #e2e8f0}
        .ops-card__body{padding:24px}
        .ops-kicker{font-size:11px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:#64748b}
        .ops-title{margin:8px 0 0;font-size:28px;line-height:1.05;font-weight:800;letter-spacing:-.04em;color:#0f172a}
        .ops-subtitle{margin-top:10px;font-size:14px;color:#64748b;max-width:780px}
        .ops-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(360px,.8fr);gap:22px}
        .metrics-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
        .metric-card{border:1px solid #e2e8f0;border-radius:20px;padding:16px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%)}
        .metric-card__label{font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#64748b}
        .metric-card__value{margin-top:8px;font-size:28px;line-height:1;font-weight:800;letter-spacing:-.04em;color:#0f172a}
        .metric-card__meta{margin-top:6px;font-size:12px;color:#64748b}
        .form-stack{display:grid;gap:18px}
        .booking-grid{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:22px}
        .service-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .service-card{position:relative;border:1px solid #dbe4ef;border-radius:22px;padding:16px 18px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);box-shadow:0 1px 2px rgba(15,23,42,.05);cursor:pointer;transition:.18s ease}
        .service-card:hover{transform:translateY(-1px);box-shadow:0 14px 30px rgba(15,23,42,.08);border-color:#c5d3e3}
        .service-card.is-selected{border-color:#0f172a;box-shadow:0 0 0 3px rgba(15,23,42,.08);background:radial-gradient(circle at top right, rgba(59,130,246,.10), transparent 34%),linear-gradient(180deg,#ffffff 0%,#eef4ff 100%)}
        .service-card__title{font-size:15px;font-weight:800;color:#0f172a}
        .service-card__meta{margin-top:6px;font-size:12px;color:#64748b}
        .service-card__badge{display:inline-flex;align-items:center;justify-content:center;min-width:76px;border-radius:999px;border:1px solid #dbe4ef;background:#ffffff;padding:7px 10px;font-size:11px;font-weight:800;color:#475569}
        .selection-input{position:absolute;opacity:0;pointer-events:none}
        .field-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:12px;align-items:end}
        .field-block{display:grid;gap:8px}
        .field-block label{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#64748b}
        .field-input{width:100%;border:1px solid #cbd5e1;border-radius:18px;background:#ffffff;padding:13px 14px;font-size:14px;color:#0f172a;outline:none;transition:.18s ease}
        .field-input:focus{border-color:#94a3b8;box-shadow:0 0 0 4px rgba(148,163,184,.14)}
        .select-input{appearance:none;background:#ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%2364758b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 14px center;padding-right:42px}
        .action-btn{appearance:none;border:1px solid transparent;border-radius:18px;padding:13px 18px;font-size:14px;font-weight:800;cursor:pointer;transition:.18s ease;display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none}
        .action-btn--primary{background:#0f172a;border-color:#0f172a;color:#ffffff;box-shadow:0 10px 22px rgba(15,23,42,.16)}
        .action-btn--primary:hover{background:#1e293b;border-color:#1e293b}
        .action-btn--secondary{background:#ffffff;border-color:#cbd5e1;color:#0f172a}
        .action-btn--secondary:hover{background:#f8fafc}
        .helper-note{border:1px solid #dbe4ef;border-radius:20px;background:#f8fafc;padding:16px}
        .helper-note__title{font-size:13px;font-weight:800;color:#0f172a}
        .helper-note__body{margin-top:6px;font-size:13px;color:#64748b}
        .summary-list{display:grid;gap:10px}
        .summary-pill{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e2e8f0;border-radius:18px;background:#ffffff;padding:12px 14px}
        .summary-pill__label{font-size:13px;font-weight:700;color:#334155}
        .summary-pill__value{font-size:12px;font-weight:800;color:#0f172a;text-align:right}
        .flash{border-radius:18px;padding:14px 16px;font-size:14px;font-weight:700}
        .flash--success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46}
        .flash--error{background:#fff1f2;border:1px solid #fecdd3;color:#9f1239}
        .flash--warn{background:#fff7ed;border:1px solid #fdba74;color:#9a3412}
        .hidden{display:none !important}
        @media (max-width: 1280px){.ops-grid,.booking-grid{grid-template-columns:1fr}}
        @media (max-width: 960px){.metrics-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.service-grid,.field-row{grid-template-columns:1fr}}
        @media (max-width: 640px){.metrics-grid{grid-template-columns:1fr}}
    </style>

    <div class="ops-shell">
        @if (session('success'))
            <div class="flash flash--success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash--error">
                Please fix the following:
                <ul style="margin:8px 0 0 18px; padding:0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($quickCreate['message'])
            <div class="flash flash--warn">{{ $quickCreate['message'] }}</div>
        @endif

        <section class="ops-card ops-card--hero">
            <div class="ops-card__body">
                <div class="ops-kicker">Front Desk Flow</div>
                <h2 class="ops-title">Fast booking, availability, and live day control</h2>
                <div class="ops-subtitle">Choose services, check eligible staff instantly, then confirm the booking from one operational workspace. This page is tuned for speed at reception, not generic admin scaffolding.</div>

                <div class="metrics-grid" style="margin-top:22px;">
                    <div class="metric-card">
                        <div class="metric-card__label">Date</div>
                        <div class="metric-card__value" style="font-size:22px;">{{ \Carbon\Carbon::parse($selectedDate)->format('d M') }}</div>
                        <div class="metric-card__meta">{{ \Carbon\Carbon::parse($selectedDate)->format('l') }}</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Total</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['total'] }}</div>
                        <div class="metric-card__meta">Booked groups</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Pending</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['booked'] }}</div>
                        <div class="metric-card__meta">Needs action</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Confirmed</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['confirmed'] }}</div>
                        <div class="metric-card__meta">Reserved</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Checked In</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['checked_in'] }}</div>
                        <div class="metric-card__meta">On site</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-card__label">Completed</div>
                        <div class="metric-card__value">{{ $dailyStatusCounts['completed'] }}</div>
                        <div class="metric-card__meta">Finished</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="booking-grid">
            <div class="ops-card">
                <div class="ops-card__header">
                    <div class="ops-kicker">Availability Desk</div>
                    <h3 style="margin:8px 0 0;font-size:24px;font-weight:800;letter-spacing:-.03em;color:#0f172a;">Build a booking in three steps</h3>
                    <div class="ops-subtitle" style="max-width:none;">Select the services, choose the clinic date, then review only viable combinations and time slots. If the calendar launched this page with a slot, we keep that preference in focus.</div>
                </div>

                <div class="ops-card__body">
                    <form method="GET" action="{{ route('app.appointments.index') }}" class="form-stack">
                        <input type="hidden" name="slot" value="{{ $prefilledSlot }}">

                        <div>
                            <div class="ops-kicker" style="color:#475569;">Step 1</div>
                            <h4 style="margin:6px 0 0;font-size:18px;font-weight:800;color:#0f172a;">Choose services</h4>
                            <div class="service-grid" style="margin-top:14px;">
                                @forelse ($services as $service)
                                    @php $isSelected = in_array((string) $service->id, $selectedServiceIds, true); @endphp
                                    <label class="service-card {{ $isSelected ? 'is-selected' : '' }}">
                                        <input type="checkbox" name="service_ids[]" value="{{ $service->id }}" class="selection-input service-checkbox" {{ $isSelected ? 'checked' : '' }}>
                                        <div style="display:flex;align-items:start;justify-content:space-between;gap:14px;">
                                            <div>
                                                <div class="service-card__title">{{ $service->name }}</div>
                                                <div class="service-card__meta">{{ $service->description ?: 'Operational service available for appointment scheduling.' }}</div>
                                            </div>
                                            <span class="service-card__badge">{{ (int) ($service->duration_minutes ?? 60) }} mins</span>
                                        </div>
                                    </label>
                                @empty
                                    <div class="flash flash--warn" style="grid-column:1 / -1;">No active services found. Activate clinic services first before checking appointment availability.</div>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <div class="ops-kicker" style="color:#475569;">Step 2</div>
                            <h4 style="margin:6px 0 0;font-size:18px;font-weight:800;color:#0f172a;">Choose the clinic day</h4>
                            <div class="field-row" style="margin-top:14px;">
                                <div class="field-block">
                                    <label for="date">Appointment date</label>
                                    <input id="date" name="date" type="date" value="{{ $selectedDate }}" class="field-input" required>
                                </div>
                                <button type="submit" class="action-btn action-btn--primary" style="min-width:190px;">Check Availability</button>
                                <a href="{{ route('app.appointments.index') }}" class="action-btn action-btn--secondary">Clear</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="form-stack">
                <div class="ops-card">
                    <div class="ops-card__body">
                        <div class="ops-kicker">Current Focus</div>
                        <h3 style="margin:8px 0 0;font-size:22px;font-weight:800;letter-spacing:-.03em;color:#0f172a;">{{ \Carbon\Carbon::parse($selectedDate)->format('l, d M Y') }}</h3>
                        <div class="ops-subtitle" style="margin-top:8px;max-width:none;">{{ $prefilledSlot ? 'Calendar slot preselected at '.$prefilledSlot.'. Choose services and we will hold that preference if it remains viable.' : 'No slot preselected. Use the availability result below to choose the best operational gap.' }}</div>
                        <div class="summary-list" style="margin-top:18px;">
                            <div class="summary-pill">
                                <span class="summary-pill__label">Selected services</span>
                                <span class="summary-pill__value">{{ count($selectedServiceLabels) ? implode(', ', $selectedServiceLabels) : 'None yet' }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">Calendar slot</span>
                                <span class="summary-pill__value">{{ $prefilledSlot ?: 'Not prefilled' }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">Viable slots</span>
                                <span class="summary-pill__value">{{ count($slotOptions) ? count($slotOptions) : 'Check first' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="helper-note">
                    <div class="helper-note__title">Operational guidance</div>
                    <div class="helper-note__body">Front desk should start here, not in the calendar, whenever the booking needs service-based staff matching. The calendar stays best for visual rescheduling and same-day workload awareness.</div>
                </div>

                <a href="{{ route('app.calendar', ['date' => $selectedDate]) }}" class="action-btn action-btn--secondary" style="width:100%;">Open Live Calendar for {{ \Carbon\Carbon::parse($selectedDate)->format('d M') }}</a>
            </div>
        </section>

        @if (! empty($availability))
            <section class="ops-card">
                <div class="ops-card__header">
                    <div class="ops-kicker">Step 3</div>
                    <h3 style="margin:8px 0 0;font-size:24px;font-weight:800;letter-spacing:-.03em;color:#0f172a;">Review viable staff and time options</h3>
                    <div class="ops-subtitle" style="max-width:none;">Only valid combinations are shown. This keeps front desk decisions fast and prevents impossible bookings from being created.</div>
                </div>

                <div class="ops-card__body">
                    <style>
                        .availability-grid{display:grid;gap:22px}
                        .eligibility-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
                        .eligibility-card{border:1px solid #e2e8f0;border-radius:22px;background:#f8fafc;padding:16px}
                        .eligibility-card__title{font-size:14px;font-weight:800;color:#0f172a}
                        .staff-chip-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
                        .staff-chip{display:inline-flex;align-items:center;border-radius:999px;border:1px solid #dbe4ef;background:#ffffff;padding:8px 11px;font-size:12px;font-weight:700;color:#475569}
                        .slot-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
                        .slot-button{appearance:none;border:none;background:none;padding:0;cursor:pointer}
                        .slot-card{border:1px solid #dbe4ef;border-radius:22px;min-height:74px;padding:12px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);box-shadow:0 1px 2px rgba(15,23,42,.05);transition:.18s ease;display:flex;align-items:center;justify-content:center;text-align:center}
                        .slot-button:hover .slot-card{transform:translateY(-1px);box-shadow:0 14px 28px rgba(15,23,42,.08);border-color:#c5d3e3}
                        .slot-card.is-selected{border-color:#0f172a;box-shadow:0 0 0 3px rgba(15,23,42,.08);background:radial-gradient(circle at top right, rgba(14,165,233,.10), transparent 34%),linear-gradient(180deg,#ffffff 0%,#eef4ff 100%)}
                        .slot-card__time{font-size:16px;font-weight:800;color:#0f172a}
                        .slot-card__meta{margin-top:4px;font-size:11px;color:#64748b}
                        .booking-panel{border:1px solid #dbe4ef;border-radius:24px;background:radial-gradient(circle at top left, rgba(14,165,233,.08), transparent 34%),linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);padding:22px}
                        .booking-panel__title{font-size:20px;font-weight:800;color:#0f172a;letter-spacing:-.02em}
                        .booking-panel__subtitle{margin-top:6px;font-size:13px;color:#64748b}
                        .booking-form{display:grid;gap:16px;margin-top:18px}
                        .booking-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
                        .select-input{appearance:none;background:#ffffff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%2364758b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 14px center;padding-right:42px}
                        .booking-textarea{min-height:112px;resize:vertical}
                        @media (max-width: 960px){.eligibility-grid,.booking-form-grid,.slot-grid{grid-template-columns:1fr}}
                    </style>

                    <div class="availability-grid">
                        @if (! empty($availability['services_without_eligible_staff']))
                            <div class="flash flash--error">No active eligible staff assigned for: <strong>{{ implode(', ', $availability['services_without_eligible_staff']) }}</strong></div>
                        @endif

                        @if (! empty($availability['fully_booked_message']))
                            <div class="flash flash--warn">{{ $availability['fully_booked_message'] }}</div>
                        @endif

                        <div class="eligibility-grid">
                            @foreach (($availability['selected_services'] ?? []) as $serviceSummary)
                                <div class="eligibility-card">
                                    <div class="eligibility-card__title">{{ $serviceSummary['name'] ?? 'Service' }}</div>
                                    <div style="margin-top:6px;font-size:12px;color:#64748b;">Eligible staff pool</div>
                                    @if (empty($serviceSummary['eligible_staff']))
                                        <div style="margin-top:10px;font-size:13px;font-weight:700;color:#be123c;">No active staff assigned.</div>
                                    @else
                                        <div class="staff-chip-wrap">
                                            @foreach ($serviceSummary['eligible_staff'] as $staff)
                                                <span class="staff-chip">{{ $staff['full_name'] }} ({{ $staff['role_key'] }})</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if (count($slotOptions))
                            <div>
                                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                                    <div>
                                        <div class="ops-kicker" style="color:#475569;">Choose a viable time</div>
                                        <h4 style="margin:6px 0 0;font-size:18px;font-weight:800;color:#0f172a;">Available booking windows</h4>
                                    </div>
                                    @if ($selectedSlotRow)
                                        <div class="summary-pill" style="padding:10px 12px;">
                                            <span class="summary-pill__label">Selected slot duration</span>
                                            <span class="summary-pill__value">{{ $selectedSlotRow['duration_minutes'] ?? ($availability['duration_minutes'] ?? 0) }} mins</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="slot-grid" style="margin-top:16px;">
                                    @foreach ($slotOptions as $slot)
                                        <button type="button" class="slot-button slot-select-button" data-slot-time="{{ $slot['time'] }}" data-combinations='@json($slot['combinations'])'>
                                            <div class="slot-card {{ $slot['is_prefilled'] ? 'is-selected' : '' }}">
                                                <div>
                                                    <div class="slot-card__time">{{ $slot['time'] }}</div>
                                                    <div class="slot-card__meta">{{ $slot['count'] }} valid combo{{ $slot['count'] === 1 ? '' : 's' }}</div>
                                                </div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div id="selected-slot-card" class="booking-panel hidden">
                            <div class="ops-kicker" style="color:#475569;">Confirm booking</div>
                            <div class="booking-panel__title">Create appointment at <span id="selected-slot-time-label">-</span></div>
                            <div class="booking-panel__subtitle">Pick the valid staff combination, then capture customer details. This preserves the operational staffing logic from the availability engine.</div>

                            <form method="POST" action="{{ route('app.appointments.store') }}" class="booking-form">
                                @csrf
                                <input type="hidden" name="date" value="{{ $selectedDate }}">
                                <input type="hidden" name="slot" id="selected-slot-input" value="">
                                <input type="hidden" name="selected_combination" id="selected-combination-input" value="">
                                @foreach ($selectedServiceIds as $serviceId)
                                    <input type="hidden" name="service_ids[]" value="{{ $serviceId }}">
                                @endforeach

                                <div class="field-block">
                                    <label for="selected_combination_select">Staff combination</label>
                                    <select id="selected_combination_select" class="field-input select-input" required></select>
                                </div>

                                <div class="booking-form-grid">
                                    <div class="field-block">
                                        <label for="customer_full_name">Customer name</label>
                                        <input id="customer_full_name" type="text" name="customer_full_name" value="{{ old('customer_full_name') }}" class="field-input" required>
                                    </div>
                                    <div class="field-block">
                                        <label for="customer_phone">Customer phone</label>
                                        <input id="customer_phone" type="text" name="customer_phone" value="{{ old('customer_phone') }}" class="field-input" required>
                                    </div>
                                </div>

                                <div class="field-block">
                                    <label for="notes">Front desk notes</label>
                                    <textarea id="notes" name="notes" class="field-input booking-textarea">{{ old('notes') }}</textarea>
                                </div>

                                <div style="display:flex;justify-content:flex-end;">
                                    <button type="submit" class="action-btn action-btn--primary">Create Appointment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <section class="ops-grid">
            <div class="ops-card">
                <div class="ops-card__header">
                    <div class="ops-kicker">Live Booking Queue</div>
                    <h3 style="margin:8px 0 0;font-size:24px;font-weight:800;letter-spacing:-.03em;color:#0f172a;">Schedule for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h3>
                    <div class="ops-subtitle" style="max-width:none;">Current appointment groups for the selected day. Front desk can review service allocations and update status without leaving the booking desk.</div>
                </div>

                <div class="ops-card__body">
                    <style>
                        .schedule-list{display:grid;gap:14px}
                        .schedule-card{border:1px solid #e2e8f0;border-radius:24px;background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);padding:18px;box-shadow:0 1px 2px rgba(15,23,42,.05)}
                        .schedule-card__head{display:flex;align-items:start;justify-content:space-between;gap:16px}
                        .schedule-card__time{font-size:16px;font-weight:800;color:#0f172a}
                        .schedule-card__name{margin-top:8px;font-size:16px;font-weight:800;color:#0f172a}
                        .schedule-card__phone{margin-top:4px;font-size:13px;color:#64748b}
                        .status-chip{display:inline-flex;align-items:center;gap:8px;border-radius:999px;border:1px solid transparent;padding:7px 11px;font-size:11px;font-weight:800}
                        .status-dot{width:8px;height:8px;border-radius:999px;background:currentColor;opacity:.78}
                        .service-line{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc;padding:11px 12px}
                        .service-line__name{font-size:13px;font-weight:800;color:#0f172a}
                        .service-line__staff{font-size:12px;color:#64748b;text-align:right}
                        .empty-card{border:1px dashed #cbd5e1;border-radius:24px;background:#f8fafc;padding:42px 24px;text-align:center}
                        .empty-card__title{font-size:16px;font-weight:800;color:#0f172a}
                        .empty-card__body{margin-top:8px;font-size:13px;color:#64748b}
                        .pagination-wrap{margin-top:18px}
                    </style>

                    <div class="schedule-list">
                        @forelse ($appointmentGroups as $group)
                            @php
                                $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (is_string($group->status) ? $group->status : '');
                                $statusStyle = $statusPalette[$statusValue] ?? ['bg' => '#f8fafc', 'border' => '#cbd5e1', 'text' => '#475569', 'label' => \Illuminate\Support\Str::headline(str_replace('_', ' ', $statusValue))];
                                $statusLabel = $group->status instanceof \App\Enums\AppointmentStatus ? $group->status->label() : $statusStyle['label'];
                            @endphp
                            <article class="schedule-card">
                                <div class="schedule-card__head">
                                    <div>
                                        <div class="schedule-card__time">{{ optional($group->starts_at)->format('h:i A') ?? '-' }} @if(optional($group->ends_at)) - {{ optional($group->ends_at)->format('h:i A') }} @endif</div>
                                        <div class="schedule-card__name">{{ $group->customer?->full_name ?? 'Customer' }}</div>
                                        <div class="schedule-card__phone">{{ $group->customer?->phone ?? 'No phone recorded' }}</div>
                                    </div>
                                    <span class="status-chip" style="background: {{ $statusStyle['bg'] }}; border-color: {{ $statusStyle['border'] }}; color: {{ $statusStyle['text'] }};">
                                        <span class="status-dot"></span>
                                        {{ $statusLabel }}
                                    </span>
                                </div>

                                <div style="display:grid;gap:10px;margin-top:16px;">
                                    @foreach ($group->items as $item)
                                        <div class="service-line">
                                            <div class="service-line__name">{{ $item->service?->name ?? 'Service' }}</div>
                                            <div class="service-line__staff">{{ $item->staff?->full_name ?? 'Unassigned' }}@if($item->staff?->role_key) ({{ $item->staff->role_key }}) @endif</div>
                                        </div>
                                    @endforeach
                                </div>

                                <form method="POST" action="{{ route('app.appointments.status', $group) }}" style="margin-top:16px;display:grid;gap:8px;">
                                    @csrf
                                    @method('PATCH')
                                    <label for="status-{{ $group->id }}" style="font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Update status</label>
                                    <select id="status-{{ $group->id }}" name="status" onchange="this.form.submit()" class="field-input select-input">
                                        @foreach ($statusOptions as $option)
                                            @php
                                                $optionValue = $option instanceof \BackedEnum ? $option->value : (is_string($option) ? $option : '');
                                                $optionLabel = $option instanceof \App\Enums\AppointmentStatus ? $option->label() : \Illuminate\Support\Str::headline(str_replace('_', ' ', $optionValue));
                                            @endphp
                                            <option value="{{ $optionValue }}" @selected($statusValue === $optionValue)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </article>
                        @empty
                            <div class="empty-card">
                                <div class="empty-card__title">No appointment groups found</div>
                                <div class="empty-card__body">Once bookings are created for this day, they will appear here for front desk follow-up.</div>
                            </div>
                        @endforelse
                    </div>

                    @if (method_exists($appointmentGroups, 'links'))
                        <div class="pagination-wrap">{{ $appointmentGroups->links() }}</div>
                    @endif
                </div>
            </div>

            <div class="form-stack">
                <div class="ops-card">
                    <div class="ops-card__body">
                        <div class="ops-kicker">What this page tells staff</div>
                        <div style="margin-top:10px;display:grid;gap:10px;">
                            <div class="summary-pill">
                                <span class="summary-pill__label">Which services are selected</span>
                                <span class="summary-pill__value">{{ count($selectedServiceLabels) ? count($selectedServiceLabels) : 0 }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">Who can perform them</span>
                                <span class="summary-pill__value">{{ ! empty($availability['selected_services']) ? 'Mapped live' : 'Check availability' }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">What slots remain viable</span>
                                <span class="summary-pill__value">{{ count($slotOptions) ?: 'Pending' }}</span>
                            </div>
                            <div class="summary-pill">
                                <span class="summary-pill__label">How busy the day already is</span>
                                <span class="summary-pill__value">{{ $dailyStatusCounts['total'] }} booked</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ops-card">
                    <div class="ops-card__body">
                        <div class="ops-kicker">Navigate</div>
                        <h3 style="margin:8px 0 0;font-size:20px;font-weight:800;color:#0f172a;">Need the timeline instead?</h3>
                        <div class="ops-subtitle" style="margin-top:8px;max-width:none;">Use the live calendar for drag rescheduling and visual occupancy. Use this booking desk when you need service-to-staff matching and quick appointment intake.</div>
                        <div style="margin-top:18px;">
                            <a href="{{ route('app.calendar', ['date' => $selectedDate]) }}" class="action-btn action-btn--secondary" style="width:100%;">Open Calendar View</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const serviceCheckboxes = document.querySelectorAll('.service-checkbox');
            const slotButtons = document.querySelectorAll('.slot-select-button');
            const slotCard = document.getElementById('selected-slot-card');
            const slotTimeLabel = document.getElementById('selected-slot-time-label');
            const slotInput = document.getElementById('selected-slot-input');
            const comboInput = document.getElementById('selected-combination-input');
            const comboSelect = document.getElementById('selected_combination_select');
            const prefilledSlot = @json($prefilledSlot);
            const slotAvailable = @json($quickCreate['slot_is_available']);

            serviceCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    this.closest('.service-card')?.classList.toggle('is-selected', this.checked);
                });
            });

            function setCombinations(combinations) {
                if (! comboSelect) {
                    return;
                }

                comboSelect.innerHTML = '';

                combinations.forEach((combo, index) => {
                    const option = document.createElement('option');
                    option.value = combo.payload || '';
                    option.textContent = combo.label || `Combination ${index + 1}`;
                    comboSelect.appendChild(option);
                });

                comboInput.value = comboSelect.value || '';
            }

            function openSlot(time, combinations, triggerButton) {
                slotButtons.forEach((button) => button.querySelector('.slot-card')?.classList.remove('is-selected'));
                triggerButton?.querySelector('.slot-card')?.classList.add('is-selected');

                if (slotTimeLabel) {
                    slotTimeLabel.textContent = time || '-';
                }

                if (slotInput) {
                    slotInput.value = time || '';
                }

                setCombinations(combinations || []);

                if (slotCard) {
                    slotCard.classList.remove('hidden');
                    slotCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            comboSelect?.addEventListener('change', function () {
                comboInput.value = this.value || '';
            });

            slotButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    let combinations = [];

                    try {
                        combinations = JSON.parse(this.dataset.combinations || '[]');
                    } catch (error) {
                        combinations = [];
                    }

                    openSlot(this.dataset.slotTime || '', combinations, this);
                });
            });

            if (prefilledSlot && slotAvailable) {
                const matchedButton = Array.from(slotButtons).find((button) => button.dataset.slotTime === prefilledSlot);

                if (matchedButton) {
                    let combinations = [];

                    try {
                        combinations = JSON.parse(matchedButton.dataset.combinations || '[]');
                    } catch (error) {
                        combinations = [];
                    }

                    openSlot(prefilledSlot, combinations, matchedButton);
                }
            }
        });
    </script>
</x-internal-layout>
