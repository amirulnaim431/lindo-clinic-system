@php
    $mode = $mode ?? 'booking';
    $isCheckInMode = $mode === 'checkin';
    $filters = $filters ?? ['date' => now()->format('Y-m-d')];
    $selectedDate = $filters['date'] ?? now()->format('Y-m-d');
    $appointmentGroups = collect($appointmentGroups ?? []);
    $services = $services ?? collect();
    $serviceCategories = collect($serviceCategories ?? []);
    $plannerBoard = $plannerBoard ?? ['slots' => [], 'staff' => [], 'occupancy' => [], 'capacity_per_slot' => 2];
    $dailyStatusCounts = [
        'total' => $appointmentGroups->count(),
        'checked_in' => 0,
        'completed' => 0,
        'reschedule' => 0,
    ];

    foreach ($appointmentGroups as $group) {
        $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (string) $group->status;
        if ($statusValue === 'checked_in') {
            $dailyStatusCounts['checked_in']++;
        } elseif ($statusValue === 'completed') {
            $dailyStatusCounts['completed']++;
        } elseif (in_array($statusValue, ['cancelled', 'no_show'], true)) {
            $dailyStatusCounts['reschedule']++;
        }
    }

    $summaryCards = [
        ['label' => 'Date', 'value' => \Carbon\Carbon::parse($selectedDate)->format('d M'), 'meta' => \Carbon\Carbon::parse($selectedDate)->format('l')],
        ['label' => 'Total', 'value' => $dailyStatusCounts['total'], 'meta' => null],
        ['label' => 'Checked In', 'value' => $dailyStatusCounts['checked_in'], 'meta' => null],
        ['label' => 'Completed', 'value' => $dailyStatusCounts['completed'], 'meta' => null],
        ['label' => 'Reschedule', 'value' => $dailyStatusCounts['reschedule'], 'meta' => null],
    ];

    $serviceCatalog = $services->mapWithKeys(function ($service) {
        return [
            (string) $service->id => [
                'id' => (string) $service->id,
                'service_code' => (string) $service->service_code,
                'name' => $service->name,
                'category_key' => $service->category_key,
                'category_label' => $service->category_label,
                'consultation_category_key' => $service->consultation_category_key,
                'consultation_category_label' => $service->consultation_category_label,
                'display_category_path' => $service->displayCategoryPath(),
                'duration_minutes' => (int) ($service->duration_minutes ?: 60),
                'price' => $service->price,
                'promo_price' => $service->promo_price,
                'eligible_staff' => $service->staff
                    ->where('is_active', true)
                    ->map(fn ($staff) => [
                        'id' => (string) $staff->id,
                        'full_name' => $staff->full_name,
                        'role_key' => $staff->role_key,
                        'role_label' => $staff->job_title
                            ?: str((string) ($staff->role_key ?: $staff->operational_role ?: 'Staff'))->replace('_', ' ')->title()->toString(),
                    ])
                    ->values()
                    ->all(),
                'option_groups' => $service->optionGroups->map(function ($group) {
                    return [
                        'id' => (string) $group->id,
                        'name' => $group->name,
                        'is_required' => (bool) ($group->pivot?->is_required ?? true),
                        'values' => $group->values->map(fn ($value) => [
                            'id' => (string) $value->id,
                            'label' => $value->label,
                        ])->values()->all(),
                    ];
                })->values()->all(),
            ],
        ];
    })->all();
    $serviceCategoryMeta = $serviceCategories->map(function ($category) {
        return [
            'key' => $category['key'],
            'label' => $category['label'],
            'consultation_groups' => collect($category['consultation_groups'] ?? [])
                ->map(fn ($group) => [
                    'key' => $group['key'],
                    'label' => $group['label'],
                ])
                ->values()
                ->all(),
        ];
    })->values()->all();

    $oldBookingPayload = old('booking_payload');
    $oldBookingPayload = is_string($oldBookingPayload) ? json_decode($oldBookingPayload, true) : null;
@endphp

<x-internal-layout :title="$isCheckInMode ? 'Customer Check-In' : 'Appointments'" :subtitle="null">
    <div class="ops-shell">
        @if (session('success'))
            <div class="flash flash--success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash--error">
                Please fix the following:
                <ul style="margin:8px 0 0 18px;padding:0;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('app.appointments.store') }}" class="stack" id="appointment-builder-form">
            @csrf

            <section class="panel">
                <div class="panel-body">
                    <div class="appointment-top-grid">
                        <div class="field-block appointment-top-grid__date">
                            <label class="field-label" for="date">Appointment date</label>
                            <input id="date" name="date" type="date" value="{{ old('date', $selectedDate) }}" class="form-input" required>
                            <div class="btn-row" style="margin-top:0.85rem;">
                                <button type="button" class="btn btn-secondary" id="view-date-board">View date board</button>
                                <a href="{{ route('app.appointments.index') }}" class="btn btn-secondary">Today</a>
                            </div>
                        </div>

                        <div class="field-block customer-picker appointment-top-grid__name" style="position:relative;">
                            <label class="field-label" for="customer_full_name">Customer name</label>
                            <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', $filters['customer_id'] ?? '') }}">
                            <input id="customer_full_name" name="customer_full_name" type="text" value="{{ old('customer_full_name', $filters['customer_full_name'] ?? '') }}" class="form-input" placeholder="Start typing member name or phone" autocomplete="off" required>
                            <div id="customer_selected_hint" class="small-note {{ old('customer_id', $filters['customer_id'] ?? '') ? '' : 'hidden' }}" style="margin-top:0.55rem;"></div>
                            <div id="customer_suggestions" class="customer-suggestion-list hidden"></div>
                        </div>

                        <div class="field-block appointment-top-grid__phone">
                            <label class="field-label" for="customer_phone">Customer phone</label>
                            <input id="customer_phone" name="customer_phone" type="text" value="{{ old('customer_phone', $filters['customer_phone'] ?? '') }}" class="form-input" placeholder="Phone number" required>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ops-card ops-card--hero">
                <div class="ops-card__body">
                    <div class="ops-kicker">{{ $isCheckInMode ? 'Status' : 'Appointments' }}</div>
                    <h2 class="ops-title">{{ $isCheckInMode ? 'Status' : "Book and manage today's appointments" }}</h2>
                    <div class="metrics-grid" style="margin-top:22px;">
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

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <div class="compact-label">Booking builder</div>
                        <h3 class="panel-title-display" style="font-size:24px;">Choose services and place them onto the staff board</h3>
                    </div>
                </div>
                <div class="panel-body stack">
                    <div class="btn-row" id="service-category-tabs" style="flex-wrap:wrap;">
                        @foreach ($serviceCategories as $category)
                            <button type="button" class="btn btn-secondary service-filter-tab" data-category-tab="{{ $category['key'] }}">{{ $category['label'] }}</button>
                        @endforeach
                    </div>

                    <div class="btn-row hidden" id="consultation-subtabs" style="flex-wrap:wrap;"></div>

                    <div class="field-block">
                        <label class="field-label" for="service-search">Search services</label>
                        <input id="service-search" type="search" class="form-input" placeholder="Search treatment, category, or option">
                    </div>

                    <div id="service-grid" class="selection-grid"></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <div class="compact-label">Selected services</div>
                        <h3 class="panel-title-display" style="font-size:24px;">List of selected services</h3>
                    </div>
                </div>
                <div class="panel-body stack">
                    <div class="small-note">Click a selected service once to make it active, then click an empty box in the board below. Double click a service card to remove it.</div>
                    <div id="selected-service-list" class="selected-service-list"></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <div class="compact-label">Eligible staff board</div>
                        <h3 class="panel-title-display" style="font-size:24px;">Assign services into staff time boxes</h3>
                    </div>
                </div>
                <div class="panel-body stack">
                    <div class="small-note">Only eligible staff with free boxes are shown. Double click your assigned box to remove it and send the service back to the selected list.</div>
                    <div id="planner-board" class="planner-board"></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-body">
                    <div class="field-block">
                        <label class="field-label" for="notes">Remarks</label>
                        <textarea id="notes" name="notes" class="form-input booking-textarea" placeholder="Front desk notes">{{ old('notes', $filters['notes'] ?? '') }}</textarea>
                    </div>
                </div>
            </section>

            <input type="hidden" name="booking_payload" id="booking_payload">

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">Create Appointment</button>
            </div>
        </form>
    </div>

    <div id="service-option-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card booking-option-modal">
                <div class="modal-header">
                    <div>
                        <div class="modal-kicker">Service setup</div>
                        <h3 class="modal-title" id="service-option-modal-title">Select service options</h3>
                        <p class="modal-subtitle" id="service-option-modal-subtitle"></p>
                    </div>
                    <button type="button" class="modal-close" id="service-option-modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="service-option-modal-body" class="stack"></div>
                    <div class="btn-row">
                        <button type="button" class="btn btn-primary" id="service-option-confirm">Confirm service</button>
                        <button type="button" class="btn btn-secondary" id="service-option-cancel">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="confirm-remove-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card confirm-remove-modal">
                <div class="modal-header">
                    <div>
                        <div class="modal-kicker">Confirm change</div>
                        <h3 class="modal-title" id="confirm-remove-title">Remove service?</h3>
                        <p class="modal-subtitle" id="confirm-remove-subtitle">This will return the service to the booking builder.</p>
                    </div>
                    <button type="button" class="modal-close" id="confirm-remove-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="confirm-remove-copy" id="confirm-remove-copy"></div>
                    <div class="btn-row">
                        <button type="button" class="btn btn-primary" id="confirm-remove-approve">Remove service</button>
                        <button type="button" class="btn btn-secondary" id="confirm-remove-cancel">Keep it</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .appointment-top-grid {
            display: grid;
            grid-template-columns: minmax(250px, 320px) minmax(360px, 1.25fr) minmax(280px, 1fr);
            gap: 1.25rem 1.75rem;
            align-items: start;
        }

        .appointment-top-grid__date {
            padding-right: 0.5rem;
        }

        .appointment-top-grid__name {
            min-width: 0;
            padding-left: 0.35rem;
        }

        .appointment-top-grid__phone {
            min-width: 0;
        }

        .service-filter-tab.is-active,
        .consultation-subtab.is-active {
            background: var(--app-accent, #c68b9a);
            color: #fff;
            border-color: var(--app-accent, #c68b9a);
        }

        .consultation-subtab {
            border-radius: 999px;
            padding-inline: 1.2rem;
            background: #f6f0f2;
        }

        .service-picker-card {
            border: 1px solid rgba(26, 19, 23, 0.08);
            border-radius: 24px;
            padding: 1rem 1.1rem;
            background: #fff;
            cursor: pointer;
            transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .service-picker-card:hover {
            transform: translateY(-1px);
            border-color: rgba(198, 139, 154, 0.55);
            box-shadow: 0 18px 30px rgba(198, 139, 154, 0.12);
        }

        .service-picker-card__title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #1a1317;
        }

        .service-picker-card__meta {
            margin-top: 0.35rem;
            color: rgba(26, 19, 23, 0.6);
            font-size: 0.92rem;
        }

        .selected-service-list {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        .selected-service-card {
            border: 1px solid rgba(26, 19, 23, 0.08);
            border-radius: 24px;
            padding: 1rem 1.1rem;
            background: #fff;
            cursor: pointer;
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .selected-service-card.is-active {
            border-color: rgba(198, 139, 154, 0.72);
            box-shadow: 0 16px 28px rgba(198, 139, 154, 0.14);
        }

        .selected-service-card.is-assigned {
            background: #fff7fa;
        }

        .selected-service-card__title {
            font-weight: 700;
            color: #1a1317;
        }

        .selected-service-card__meta,
        .selected-service-card__hint {
            margin-top: 0.35rem;
            color: rgba(26, 19, 23, 0.62);
            font-size: 0.92rem;
        }

        .planner-board {
            display: grid;
            gap: 1rem;
        }

        .planner-staff-card {
            border: 1px solid rgba(26, 19, 23, 0.08);
            border-radius: 28px;
            background: #fff;
            overflow: hidden;
        }

        .planner-staff-card__head {
            padding: 1rem 1.15rem;
            border-bottom: 1px solid rgba(26, 19, 23, 0.06);
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
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
            cursor: pointer;
        }

        .planner-slot-box.is-empty:hover {
            border-color: rgba(198, 139, 154, 0.55);
            background: #fff7fa;
        }

        .planner-slot-box.is-assigned {
            border-style: solid;
            border-color: rgba(198, 139, 154, 0.46);
            background: #fff4f7;
            cursor: pointer;
        }

        .planner-slot-box.is-occupied {
            border-style: solid;
            background: #f4f0f1;
            color: rgba(26, 19, 23, 0.7);
        }

        .planner-slot-box__title {
            font-weight: 700;
            color: #1a1317;
        }

        .booking-option-modal {
            width: min(960px, calc(100vw - 32px));
            max-height: calc(100vh - 48px);
        }

        .confirm-remove-modal {
            width: min(560px, calc(100vw - 32px));
            background:
                radial-gradient(circle at top right, rgba(198, 139, 154, 0.16), transparent 42%),
                linear-gradient(180deg, #fffdfd 0%, #fff7fa 100%);
            border: 1px solid rgba(198, 139, 154, 0.2);
            box-shadow: 0 28px 70px rgba(92, 58, 69, 0.16);
        }

        .confirm-remove-copy {
            border: 1px solid rgba(198, 139, 154, 0.14);
            background: rgba(255, 255, 255, 0.9);
            border-radius: 22px;
            padding: 1rem 1.1rem;
            color: rgba(26, 19, 23, 0.78);
            line-height: 1.65;
        }

        .customer-suggestion-list {
            position: absolute;
            inset: auto 0 auto 0;
            top: calc(100% + 8px);
            z-index: 15;
            display: grid;
            gap: 0.45rem;
            background: #fff;
            border: 1px solid rgba(26, 19, 23, 0.1);
            border-radius: 22px;
            padding: 0.6rem;
            box-shadow: 0 20px 40px rgba(26, 19, 23, 0.12);
        }

        .customer-suggestion {
            border: 0;
            background: #fff;
            border-radius: 16px;
            padding: 0.8rem 0.9rem;
            text-align: left;
            cursor: pointer;
        }

        .customer-suggestion:hover,
        .customer-suggestion.is-active {
            background: #fff6f8;
        }

        .customer-suggestion__name {
            font-weight: 700;
            color: #1a1317;
        }

        .customer-suggestion__meta {
            color: rgba(26, 19, 23, 0.6);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        @media (max-width: 960px) {
            .appointment-top-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .appointment-top-grid__date,
            .appointment-top-grid__name {
                padding-left: 0;
                padding-right: 0;
            }

            .planner-slot-row {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const appointmentRoute = @json(route('app.appointments.index'));
            const customerSearchUrl = @json(route('app.appointments.customer-search'));
            const serviceCatalog = @json($serviceCatalog);
            const serviceCategories = @json($serviceCategoryMeta);
            const plannerBoard = @json($plannerBoard);
            const oldDraft = @json($oldBookingPayload);
            const oldCustomerId = @json(old('customer_id', $filters['customer_id'] ?? ''));

            const dateInput = document.getElementById('date');
            const viewDateBoardButton = document.getElementById('view-date-board');
            const categoryTabs = document.getElementById('service-category-tabs');
            const consultationSubtabs = document.getElementById('consultation-subtabs');
            const serviceGrid = document.getElementById('service-grid');
            const serviceSearchInput = document.getElementById('service-search');
            const selectedServiceList = document.getElementById('selected-service-list');
            const plannerBoardContainer = document.getElementById('planner-board');
            const bookingPayloadInput = document.getElementById('booking_payload');
            const bookingForm = document.getElementById('appointment-builder-form');
            const notesInput = document.getElementById('notes');

            const customerIdInput = document.getElementById('customer_id');
            const customerNameInput = document.getElementById('customer_full_name');
            const customerPhoneInput = document.getElementById('customer_phone');
            const customerHint = document.getElementById('customer_selected_hint');
            const customerSuggestions = document.getElementById('customer_suggestions');

            const modal = document.getElementById('service-option-modal');
            const modalTitle = document.getElementById('service-option-modal-title');
            const modalSubtitle = document.getElementById('service-option-modal-subtitle');
            const modalBody = document.getElementById('service-option-modal-body');
            const modalClose = document.getElementById('service-option-modal-close');
            const modalCancel = document.getElementById('service-option-cancel');
            const modalConfirm = document.getElementById('service-option-confirm');
            const confirmRemoveModal = document.getElementById('confirm-remove-modal');
            const confirmRemoveTitle = document.getElementById('confirm-remove-title');
            const confirmRemoveSubtitle = document.getElementById('confirm-remove-subtitle');
            const confirmRemoveCopy = document.getElementById('confirm-remove-copy');
            const confirmRemoveClose = document.getElementById('confirm-remove-close');
            const confirmRemoveCancel = document.getElementById('confirm-remove-cancel');
            const confirmRemoveApprove = document.getElementById('confirm-remove-approve');

            let activeCategoryKey = serviceCategories[0]?.key || 'consultations';
            let activeConsultationDepartment = serviceCategories.find((group) => group.key === 'consultations')?.consultation_groups?.[0]?.key || 'wellness';
            let selectedServices = [];
            let assignments = {};
            let activeInstanceId = null;
            let activeCustomerRequest = null;
            let pendingModalService = null;
            let pendingModalSelections = {};
            let pendingRemovalAction = null;

            const capacityPerSlot = Number(plannerBoard.capacity_per_slot || 2);
            const boardOccupancy = plannerBoard.occupancy || {};
            const allStaff = Array.isArray(plannerBoard.staff) ? plannerBoard.staff : [];
            const plannerSlots = Array.isArray(plannerBoard.slots) ? plannerBoard.slots : [];

            function formatMoney(value) {
                if (value === null || value === undefined || value === '') {
                    return null;
                }

                return `RM ${Number(value).toLocaleString()}`;
            }

            function createInstance(service, selectedOptions = {}) {
                const instanceId = `${service.id}-${Math.random().toString(36).slice(2, 10)}`;
                const selectedOptionLabels = [];

                (service.option_groups || []).forEach((group) => {
                    const selectedValueId = selectedOptions[group.id];
                    const selectedValue = (group.values || []).find((value) => value.id === selectedValueId);

                    if (selectedValue) {
                        selectedOptionLabels.push(`${group.name}: ${selectedValue.label}`);
                    }
                });

                let displayLabel = service.name;

                if (service.service_code === 'consult_tirze') {
                    const dosageGroup = (service.option_groups || []).find((group) => group.name === 'Dosage');
                    const dosageValueId = dosageGroup ? selectedOptions[dosageGroup.id] : null;
                    const dosageValue = dosageGroup ? (dosageGroup.values || []).find((value) => value.id === dosageValueId) : null;

                    if (dosageValue) {
                        displayLabel = `Tirze ${dosageValue.label}`;
                    }
                }

                return {
                    instance_id: instanceId,
                    service_id: service.id,
                    service_code: service.service_code,
                    name: service.name,
                    display_label: displayLabel,
                    category_key: service.category_key,
                    category_label: service.category_label,
                    consultation_category_key: service.consultation_category_key || null,
                    duration_minutes: service.duration_minutes,
                    eligible_staff_ids: (service.eligible_staff || []).map((staff) => staff.id),
                    selected_options: selectedOptions,
                    selected_option_labels: selectedOptionLabels,
                };
            }

            function getServiceById(serviceId) {
                return serviceCatalog[serviceId] || null;
            }

            function getSelectedService(instanceId) {
                return selectedServices.find((service) => service.instance_id === instanceId) || null;
            }

            function getAssignment(instanceId) {
                return assignments[instanceId] || null;
            }

            function isServiceVisible(service) {
                const query = (serviceSearchInput?.value || '').trim().toLowerCase();
                const serviceMatchesCategory = service.category_key === activeCategoryKey;
                const serviceMatchesConsultationDepartment = activeCategoryKey !== 'consultations'
                    || !activeConsultationDepartment
                    || service.consultation_category_key === activeConsultationDepartment;

                const haystack = [
                    service.name,
                    service.category_label,
                    service.consultation_category_label,
                    ...(service.option_groups || []).flatMap((group) => [group.name, ...(group.values || []).map((value) => value.label)]),
                ]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase();

                if (!query) {
                    return serviceMatchesCategory && serviceMatchesConsultationDepartment;
                }

                return haystack.includes(query);
            }

            function applySearchOverride() {
                const query = (serviceSearchInput?.value || '').trim().toLowerCase();

                if (!query) {
                    return;
                }

                const matchingService = Object.values(serviceCatalog).find((service) => {
                    const haystack = [
                        service.name,
                        service.category_label,
                        service.consultation_category_label,
                        ...(service.option_groups || []).flatMap((group) => [group.name, ...(group.values || []).map((value) => value.label)]),
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    return haystack.includes(query);
                });

                if (!matchingService) {
                    return;
                }

                activeCategoryKey = matchingService.category_key;

                if (matchingService.category_key === 'consultations') {
                    activeConsultationDepartment = matchingService.consultation_category_key || activeConsultationDepartment;
                }
            }

            function renderCategoryTabs() {
                Array.from(categoryTabs.querySelectorAll('[data-category-tab]')).forEach((button) => {
                    button.classList.toggle('is-active', button.dataset.categoryTab === activeCategoryKey);
                });

                const consultationCategory = serviceCategories.find((group) => group.key === 'consultations');
                const consultationGroups = consultationCategory?.consultation_groups || [];
                consultationSubtabs.classList.toggle('hidden', activeCategoryKey !== 'consultations');
                consultationSubtabs.innerHTML = '';

                if (activeCategoryKey !== 'consultations') {
                    return;
                }

                consultationGroups.forEach((group) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `btn btn-secondary consultation-subtab${group.key === activeConsultationDepartment ? ' is-active' : ''}`;
                    button.textContent = group.label;
                    button.dataset.consultationDepartment = group.key;
                    consultationSubtabs.appendChild(button);
                });
            }

            function renderServiceGrid() {
                applySearchOverride();
                renderCategoryTabs();

                const servicesToShow = Object.values(serviceCatalog).filter(isServiceVisible);
                serviceGrid.innerHTML = '';

                if (!servicesToShow.length) {
                    serviceGrid.innerHTML = '<div class="small-note">No services match this category and search.</div>';
                    return;
                }

                servicesToShow.forEach((service) => {
                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = 'service-picker-card';
                    card.innerHTML = `
                        <div class="service-picker-card__title">${service.name}</div>
                        <div class="service-picker-card__meta">${service.display_category_path}</div>
                        <div class="service-picker-card__meta">${service.duration_minutes} mins${formatMoney(service.price) ? ` | ${formatMoney(service.price)}` : ''}</div>
                    `;
                    card.addEventListener('click', () => handleServicePick(service));
                    serviceGrid.appendChild(card);
                });
            }

            function openOptionModal(service) {
                pendingModalService = service;
                pendingModalSelections = {};
                modalTitle.textContent = service.name;
                modalSubtitle.textContent = service.display_category_path || service.category_label || '';
                modalBody.innerHTML = '';

                (service.option_groups || []).forEach((group) => {
                    const block = document.createElement('div');
                    block.className = 'field-block';
                    block.innerHTML = `
                        <div class="field-label">${group.name}</div>
                        <div class="btn-row" style="margin-top:0.75rem;flex-wrap:wrap;">
                            ${(group.values || []).map((value) => `
                                <button type="button" class="btn btn-secondary option-choice" data-group-id="${group.id}" data-value-id="${value.id}">${value.label}</button>
                            `).join('')}
                        </div>
                    `;
                    modalBody.appendChild(block);
                });

                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            }

            function closeOptionModal() {
                pendingModalService = null;
                pendingModalSelections = {};
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            }

            function openConfirmRemoveModal(config) {
                pendingRemovalAction = typeof config?.onApprove === 'function' ? config.onApprove : null;
                confirmRemoveTitle.textContent = config?.title || 'Remove service?';
                confirmRemoveSubtitle.textContent = config?.subtitle || 'This will remove the current assignment.';
                confirmRemoveCopy.textContent = config?.copy || 'Confirm this change to continue.';
                confirmRemoveApprove.textContent = config?.approveLabel || 'Remove service';
                confirmRemoveModal.classList.remove('hidden');
                confirmRemoveModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            }

            function closeConfirmRemoveModal() {
                pendingRemovalAction = null;
                confirmRemoveModal.classList.add('hidden');
                confirmRemoveModal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            }

            function handleServicePick(service) {
                if (Array.isArray(service.option_groups) && service.option_groups.length > 0) {
                    openOptionModal(service);
                    return;
                }

                selectedServices.push(createInstance(service));
                activeInstanceId = selectedServices[selectedServices.length - 1].instance_id;
                renderSelectedServices();
                renderPlannerBoard();
            }

            function renderSelectedServices() {
                selectedServiceList.innerHTML = '';

                if (!selectedServices.length) {
                    selectedServiceList.innerHTML = '<div class="small-note">No services selected yet. Pick a treatment above to start the booking.</div>';
                    return;
                }

                selectedServices.forEach((service) => {
                    const assignment = getAssignment(service.instance_id);
                    const card = document.createElement('button');
                    card.type = 'button';
                    card.className = `selected-service-card${service.instance_id === activeInstanceId ? ' is-active' : ''}${assignment ? ' is-assigned' : ''}`;
                    card.innerHTML = `
                        <div class="selected-service-card__title">${service.display_label}</div>
                        <div class="selected-service-card__meta">${service.category_label}${service.consultation_category_key ? ` / ${serviceCatalog[service.service_id]?.consultation_category_label || ''}` : ''}</div>
                        ${service.selected_option_labels.length ? `<div class="selected-service-card__meta">${service.selected_option_labels.join(' | ')}</div>` : ''}
                        <div class="selected-service-card__hint">${assignment ? `Assigned to ${assignment.staff_name} at ${assignment.start_time}` : 'Click this card, then click an empty staff box below.'}</div>
                    `;
                    card.addEventListener('click', () => {
                        activeInstanceId = service.instance_id;
                        renderSelectedServices();
                    });
                    card.addEventListener('dblclick', () => {
                        openConfirmRemoveModal({
                            title: 'Remove selected service?',
                            subtitle: service.display_label,
                            copy: `This will remove ${service.display_label} from the current booking draft.`,
                            approveLabel: 'Remove service',
                            onApprove: () => {
                                delete assignments[service.instance_id];
                                selectedServices = selectedServices.filter((row) => row.instance_id !== service.instance_id);

                                if (activeInstanceId === service.instance_id) {
                                    activeInstanceId = selectedServices.find((row) => !assignments[row.instance_id])?.instance_id || selectedServices[0]?.instance_id || null;
                                }

                                renderSelectedServices();
                                renderPlannerBoard();
                            },
                        });
                    });
                    selectedServiceList.appendChild(card);
                });
            }

            function getVisibleStaffRows() {
                if (!selectedServices.length) {
                    return [];
                }

                const relevantStaffIds = new Set(selectedServices.flatMap((service) => service.eligible_staff_ids || []));

                return allStaff.filter((staff) => {
                    if (!relevantStaffIds.has(staff.id)) {
                        return false;
                    }

                    return plannerSlots.some((slot) => {
                        const occupancy = boardOccupancy?.[staff.id]?.[slot.time];
                        const existingCount = Number(occupancy?.count || 0);
                        const draftCount = Object.values(assignments).filter((assignment) => assignment.staff_id === staff.id && assignment.start_time === slot.time).length;

                        return (existingCount + draftCount) < capacityPerSlot;
                    });
                });
            }

            function buildSlotBoxes(staff, slot) {
                const occupancy = boardOccupancy?.[staff.id]?.[slot.time] || { count: 0, appointments: [] };
                const existingAppointments = Array.isArray(occupancy.appointments) ? occupancy.appointments : [];
                const draftAssignments = Object.values(assignments)
                    .filter((assignment) => assignment.staff_id === staff.id && assignment.start_time === slot.time)
                    .sort((left, right) => left.slot_index - right.slot_index);

                const boxes = [];

                for (let slotIndex = 1; slotIndex <= capacityPerSlot; slotIndex++) {
                    if (existingAppointments[slotIndex - 1]) {
                        boxes.push({
                            type: 'occupied',
                            slot_index: slotIndex,
                            appointment: existingAppointments[slotIndex - 1],
                        });
                        continue;
                    }

                    const draft = draftAssignments.find((assignment) => Number(assignment.slot_index) === slotIndex);

                    if (draft) {
                        boxes.push({
                            type: 'assigned',
                            slot_index: slotIndex,
                            assignment: draft,
                            service: getSelectedService(draft.instance_id),
                        });
                        continue;
                    }

                    boxes.push({
                        type: 'empty',
                        slot_index: slotIndex,
                    });
                }

                return boxes;
            }

            function assignActiveServiceToBox(staff, slot, slotIndex) {
                if (!activeInstanceId) {
                    window.alert('Choose a selected service first.');
                    return;
                }

                const service = getSelectedService(activeInstanceId);

                if (!service) {
                    return;
                }

                if (!(service.eligible_staff_ids || []).includes(staff.id)) {
                    window.alert(`${service.display_label} is not assigned under ${staff.full_name}.`);
                    return;
                }

                assignments[service.instance_id] = {
                    instance_id: service.instance_id,
                    staff_id: staff.id,
                    staff_name: staff.full_name,
                    start_time: slot.time,
                    slot_index: slotIndex,
                };

                activeInstanceId = selectedServices.find((row) => !assignments[row.instance_id])?.instance_id || service.instance_id;
                renderSelectedServices();
                renderPlannerBoard();
            }

            function renderPlannerBoard() {
                plannerBoardContainer.innerHTML = '';
                const visibleStaffRows = getVisibleStaffRows();

                if (!selectedServices.length) {
                    plannerBoardContainer.innerHTML = '<div class="small-note">Select at least one service to load the staff board.</div>';
                    return;
                }

                if (!visibleStaffRows.length) {
                    plannerBoardContainer.innerHTML = '<div class="small-note">No eligible staff have empty boxes for the selected services on this date.</div>';
                    return;
                }

                visibleStaffRows.forEach((staff) => {
                    const card = document.createElement('div');
                    card.className = 'planner-staff-card';

                    const head = document.createElement('div');
                    head.className = 'planner-staff-card__head';
                    head.innerHTML = `
                        <div>
                            <div class="selection-card__title">${staff.full_name}</div>
                            <div class="small-note">${staff.role_label || staff.appointment_group_label || 'Staff'}</div>
                        </div>
                        <div class="small-note">${plannerSlots.length} booking windows</div>
                    `;
                    card.appendChild(head);

                    plannerSlots.forEach((slot) => {
                        const row = document.createElement('div');
                        row.className = 'planner-slot-row';
                        row.innerHTML = `<div class="planner-slot-label">${slot.label}</div>`;

                        buildSlotBoxes(staff, slot).forEach((box) => {
                            const boxButton = document.createElement('button');
                            boxButton.type = 'button';

                            if (box.type === 'occupied') {
                                boxButton.className = 'planner-slot-box is-occupied';
                                boxButton.disabled = true;
                                boxButton.innerHTML = `
                                    <div class="planner-slot-box__title">${box.appointment.customer_name}</div>
                                    <div>${box.appointment.service_name}</div>
                                `;
                            } else if (box.type === 'assigned') {
                                boxButton.className = 'planner-slot-box is-assigned';
                                boxButton.innerHTML = `
                                    <div class="planner-slot-box__title">${box.service?.display_label || 'Selected service'}</div>
                                    <div>${box.service?.selected_option_labels?.join(' | ') || 'Draft booking'}</div>
                                `;
                                boxButton.addEventListener('click', () => {
                                    activeInstanceId = box.assignment.instance_id;
                                    renderSelectedServices();
                                });
                                boxButton.addEventListener('dblclick', () => {
                                    openConfirmRemoveModal({
                                        title: 'Remove staff assignment?',
                                        subtitle: `${box.service?.display_label || 'Selected service'} • ${staff.full_name}`,
                                        copy: `This will remove the booking from ${staff.full_name} at ${slot.label} and return the service to your selected services list.`,
                                        approveLabel: 'Remove assignment',
                                        onApprove: () => {
                                            delete assignments[box.assignment.instance_id];
                                            activeInstanceId = box.assignment.instance_id;
                                            renderSelectedServices();
                                            renderPlannerBoard();
                                        },
                                    });
                                });
                            } else {
                                boxButton.className = 'planner-slot-box is-empty';
                                boxButton.innerHTML = `
                                    <div class="planner-slot-box__title">Empty box</div>
                                    <div>${activeInstanceId ? 'Click to assign active service here' : 'Choose a selected service first'}</div>
                                `;
                                boxButton.addEventListener('click', () => assignActiveServiceToBox(staff, slot, box.slot_index));
                            }

                            row.appendChild(boxButton);
                        });

                        card.appendChild(row);
                    });

                    plannerBoardContainer.appendChild(card);
                });
            }

            function buildPayloadForSubmit() {
                return {
                    services: selectedServices.map((service) => ({
                        instance_id: service.instance_id,
                        service_id: service.service_id,
                        selected_options: service.selected_options || {},
                    })),
                    assignments: Object.values(assignments).map((assignment) => ({
                        instance_id: assignment.instance_id,
                        staff_id: assignment.staff_id,
                        start_time: assignment.start_time,
                        slot_index: assignment.slot_index,
                    })),
                };
            }

            function renderSelectedCustomer(customer) {
                if (!customer) {
                    customerHint.textContent = '';
                    customerHint.classList.add('hidden');
                    return;
                }

                const parts = [customer.full_name || 'Customer'];
                if (customer.phone) {
                    parts.push(customer.phone);
                }
                if (customer.membership_code) {
                    parts.push(`Member ${customer.membership_code}`);
                }

                customerHint.textContent = `Linked to existing customer: ${parts.join(' | ')}`;
                customerHint.classList.remove('hidden');
            }

            function hideCustomerSuggestions() {
                customerSuggestions.innerHTML = '';
                customerSuggestions.classList.add('hidden');
            }

            function selectCustomer(customer) {
                customerIdInput.value = customer.id || '';
                customerNameInput.value = customer.full_name || '';
                if (customer.phone) {
                    customerPhoneInput.value = customer.phone;
                }
                renderSelectedCustomer(customer);
                hideCustomerSuggestions();
            }

            function clearSelectedCustomer() {
                customerIdInput.value = '';
                renderSelectedCustomer(null);
            }

            async function searchCustomers(query) {
                if (activeCustomerRequest) {
                    activeCustomerRequest.abort();
                }

                activeCustomerRequest = new AbortController();

                try {
                    const response = await fetch(`${customerSearchUrl}?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        signal: activeCustomerRequest.signal,
                    });

                    const result = await response.json().catch(() => ({ customers: [] }));
                    const customers = Array.isArray(result.customers) ? result.customers : [];

                    if (!customers.length) {
                        hideCustomerSuggestions();
                        return;
                    }

                    customerSuggestions.innerHTML = '';
                    customers.forEach((customer, index) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = `customer-suggestion${index === 0 ? ' is-active' : ''}`;
                        button.innerHTML = `
                            <div class="customer-suggestion__name">${customer.full_name || 'Customer'}</div>
                            <div class="customer-suggestion__meta">${customer.phone || 'No phone'}${customer.membership_code ? ` | Member ${customer.membership_code}` : ''}${customer.current_package ? ` | ${customer.current_package}` : ''}</div>
                        `;
                        button.addEventListener('click', () => selectCustomer(customer));
                        customerSuggestions.appendChild(button);
                    });

                    customerSuggestions.classList.remove('hidden');
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        hideCustomerSuggestions();
                    }
                }
            }

            categoryTabs.addEventListener('click', function (event) {
                const button = event.target.closest('[data-category-tab]');
                if (!button) {
                    return;
                }

                activeCategoryKey = button.dataset.categoryTab || activeCategoryKey;
                renderServiceGrid();
            });

            consultationSubtabs.addEventListener('click', function (event) {
                const button = event.target.closest('[data-consultation-department]');
                if (!button) {
                    return;
                }

                activeConsultationDepartment = button.dataset.consultationDepartment || activeConsultationDepartment;
                renderServiceGrid();
            });

            serviceSearchInput?.addEventListener('input', renderServiceGrid);

            viewDateBoardButton?.addEventListener('click', function () {
                const params = new URLSearchParams();
                params.set('date', dateInput.value || @json($selectedDate));
                window.location.href = `${appointmentRoute}?${params.toString()}`;
            });

            modalBody.addEventListener('click', function (event) {
                const button = event.target.closest('.option-choice');
                if (!button) {
                    return;
                }

                const groupId = button.dataset.groupId;
                const valueId = button.dataset.valueId;
                pendingModalSelections[groupId] = valueId;

                modalBody.querySelectorAll(`[data-group-id="${groupId}"]`).forEach((choice) => {
                    choice.classList.toggle('btn-primary', choice.dataset.valueId === valueId);
                    choice.classList.toggle('btn-secondary', choice.dataset.valueId !== valueId);
                });
            });

            modalConfirm.addEventListener('click', function () {
                if (!pendingModalService) {
                    return;
                }

                const missingRequiredGroup = (pendingModalService.option_groups || []).find((group) => group.is_required && !pendingModalSelections[group.id]);

                if (missingRequiredGroup) {
                    window.alert(`Choose ${missingRequiredGroup.name} before confirming this service.`);
                    return;
                }

                selectedServices.push(createInstance(pendingModalService, { ...pendingModalSelections }));
                activeInstanceId = selectedServices[selectedServices.length - 1].instance_id;
                closeOptionModal();
                renderSelectedServices();
                renderPlannerBoard();
            });

            [modalClose, modalCancel].forEach((button) => {
                button?.addEventListener('click', closeOptionModal);
            });

            [confirmRemoveClose, confirmRemoveCancel].forEach((button) => {
                button?.addEventListener('click', closeConfirmRemoveModal);
            });

            confirmRemoveApprove?.addEventListener('click', function () {
                const action = pendingRemovalAction;
                closeConfirmRemoveModal();
                action?.();
            });

            modal?.addEventListener('click', function (event) {
                if (event.target === modal || event.target === modal.firstElementChild) {
                    closeOptionModal();
                }
            });

            confirmRemoveModal?.addEventListener('click', function (event) {
                if (event.target === confirmRemoveModal || event.target === confirmRemoveModal.firstElementChild) {
                    closeConfirmRemoveModal();
                }
            });

            customerNameInput?.addEventListener('input', function () {
                const query = this.value.trim();
                clearSelectedCustomer();

                if (query.length < 2) {
                    hideCustomerSuggestions();
                    return;
                }

                searchCustomers(query);
            });

            customerNameInput?.addEventListener('blur', function () {
                window.setTimeout(hideCustomerSuggestions, 120);
            });

            customerPhoneInput?.addEventListener('input', function () {
                if (customerIdInput.value) {
                    clearSelectedCustomer();
                }
            });

            bookingForm.addEventListener('submit', function (event) {
                if (!selectedServices.length) {
                    event.preventDefault();
                    window.alert('Select at least one service before creating the appointment.');
                    return;
                }

                const unassigned = selectedServices.filter((service) => !assignments[service.instance_id]);

                if (unassigned.length) {
                    event.preventDefault();
                    activeInstanceId = unassigned[0].instance_id;
                    renderSelectedServices();
                    renderPlannerBoard();
                    window.alert('Assign every selected service into a staff time box before submitting.');
                    return;
                }

                bookingPayloadInput.value = JSON.stringify(buildPayloadForSubmit());
            });

            document.addEventListener('click', function (event) {
                if (!event.target.closest('.customer-picker')) {
                    hideCustomerSuggestions();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeOptionModal();
                }

                if (event.key === 'Escape' && !confirmRemoveModal.classList.contains('hidden')) {
                    closeConfirmRemoveModal();
                }
            });

            if (oldCustomerId && customerNameInput.value) {
                renderSelectedCustomer({
                    id: oldCustomerId,
                    full_name: customerNameInput.value,
                    phone: customerPhoneInput.value,
                    membership_code: '',
                });
            }

            if (oldDraft && Array.isArray(oldDraft.services)) {
                selectedServices = oldDraft.services
                    .map((row) => {
                        const service = getServiceById(row.service_id);
                        if (!service) {
                            return null;
                        }

                        const instance = createInstance(service, row.selected_options || {});
                        instance.instance_id = row.instance_id || instance.instance_id;
                        return instance;
                    })
                    .filter(Boolean);

                assignments = Object.fromEntries(
                    (oldDraft.assignments || [])
                        .filter((row) => row.instance_id && row.staff_id && row.start_time)
                        .map((row) => [row.instance_id, {
                            instance_id: row.instance_id,
                            staff_id: row.staff_id,
                            staff_name: allStaff.find((staff) => staff.id === row.staff_id)?.full_name || 'Staff',
                            start_time: row.start_time,
                            slot_index: Number(row.slot_index || 1),
                        }])
                );

                activeInstanceId = selectedServices.find((service) => !assignments[service.instance_id])?.instance_id || selectedServices[0]?.instance_id || null;
            }

            renderCategoryTabs();
            renderServiceGrid();
            renderSelectedServices();
            renderPlannerBoard();
        });
    </script>
</x-internal-layout>
