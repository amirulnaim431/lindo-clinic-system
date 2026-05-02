@php
    $mode = $mode ?? 'booking';
    $isCheckInMode = $mode === 'checkin';
    $filters = $filters ?? ['date' => now()->format('Y-m-d')];
    $selectedDate = $filters['date'] ?? now()->format('Y-m-d');
    $appointmentGroups = collect($appointmentGroups ?? []);
    $services = $services ?? collect();
    $serviceCategories = collect($serviceCategories ?? []);
    $plannerBoard = $plannerBoard ?? ['slots' => [], 'staff' => [], 'occupancy' => [], 'capacity_per_slot' => 2];
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
    $trafficCounts = [
        'checked_in' => $appointmentGroups->filter(fn ($group) => ($group->status instanceof \BackedEnum ? $group->status->value : (string) $group->status) === 'checked_in')->count(),
        'completed' => $appointmentGroups->filter(fn ($group) => ($group->status instanceof \BackedEnum ? $group->status->value : (string) $group->status) === 'completed')->count(),
        'reschedule' => $appointmentGroups->filter(fn ($group) => in_array(($group->status instanceof \BackedEnum ? $group->status->value : (string) $group->status), ['cancelled', 'no_show'], true))->count(),
    ];
    $trafficListRows = $appointmentGroups->map(function ($group) use ($selectedDate) {
        $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (string) $group->status;
        $customer = $group->customer;
        $membership = $customer?->current_package ?: ($customer?->membership_type ?: ($customer?->membership_code ?: '-'));

        return [
            'id' => (string) $group->id,
            'status' => $statusValue,
            'customer_id' => (string) ($customer?->id ?: ''),
            'time' => optional($group->starts_at)->format('g:i A') ?: '-',
            'customer' => $customer?->full_name ?: 'Walk-in customer',
            'phone' => $customer?->phone ?: '-',
            'membership' => $membership,
            'treatment' => $group->items->map(fn ($item) => $item->displayServiceName())->filter()->unique()->implode(' | ') ?: '-',
            'pic' => $group->items->map(fn ($item) => $item->displayStaffName())->filter()->unique()->implode(' | ') ?: '-',
            'booked_by' => $group->source ? str((string) $group->source)->replace('_', ' ')->title()->toString() : 'Admin',
            'remarks' => $group->notes ?: '-',
            'booking_url' => route('app.appointments.index', array_filter([
                'date' => $group->starts_at?->format('Y-m-d'),
                'customer_id' => $customer?->id,
                'followup_id' => (string) $group->id,
                'return_to' => 'reschedule',
                'return_date' => $selectedDate,
            ])),
        ];
    })->values();
    $trafficLists = [
        'checked_in' => $trafficListRows->where('status', 'checked_in')->values(),
        'completed' => $trafficListRows->where('status', 'completed')->values(),
        'reschedule' => $trafficListRows->filter(fn ($row) => in_array($row['status'], ['cancelled', 'no_show'], true))->values(),
    ];
    $prefillCustomer = $prefillCustomer ?? null;
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

        <div class="stack" id="appointment-builder-form">
            @unless ($isCheckInMode)
                <section class="panel">
                    <div class="panel-body">
                        <div class="appointment-top-grid">
                            <div class="field-block appointment-top-grid__date">
                                <label class="field-label" for="date">Appointment date</label>
                                <input id="date" name="date" type="date" value="{{ old('date', $selectedDate) }}" class="form-input" required>
                                <div class="btn-row" style="margin-top:0.85rem;">
                                    <button type="button" class="btn btn-secondary" id="view-date-board">View calendar board</button>
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
            @endunless

            @if ($isCheckInMode)
                <section class="panel">
                    <div class="panel-header">
                        <div>
                            <div class="compact-label">Clinic traffic</div>
                            <h3 class="panel-title-display" style="font-size:24px;">Today's customer check-in board</h3>
                        </div>
                    </div>
                    <div class="panel-body stack">
                        <div class="checkin-metrics">
                            <button type="button" class="metric-card checkin-metric-button" data-traffic-list="checked_in">
                                <div class="metric-card__label">Checked In</div>
                                <div class="metric-card__value">{{ $trafficCounts['checked_in'] }}</div>
                            </button>
                            <button type="button" class="metric-card checkin-metric-button" data-traffic-list="completed">
                                <div class="metric-card__label">Completed</div>
                                <div class="metric-card__value">{{ $trafficCounts['completed'] }}</div>
                            </button>
                            <button type="button" class="metric-card checkin-metric-button" data-traffic-list="reschedule">
                                <div class="metric-card__label">Rescheduled</div>
                                <div class="metric-card__value">{{ $trafficCounts['reschedule'] }}</div>
                            </button>
                        </div>

                        <div class="checkin-list">
                            @forelse ($appointmentGroups as $group)
                                @php
                                    $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (string) $group->status;
                                    $statusLabel = $group->status instanceof \App\Enums\AppointmentStatus ? $group->status->label() : str($statusValue)->replace('_', ' ')->title();
                                    $customer = $group->customer;
                                    $membershipLabel = $customer?->current_package ?: ($customer?->membership_type ?: ($customer?->membership_code ?: 'No package'));
                                    $membershipTone = str($membershipLabel)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();
                                    $treatments = $group->items->map(fn ($item) => $item->displayServiceName())->filter()->implode(' | ');
                                    $picNames = $group->items->map(fn ($item) => $item->displayStaffName())->filter()->unique()->implode(' | ');
                                    $statusTone = match ($statusValue) {
                                        'checked_in' => 'warning',
                                        'completed' => 'success',
                                        default => 'danger',
                                    };
                                @endphp
                                <div class="checkin-card">
                                    <div>
                                        <div class="checkin-card__time">{{ optional($group->starts_at)->format('g:i A') ?: '-' }}</div>
                                        <div class="checkin-card__name">
                                            <span class="traffic-light traffic-light--{{ $statusTone }}" title="{{ $statusLabel }}"></span>
                                            <span>{{ $customer?->full_name ?: 'Walk-in customer' }}</span>
                                            @if ($membershipLabel !== 'No package')
                                                <span class="membership-pill membership-pill--{{ $membershipTone }}">{{ $membershipLabel }}</span>
                                            @endif
                                        </div>
                                        <div class="checkin-card__meta">
                                            {{ $customer?->phone ?: 'No phone' }} &middot; {{ $treatments ?: 'No treatment listed' }}
                                        </div>
                                        <div class="checkin-card__meta">PIC: {{ $picNames ?: '-' }}</div>
                                    </div>
                                    <div class="checkin-card__actions">
                                        <span class="status-chip">{{ $statusLabel }}</span>
                                        @if ($statusValue !== 'checked_in')
                                            <form method="POST" action="{{ route('app.appointments.status', $group) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="checked_in">
                                                <button type="submit" class="btn btn-secondary">Check in</button>
                                            </form>
                                        @endif
                                        @if ($statusValue !== 'completed')
                                            <form method="POST" action="{{ route('app.appointments.status', $group) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="btn btn-primary">Complete</button>
                                            </form>
                                        @endif
                                        <button type="button" class="btn btn-secondary checkin-remark-action" data-action-url="{{ route('app.appointments.status', $group) }}" data-status="cancelled" data-title="Reschedule customer" data-customer="{{ $customer?->full_name ?: 'Walk-in customer' }}">Reschedule</button>
                                        <button type="button" class="btn btn-secondary checkin-remark-action" data-action-url="{{ route('app.appointments.status', $group) }}" data-status="no_show" data-title="Cancel customer appointment" data-customer="{{ $customer?->full_name ?: 'Walk-in customer' }}">Cancel</button>
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">
                                    <div class="empty-state__title">No booked customers for this date</div>
                                    <div class="empty-state__body">Booked appointments will appear here for front desk check-in and traffic control.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>
            @else
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
                    <div id="selected-service-list" class="selected-service-list"></div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <div class="compact-label">Eligible staff board</div>
                        <h3 class="panel-title-display" style="font-size:24px;">Assign services into staff time boxes</h3>
                    </div>
                    <div class="break-toggle-wrap">
                        <span class="traffic-light traffic-light--danger"></span>
                        <label class="break-toggle">
                            <input type="checkbox" id="break-mode-toggle">
                            <span class="break-toggle__track"></span>
                            <span class="break-toggle__label">Break</span>
                        </label>
                    </div>
                </div>
                <div class="panel-body stack">
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

            <div class="btn-row">
                <button type="button" class="btn btn-primary" id="create-appointment-submit">Create Appointment</button>
            </div>
            @endif
        </div>

        <form method="POST" action="{{ route('app.appointments.store') }}" id="appointment-submit-form" class="hidden">
            @csrf
            <input type="hidden" name="date" id="submit_date">
            <input type="hidden" name="customer_id" id="submit_customer_id">
            <input type="hidden" name="customer_full_name" id="submit_customer_full_name">
            <input type="hidden" name="customer_phone" id="submit_customer_phone">
            <input type="hidden" name="notes" id="submit_notes">
            <input type="hidden" name="booking_payload" id="booking_payload">
            <input type="hidden" name="followup_id" id="submit_followup_id">
            <input type="hidden" name="return_to" id="submit_return_to">
            <input type="hidden" name="return_date" id="submit_return_date">
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
                    <div class="btn-row service-option-actions">
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

    <div id="break-remark-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card break-remark-modal">
                <div class="modal-header">
                    <div>
                        <div class="modal-kicker">Block time</div>
                        <h3 class="modal-title" id="break-remark-title">Add break remark</h3>
                        <p class="modal-subtitle" id="break-remark-subtitle">Tell the team why this slot is unavailable.</p>
                    </div>
                    <button type="button" class="modal-close" id="break-remark-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="break-remark-context" id="break-remark-context"></div>
                    <label class="field-block" for="break-remark-input">
                        <span class="field-label">Remark / reason</span>
                        <textarea id="break-remark-input" class="form-input booking-textarea" rows="4" placeholder="Example: lunch break, machine cleaning, staff prayer break"></textarea>
                    </label>
                    <p class="form-error hidden" id="break-remark-error">Please enter a remark before blocking this slot.</p>
                    <div class="btn-row">
                        <button type="button" class="btn btn-primary" id="break-remark-save">Block slot</button>
                        <button type="button" class="btn btn-secondary" id="break-remark-cancel">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="calendar-board-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage modal-stage--wide">
            <div class="modal-card calendar-board-modal">
                <div class="modal-header">
                    <div>
                        <div class="modal-kicker">Reference board</div>
                        <h3 class="modal-title">View calendar board</h3>
                        <p class="modal-subtitle">Check PIC availability without leaving the booking flow.</p>
                    </div>
                    <button type="button" class="modal-close" id="calendar-board-modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body calendar-board-modal__body">
                    <iframe id="calendar-board-frame" class="calendar-board-frame" title="Calendar board" src="about:blank"></iframe>
                </div>
            </div>
        </div>
    </div>

    <div id="traffic-list-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage modal-stage--wide">
            <div class="modal-card traffic-list-modal">
                <div class="modal-header">
                    <div>
                        <div class="modal-kicker">Printable list</div>
                        <h3 class="modal-title" id="traffic-list-title">Customer list</h3>
                        <p class="modal-subtitle" id="traffic-list-subtitle">Review and print this traffic list.</p>
                    </div>
                    <button type="button" class="modal-close" id="traffic-list-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body traffic-list-body">
                    <div id="traffic-list-content"></div>
                    <div class="btn-row screen-actions">
                        <button type="button" class="btn btn-primary" id="traffic-list-print">Print list</button>
                        <button type="button" class="btn btn-secondary" id="traffic-list-cancel">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="checkin-remark-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage">
            <div class="modal-card confirm-remove-modal">
                <div class="modal-header">
                    <div>
                        <div class="modal-kicker">Front desk remark</div>
                        <h3 class="modal-title" id="checkin-remark-title">Update appointment</h3>
                        <p class="modal-subtitle" id="checkin-remark-subtitle">Add a reason or remark before updating this customer.</p>
                    </div>
                    <button type="button" class="modal-close" id="checkin-remark-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="checkin-remark-form" class="stack">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" id="checkin-remark-status">
                        <div class="field-block">
                            <label class="field-label" for="checkin-remark-notes">Remark / reason</label>
                            <textarea id="checkin-remark-notes" name="notes" class="form-input booking-textarea" placeholder="Example: customer requested another date, duplicate entry, cancelled at counter"></textarea>
                        </div>
                        <div class="btn-row">
                            <button type="submit" class="btn btn-primary">Save update</button>
                            <button type="button" class="btn btn-secondary" id="checkin-remark-cancel">Cancel</button>
                        </div>
                    </form>
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
            border-bottom: 1px solid rgba(198, 139, 154, 0.22);
            background:
                radial-gradient(circle at top left, rgba(198, 139, 154, 0.18), transparent 42%),
                linear-gradient(135deg, #fff7fa 0%, #fff 70%);
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

        .planner-slot-box.is-blocked {
            border-style: solid;
            border-color: rgba(151, 51, 63, 0.28);
            background: #fff0f1;
            color: #7f2f3b;
        }

        .planner-slot-box__title {
            font-weight: 700;
            color: #1a1317;
        }

        .booking-option-modal {
            width: min(960px, calc(100vw - 32px));
            max-height: calc(100vh - 48px);
        }

        .modal-stage--wide {
            width: 100%;
            max-width: none;
            padding: 1.25rem;
        }

        .calendar-board-modal {
            width: min(1400px, calc(100vw - 2.5rem));
            margin: auto;
            min-height: min(88vh, 980px);
            max-height: min(92vh, 1100px);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .calendar-board-modal .modal-header {
            padding-right: 4.25rem;
        }

        .calendar-board-modal .modal-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 2;
        }

        .calendar-board-modal__body {
            flex: 1 1 auto;
            min-height: 0;
            padding: 0;
        }

        .calendar-board-frame {
            width: 100%;
            min-height: min(74vh, 900px);
            border: 0;
            border-radius: 0 0 28px 28px;
            background: #fffdfd;
        }

        .booking-option-modal .modal-header,
        .confirm-remove-modal .modal-header,
        .break-remark-modal .modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .booking-option-modal .modal-header > :first-child,
        .confirm-remove-modal .modal-header > :first-child,
        .break-remark-modal .modal-header > :first-child {
            min-width: 0;
            flex: 1 1 auto;
        }

        .booking-option-modal .modal-close,
        .confirm-remove-modal .modal-close,
        .break-remark-modal .modal-close {
            flex: 0 0 auto;
            align-self: flex-start;
            margin-left: auto;
        }

        .confirm-remove-modal {
            width: min(560px, calc(100vw - 32px));
            background:
                radial-gradient(circle at top right, rgba(198, 139, 154, 0.16), transparent 42%),
                linear-gradient(180deg, #fffdfd 0%, #fff7fa 100%);
            border: 1px solid rgba(198, 139, 154, 0.2);
            box-shadow: 0 28px 70px rgba(92, 58, 69, 0.16);
        }

        .break-remark-modal {
            width: min(620px, calc(100vw - 32px));
            background:
                radial-gradient(circle at 88% 8%, rgba(198, 139, 154, 0.2), transparent 36%),
                linear-gradient(145deg, #fffdfd 0%, #fff7fa 58%, #fff 100%);
            border: 1px solid rgba(198, 139, 154, 0.22);
            box-shadow: 0 32px 80px rgba(78, 43, 55, 0.18);
        }

        .break-remark-context {
            border: 1px solid rgba(198, 139, 154, 0.18);
            background: rgba(255, 255, 255, 0.86);
            border-radius: 22px;
            padding: 1rem 1.1rem;
            color: rgba(26, 19, 23, 0.76);
            line-height: 1.55;
            margin-bottom: 1rem;
        }

        .break-remark-modal .booking-textarea {
            min-height: 118px;
            resize: vertical;
        }

        .form-error {
            margin: 0.65rem 0 0;
            color: #9b2f42;
            font-size: 0.95rem;
        }

        .confirm-remove-copy {
            border: 1px solid rgba(198, 139, 154, 0.14);
            background: rgba(255, 255, 255, 0.9);
            border-radius: 22px;
            padding: 1rem 1.1rem;
            color: rgba(26, 19, 23, 0.78);
            line-height: 1.65;
        }

        .service-option-actions {
            margin-top: 1.5rem;
            gap: 1rem;
        }

        .confirm-remove-modal .btn-row {
            gap: 1rem;
            margin-top: 1.25rem;
            justify-content: flex-start;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
            font-weight: 700;
            color: #1a1317;
        }

        .customer-suggestion__meta {
            color: rgba(26, 19, 23, 0.6);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .traffic-light {
            width: 0.72rem;
            height: 0.72rem;
            border-radius: 999px;
            display: inline-block;
            box-shadow: 0 0 0 4px rgba(26, 19, 23, 0.04);
        }

        .traffic-light--danger {
            background: #d85062;
        }

        .traffic-light--warning {
            background: #e8b84f;
        }

        .traffic-light--success {
            background: #58ad72;
        }

        .break-toggle-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.55rem 0.75rem;
            border: 1px solid rgba(198, 139, 154, 0.18);
            border-radius: 999px;
            background: #fff8fa;
        }

        .break-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            cursor: pointer;
            font-weight: 700;
        }

        .break-toggle input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .break-toggle__track {
            width: 48px;
            height: 26px;
            border-radius: 999px;
            background: #eadde1;
            position: relative;
            transition: background 0.18s ease;
        }

        .break-toggle__track::after {
            content: '';
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #fff;
            position: absolute;
            top: 3px;
            left: 3px;
            box-shadow: 0 5px 12px rgba(26, 19, 23, 0.18);
            transition: transform 0.18s ease;
        }

        .break-toggle input:checked + .break-toggle__track {
            background: #d85062;
        }

        .break-toggle input:checked + .break-toggle__track::after {
            transform: translateX(22px);
        }

        .checkin-metrics {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .checkin-metric-button {
            appearance: none;
            border: 1px solid rgba(26, 19, 23, 0.08);
            text-align: left;
            cursor: pointer;
            transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .checkin-metric-button:hover {
            transform: translateY(-1px);
            border-color: rgba(198, 139, 154, 0.5);
            box-shadow: 0 18px 36px rgba(92, 58, 69, 0.1);
        }

        .checkin-list {
            display: grid;
            gap: 0.9rem;
        }

        .checkin-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border: 1px solid rgba(26, 19, 23, 0.08);
            border-radius: 26px;
            background: #fff;
            padding: 1rem 1.1rem;
            box-shadow: 0 14px 28px rgba(92, 58, 69, 0.06);
        }

        .checkin-card__time {
            color: #c68b9a;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .checkin-card__name {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex-wrap: wrap;
            margin-top: 0.25rem;
            color: #1a1317;
            font-weight: 800;
            font-size: 1.05rem;
        }

        .membership-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.22rem 0.62rem;
            font-size: 0.72rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border: 1px solid rgba(198, 139, 154, 0.24);
            background: #fff4f7;
            color: #8f5262;
        }

        .membership-pill--bronze {
            background: #f7d0a8;
            border-color: #e0aa72;
            color: #6f3e1d;
        }

        .membership-pill--silver {
            background: #e8eaee;
            border-color: #c5cbd3;
            color: #4b5663;
        }

        .membership-pill--gold {
            background: #f9e4a7;
            border-color: #dfbd52;
            color: #765a10;
        }

        .membership-pill--new {
            background: #d9ecd0;
            border-color: #b9d8aa;
            color: #356325;
        }

        .checkin-card__meta {
            margin-top: 0.25rem;
            color: rgba(26, 19, 23, 0.62);
            font-size: 0.92rem;
        }

        .checkin-card__actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.55rem;
            flex-wrap: wrap;
        }

        .traffic-list-modal {
            width: min(1180px, calc(100vw - 2.5rem));
            max-height: min(92vh, 980px);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .traffic-list-modal .modal-header {
            padding-right: 4.25rem;
        }

        .traffic-list-modal .modal-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 2;
        }

        .traffic-list-body {
            overflow: auto;
        }

        .traffic-print-header {
            text-align: center;
            margin-bottom: 1.1rem;
        }

        .traffic-print-title {
            font-size: 1.45rem;
            font-weight: 800;
            color: #1a1317;
        }

        .traffic-print-subtitle {
            margin-top: 0.35rem;
            color: rgba(26, 19, 23, 0.62);
        }

        .traffic-list-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .traffic-list-table th {
            background: #070707;
            color: #fff;
            font-size: 0.74rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .traffic-list-table th,
        .traffic-list-table td {
            border: 1px solid rgba(7, 7, 7, 0.16);
            padding: 0.65rem 0.7rem;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        .traffic-list-empty {
            border: 1px solid rgba(198, 139, 154, 0.16);
            border-radius: 24px;
            padding: 1.4rem;
            text-align: center;
            color: rgba(26, 19, 23, 0.66);
            background: #fff;
        }

        .traffic-followup-tools {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .traffic-followup-tools .form-input {
            width: 180px;
        }

        .traffic-customer-link {
            color: #1a1317;
            font-weight: 700;
            text-decoration: none;
        }

        .traffic-booking-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 0.45rem;
            padding: 0.38rem 0.7rem;
            border: 1px solid rgba(198, 124, 154, 0.32);
            border-radius: 999px;
            color: #7a3f57;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            background: rgba(255, 247, 250, 0.92);
        }

        .traffic-booking-action:hover {
            border-color: rgba(198, 124, 154, 0.7);
            background: #fff;
        }

        .followup-check {
            margin-right: 0.35rem;
        }

        .followup-check input:checked + span::after {
            content: 'Followed up';
            color: #1f7a48;
            font-weight: 700;
            margin-right: 0.35rem;
        }

        @media print {
            body.is-printing-traffic * {
                visibility: hidden !important;
            }

            body.is-printing-traffic #traffic-list-content,
            body.is-printing-traffic #traffic-list-content * {
                visibility: visible !important;
            }

            body.is-printing-traffic #traffic-list-content {
                position: fixed;
                inset: 0;
                padding: 12mm;
                background: #fff;
            }

            body.is-printing-traffic .screen-actions {
                display: none !important;
            }

            body.is-printing-traffic .traffic-list-table {
                font-size: 10px;
            }

            body.is-printing-traffic .traffic-list-table th,
            body.is-printing-traffic .traffic-list-table td {
                border: 1px solid #000 !important;
                color: #000 !important;
                padding: 5px;
            }

            body.is-printing-traffic .traffic-list-table th {
                background: #000 !important;
                color: #fff !important;
            }
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

            .checkin-metrics {
                grid-template-columns: 1fr;
            }

            .checkin-card {
                align-items: stretch;
                flex-direction: column;
            }

            .checkin-card__actions {
                justify-content: flex-start;
            }

            .traffic-list-table {
                min-width: 920px;
            }
        }
    </style>

    @php
        $prefillCustomerPayload = $prefillCustomer ? [
            'id' => (string) $prefillCustomer->id,
            'full_name' => $prefillCustomer->full_name,
            'phone' => $prefillCustomer->phone,
        ] : null;
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const isCheckInMode = @json($isCheckInMode);
            const appointmentRoute = @json(route('app.appointments.index'));
            const calendarRoute = @json(route('app.calendar'));
            const customerSearchUrl = @json(route('app.appointments.customer-search'));
            const slotBlockUrl = @json(route('app.appointments.slot-blocks.store'));
            const csrfToken = @json(csrf_token());
            const selectedDate = @json($selectedDate);
            const prefillCustomer = @json($prefillCustomerPayload);
            const serviceCatalog = @json($serviceCatalog);
            const serviceCategories = @json($serviceCategoryMeta);
            const plannerBoard = @json($plannerBoard);
            const oldDraft = @json($oldBookingPayload);
            const oldCustomerId = @json(old('customer_id', $filters['customer_id'] ?? ''));
            const trafficLists = @json($trafficLists);
            const trafficListMeta = {
                checked_in: {
                    title: 'Checked In Customers',
                    subtitle: 'Customers currently checked in for the selected date.',
                },
                completed: {
                    title: 'Completed Customers',
                    subtitle: 'Customers marked complete for the selected date.',
                },
                reschedule: {
                    title: 'Rescheduled / Removed Customers',
                    subtitle: 'Customers cancelled, rescheduled, or removed from active clinic traffic.',
                },
            };

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
            const appointmentSubmitForm = document.getElementById('appointment-submit-form');
            const createAppointmentSubmit = document.getElementById('create-appointment-submit');
            const notesInput = document.getElementById('notes');
            const submitDateInput = document.getElementById('submit_date');
            const submitCustomerIdInput = document.getElementById('submit_customer_id');
            const submitCustomerNameInput = document.getElementById('submit_customer_full_name');
            const submitCustomerPhoneInput = document.getElementById('submit_customer_phone');
            const submitNotesInput = document.getElementById('submit_notes');
            const submitFollowupIdInput = document.getElementById('submit_followup_id');
            const submitReturnToInput = document.getElementById('submit_return_to');
            const submitReturnDateInput = document.getElementById('submit_return_date');
            const urlParams = new URLSearchParams(window.location.search);
            const rescheduleFollowupId = urlParams.get('followup_id') || '';
            const returnToList = urlParams.get('return_to') || '';
            const returnDate = urlParams.get('return_date') || '';
            const completedFollowupId = urlParams.get('followup_done') || '';

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
            const calendarBoardModal = document.getElementById('calendar-board-modal');
            const calendarBoardModalClose = document.getElementById('calendar-board-modal-close');
            const calendarBoardFrame = document.getElementById('calendar-board-frame');
            const trafficListModal = document.getElementById('traffic-list-modal');
            const trafficListTitle = document.getElementById('traffic-list-title');
            const trafficListSubtitle = document.getElementById('traffic-list-subtitle');
            const trafficListContent = document.getElementById('traffic-list-content');
            const trafficListClose = document.getElementById('traffic-list-close');
            const trafficListCancel = document.getElementById('traffic-list-cancel');
            const trafficListPrint = document.getElementById('traffic-list-print');
            const checkinRemarkModal = document.getElementById('checkin-remark-modal');
            const checkinRemarkForm = document.getElementById('checkin-remark-form');
            const checkinRemarkStatus = document.getElementById('checkin-remark-status');
            const checkinRemarkNotes = document.getElementById('checkin-remark-notes');
            const checkinRemarkTitle = document.getElementById('checkin-remark-title');
            const checkinRemarkSubtitle = document.getElementById('checkin-remark-subtitle');
            const checkinRemarkClose = document.getElementById('checkin-remark-close');
            const checkinRemarkCancel = document.getElementById('checkin-remark-cancel');
            const breakModeToggle = document.getElementById('break-mode-toggle');
            const breakRemarkModal = document.getElementById('break-remark-modal');
            const breakRemarkTitle = document.getElementById('break-remark-title');
            const breakRemarkSubtitle = document.getElementById('break-remark-subtitle');
            const breakRemarkContext = document.getElementById('break-remark-context');
            const breakRemarkInput = document.getElementById('break-remark-input');
            const breakRemarkError = document.getElementById('break-remark-error');
            const breakRemarkSave = document.getElementById('break-remark-save');
            const breakRemarkClose = document.getElementById('break-remark-close');
            const breakRemarkCancel = document.getElementById('break-remark-cancel');

            let activeCategoryKey = serviceCategories[0]?.key || 'consultations';
            let activeConsultationDepartment = serviceCategories.find((group) => group.key === 'consultations')?.consultation_groups?.[0]?.key || 'wellness';
            let selectedServices = [];
            let assignments = {};
            let activeInstanceId = null;
            let activeCustomerRequest = null;
            let pendingModalService = null;
            let pendingModalSelections = {};
            let pendingRemovalAction = null;
            let breakModeEnabled = false;
            let assignmentClickTimer = null;
            let pendingBreakRemarkResolve = null;
            let isSubmittingAppointment = false;

            const capacityPerSlot = Number(plannerBoard.capacity_per_slot || 2);
            const boardOccupancy = plannerBoard.occupancy || {};
            const allStaff = Array.isArray(plannerBoard.staff) ? plannerBoard.staff : [];
            const plannerSlots = Array.isArray(plannerBoard.slots) ? plannerBoard.slots : [];

            function getDraftStorageKey() {
                return `lindo-appointment-builder-v2:${dateInput?.value || selectedDate}`;
            }

            function buildCalendarBoardUrl() {
                const params = new URLSearchParams();
                params.set('date', dateInput?.value || selectedDate);
                params.set('embedded', '1');
                params.set('compact', '1');

                return `${calendarRoute}?${params.toString()}`;
            }

            function reloadBookingBoardForSelectedDate() {
                const nextDate = dateInput?.value || '';

                if (!nextDate || nextDate === selectedDate || isCheckInMode) {
                    return false;
                }

                const url = new URL(appointmentRoute, window.location.origin);
                url.searchParams.set('date', nextDate);

                if (customerIdInput?.value) {
                    url.searchParams.set('customer_id', customerIdInput.value);
                }

                if (customerNameInput?.value) {
                    url.searchParams.set('customer_full_name', customerNameInput.value);
                }

                if (customerPhoneInput?.value) {
                    url.searchParams.set('customer_phone', customerPhoneInput.value);
                }

                if (notesInput?.value) {
                    url.searchParams.set('notes', notesInput.value);
                }

                if (rescheduleFollowupId) {
                    url.searchParams.set('followup_id', rescheduleFollowupId);
                }

                if (returnToList) {
                    url.searchParams.set('return_to', returnToList);
                }

                if (returnDate) {
                    url.searchParams.set('return_date', returnDate);
                }

                window.location.href = url.toString();

                return true;
            }

            function openCalendarBoardModal() {
                if (!calendarBoardModal || !calendarBoardFrame) {
                    return;
                }

                persistDraft();
                calendarBoardFrame.src = buildCalendarBoardUrl();
                calendarBoardModal.classList.remove('hidden');
                calendarBoardModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
            }

            function closeCalendarBoardModal() {
                if (!calendarBoardModal || !calendarBoardFrame) {
                    return;
                }

                calendarBoardModal.classList.add('hidden');
                calendarBoardModal.setAttribute('aria-hidden', 'true');
                calendarBoardFrame.src = 'about:blank';
                document.body.classList.remove('modal-open');
            }

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function membershipPillHtml(membership) {
                if (!membership) {
                    return '';
                }

                const tone = String(membership)
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-|-$/g, '');

                return `<span class="membership-pill membership-pill--${escapeHtml(tone)}">${escapeHtml(membership)}</span>`;
            }

            function openTrafficListModal(listKey) {
                if (!trafficListModal || !trafficListContent) {
                    return;
                }

                const meta = trafficListMeta[listKey] || trafficListMeta.checked_in;
                const rows = Array.isArray(trafficLists?.[listKey]) ? trafficLists[listKey] : [];
                trafficListTitle.textContent = meta.title;
                trafficListSubtitle.textContent = meta.subtitle;
                const rescheduleDateControl = listKey === 'reschedule' ? `
                    <div class="traffic-followup-tools">
                        <label class="field-label" for="traffic-followup-date">Follow-up date</label>
                        <input id="traffic-followup-date" type="date" class="form-input" value="${escapeHtml(dateInput?.value || @json($selectedDate))}">
                    </div>
                ` : '';

                if (!rows.length) {
                    trafficListContent.innerHTML = `
                        <div class="traffic-print-header">
                            <div class="traffic-print-title">${escapeHtml(meta.title)}</div>
                            <div class="traffic-print-subtitle">${escapeHtml(meta.subtitle)}</div>
                        </div>
                        ${rescheduleDateControl}
                        <div class="traffic-list-empty">No customers in this list yet.</div>
                    `;
                } else {
                    trafficListContent.innerHTML = `
                        <div class="traffic-print-header">
                            <div class="traffic-print-title">${escapeHtml(meta.title)}</div>
                            <div class="traffic-print-subtitle">${escapeHtml(meta.subtitle)}</div>
                        </div>
                        ${rescheduleDateControl}
                        <table class="traffic-list-table">
                            <thead>
                                <tr>
                                    <th style="width:5%;">No.</th>
                                    <th style="width:9%;">Time</th>
                                    <th style="width:20%;">Customer</th>
                                    <th style="width:11%;">Phone</th>
                                    <th style="width:11%;">Package</th>
                                    <th style="width:18%;">Treatment</th>
                                    <th style="width:13%;">PIC</th>
                                    <th style="width:7%;">Booked By</th>
                                    <th style="width:15%;">Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows.map((row, index) => `
                                    <tr>
                                        <td>${index + 1}</td>
                                        <td>${escapeHtml(row.time)}</td>
                                        <td>
                                            ${listKey === 'reschedule' ? `<label class="followup-check"><input type="checkbox" data-followup-id="${escapeHtml(row.id)}"> <span></span></label>` : ''}
                                            <a href="${escapeHtml(row.booking_url || '#')}" class="traffic-customer-link">${escapeHtml(row.customer)}</a>
                                            ${listKey === 'reschedule' ? `<br><a href="${escapeHtml(row.booking_url || '#')}" class="traffic-booking-action" data-followup-booking="${escapeHtml(row.id)}">Book again</a>` : ''}
                                        </td>
                                        <td>${escapeHtml(row.phone)}</td>
                                        <td>${escapeHtml(row.membership)}</td>
                                        <td>${escapeHtml(row.treatment)}</td>
                                        <td>${escapeHtml(row.pic)}</td>
                                        <td>${escapeHtml(row.booked_by)}</td>
                                        <td>${escapeHtml(row.remarks)}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `;
                }

                if (listKey === 'reschedule') {
                    const datePicker = trafficListContent.querySelector('#traffic-followup-date');
                    datePicker?.addEventListener('change', function () {
                        const url = new URL(window.location.href);
                        url.searchParams.set('mode', 'checkin');
                        url.searchParams.set('status', 'reschedule');
                        url.searchParams.set('date', datePicker.value);
                        window.location.href = url.toString();
                    });

                    trafficListContent.querySelectorAll('[data-followup-id]').forEach((checkbox) => {
                        const storageKey = `lindo-reschedule-followup:${checkbox.dataset.followupId}`;
                        if (completedFollowupId && completedFollowupId === checkbox.dataset.followupId) {
                            window.localStorage.setItem(storageKey, '1');
                        }
                        checkbox.checked = window.localStorage.getItem(storageKey) === '1';
                        checkbox.addEventListener('change', function () {
                            if (checkbox.checked) {
                                window.localStorage.setItem(storageKey, '1');
                            } else {
                                window.localStorage.removeItem(storageKey);
                            }
                        });
                    });
                }

                trafficListModal.classList.remove('hidden');
                trafficListModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
            }

            function closeTrafficListModal() {
                trafficListModal?.classList.add('hidden');
                trafficListModal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open', 'is-printing-traffic');
            }

            function openCheckinRemarkModal(button) {
                if (!checkinRemarkModal || !checkinRemarkForm) {
                    return;
                }

                checkinRemarkForm.action = button.dataset.actionUrl || '';
                checkinRemarkStatus.value = button.dataset.status || '';
                checkinRemarkNotes.value = '';
                checkinRemarkTitle.textContent = button.dataset.title || 'Update appointment';
                checkinRemarkSubtitle.textContent = `Add a reason or remark for ${button.dataset.customer || 'this customer'}.`;
                checkinRemarkModal.classList.remove('hidden');
                checkinRemarkModal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                window.setTimeout(() => checkinRemarkNotes?.focus(), 80);
            }

            function closeCheckinRemarkModal() {
                checkinRemarkModal?.classList.add('hidden');
                checkinRemarkModal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
            }

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
                        displayLabel = `Consult Tirze ${dosageValue.label}`;
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
                        ${formatMoney(service.price) ? `<div class="service-picker-card__meta">${formatMoney(service.price)}</div>` : ''}
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

                const orderedGroups = [
                    ...(service.option_groups || []).filter((group) => group.name === 'Dosage'),
                    ...(service.option_groups || []).filter((group) => group.name === 'Session'),
                    ...(service.option_groups || []).filter((group) => group.name === 'Maintenance'),
                    ...(service.option_groups || []).filter((group) => !['Dosage', 'Session', 'Maintenance'].includes(group.name)),
                ];

                orderedGroups.forEach((group) => {
                    const block = document.createElement('div');
                    block.className = 'field-block';
                    block.innerHTML = `
                        <div class="field-label">${group.name}</div>
                        <div class="btn-row" style="margin-top:0.75rem;flex-wrap:wrap;row-gap:0.85rem;column-gap:0.85rem;">
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

            function openBreakRemarkModal(staff, slot) {
                return new Promise((resolve) => {
                    if (!breakRemarkModal || !breakRemarkInput) {
                        resolve(null);
                        return;
                    }

                    pendingBreakRemarkResolve = resolve;
                    breakRemarkTitle.textContent = 'Block staff time';
                    breakRemarkSubtitle.textContent = 'A remark is required so the team knows why this box is unavailable.';
                    breakRemarkContext.innerHTML = `
                        <strong>${escapeHtml(staff.full_name)}</strong><br>
                        ${escapeHtml(slot.label)}
                    `;
                    breakRemarkInput.value = '';
                    breakRemarkError?.classList.add('hidden');
                    breakRemarkModal.classList.remove('hidden');
                    breakRemarkModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                    window.setTimeout(() => breakRemarkInput.focus(), 80);
                });
            }

            function closeBreakRemarkModal(value = null) {
                breakRemarkModal?.classList.add('hidden');
                breakRemarkModal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');

                if (pendingBreakRemarkResolve) {
                    pendingBreakRemarkResolve(value);
                    pendingBreakRemarkResolve = null;
                }
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
                        ${service.selected_option_labels.length ? `<div class="selected-service-card__meta">${service.selected_option_labels.join(' | ')}</div>` : ''}
                        ${assignment ? `<div class="selected-service-card__hint">${assignment.staff_name} • ${assignment.start_time}</div>` : ''}
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

                persistDraft();
            }

            function getVisibleStaffRows() {
                if (breakModeEnabled) {
                    return allStaff.filter((staff) => plannerSlots.some((slot) => {
                        const occupancy = boardOccupancy?.[staff.id]?.[slot.time];
                        const existingCount = Number(occupancy?.count || 0);
                        const blockCount = Array.isArray(occupancy?.blocks) ? occupancy.blocks.length : 0;

                        return (existingCount + blockCount) < capacityPerSlot;
                    }));
                }

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
                const existingBlocks = Array.isArray(occupancy.blocks) ? occupancy.blocks : [];
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

                    const block = existingBlocks.find((row) => Number(row.slot_index) === slotIndex);

                    if (block) {
                        boxes.push({
                            type: 'blocked',
                            slot_index: slotIndex,
                            reason: block.reason,
                        });
                        continue;
                    }

                    const draft = draftAssignments.find((assignment) => Number(assignment.slot_index) === slotIndex || assignment.span_slots);

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

                if (!selectedServices.length && !breakModeEnabled) {
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
                            } else if (box.type === 'blocked') {
                                boxButton.className = 'planner-slot-box is-blocked';
                                boxButton.disabled = true;
                                boxButton.innerHTML = `
                                    <div class="planner-slot-box__title">Blocked</div>
                                    <div>${escapeHtml(box.reason || 'Break')}</div>
                                `;
                            } else if (box.type === 'assigned') {
                                boxButton.className = 'planner-slot-box is-assigned';
                                boxButton.innerHTML = `
                                    <div class="planner-slot-box__title">${box.service?.display_label || 'Selected service'}</div>
                                    <div>${box.service?.selected_option_labels?.join(' | ') || 'Draft booking'}</div>
                                `;
                                boxButton.addEventListener('click', () => {
                                    window.clearTimeout(assignmentClickTimer);
                                    assignmentClickTimer = window.setTimeout(() => {
                                        openConfirmRemoveModal({
                                        title: 'Remove staff assignment?',
                                        subtitle: `${box.service?.display_label || 'Selected service'} - ${staff.full_name}`,
                                        copy: `This will remove the booking from ${staff.full_name} at ${slot.label} and return the service to your selected services list.`,
                                        approveLabel: 'Remove assignment',
                                        onApprove: () => {
                                            delete assignments[box.assignment.instance_id];
                                            activeInstanceId = box.assignment.instance_id;
                                            renderSelectedServices();
                                            renderPlannerBoard();
                                        },
                                        });
                                    }, 240);
                                });
                                boxButton.addEventListener('dblclick', (event) => {
                                    event.preventDefault();
                                    window.clearTimeout(assignmentClickTimer);
                                    const slotBoxes = buildSlotBoxes(staff, slot);
                                    const canMerge = slotBoxes.every((slotBox) => {
                                        return slotBox.type === 'empty'
                                            || (slotBox.type === 'assigned' && slotBox.assignment.instance_id === box.assignment.instance_id);
                                    });

                                    if (!canMerge) {
                                        window.alert('This hour already has another booking or block, so it cannot be merged.');
                                        return;
                                    }

                                    assignments[box.assignment.instance_id] = {
                                        ...box.assignment,
                                        slot_index: 1,
                                        span_slots: true,
                                    };
                                    renderPlannerBoard();
                                    return;
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
                                boxButton.addEventListener('click', () => {
                                    if (breakModeEnabled) {
                                        blockStaffSlot(staff, slot, box.slot_index);
                                        return;
                                    }

                                    assignActiveServiceToBox(staff, slot, box.slot_index);
                                });
                            }

                            row.appendChild(boxButton);
                        });

                        card.appendChild(row);
                    });

                    plannerBoardContainer.appendChild(card);
                });

                persistDraft();
            }

            async function blockStaffSlot(staff, slot, slotIndex) {
                const reason = await openBreakRemarkModal(staff, slot);

                if (!reason || !reason.trim()) {
                    return;
                }

                try {
                    const response = await fetch(slotBlockUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            staff_id: staff.id,
                            date: dateInput?.value || @json($selectedDate),
                            start_time: slot.time,
                            slot_index: slotIndex,
                            reason: reason.trim(),
                        }),
                    });
                    const result = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const errors = Object.values(result.errors || {}).flat();
                        window.alert(errors[0] || result.message || 'Unable to block this slot.');
                        return;
                    }

                    const occupancy = boardOccupancy[staff.id]?.[slot.time] || { count: 0, appointments: [], blocks: [] };
                    occupancy.blocks = Array.isArray(occupancy.blocks) ? occupancy.blocks : [];
                    occupancy.blocks.push({
                        id: result.id,
                        slot_index: result.slot_index,
                        reason: result.reason,
                    });
                    boardOccupancy[staff.id] = boardOccupancy[staff.id] || {};
                    boardOccupancy[staff.id][slot.time] = occupancy;
                    renderPlannerBoard();
                } catch (error) {
                    window.alert('Unable to block this slot. Please try again.');
                }
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
                        span_slots: !!assignment.span_slots,
                    })),
                };
            }

            function buildDraftPayload() {
                return {
                    customer_id: customerIdInput.value || '',
                    customer_full_name: customerNameInput.value || '',
                    customer_phone: customerPhoneInput.value || '',
                    notes: notesInput?.value || '',
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
                    active_instance_id: activeInstanceId,
                };
            }

            function persistDraft() {
                try {
                    window.sessionStorage.setItem(getDraftStorageKey(), JSON.stringify(buildDraftPayload()));
                } catch (error) {
                    // Ignore storage failures; the in-memory draft still works.
                }
            }

            function clearPersistedDraft() {
                try {
                    window.sessionStorage.removeItem(getDraftStorageKey());
                } catch (error) {
                    // Ignore storage failures.
                }
            }

            function resetBookingBuilderDraft({ clearStorage = false } = {}) {
                selectedServices = [];
                assignments = {};
                activeInstanceId = null;
                renderSelectedServices();
                renderPlannerBoard();

                if (clearStorage) {
                    clearPersistedDraft();
                }
            }

            function restorePersistedDraft() {
                if (oldDraft && Array.isArray(oldDraft.services)) {
                    return false;
                }

                try {
                    const raw = window.sessionStorage.getItem(getDraftStorageKey());

                    if (!raw) {
                        return false;
                    }

                    const storedDraft = JSON.parse(raw);

                    if (!storedDraft || !Array.isArray(storedDraft.services)) {
                        return false;
                    }

                    if (storedDraft.customer_id) {
                        customerIdInput.value = storedDraft.customer_id;
                    }

                    if (storedDraft.customer_full_name && !customerNameInput.value) {
                        customerNameInput.value = storedDraft.customer_full_name;
                    }

                    if (storedDraft.customer_phone && !customerPhoneInput.value) {
                        customerPhoneInput.value = storedDraft.customer_phone;
                    }

                    if (storedDraft.notes && notesInput && !notesInput.value) {
                        notesInput.value = storedDraft.notes;
                    }

                    selectedServices = storedDraft.services
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
                        (storedDraft.assignments || [])
                            .filter((row) => row.instance_id && row.staff_id && row.start_time)
                            .map((row) => [row.instance_id, {
                                instance_id: row.instance_id,
                                staff_id: row.staff_id,
                                staff_name: allStaff.find((staff) => staff.id === row.staff_id)?.full_name || 'Staff',
                                start_time: row.start_time,
                                slot_index: Number(row.slot_index || 1),
                                span_slots: !!row.span_slots,
                            }])
                    );

                    activeInstanceId = storedDraft.active_instance_id
                        || selectedServices.find((service) => !assignments[service.instance_id])?.instance_id
                        || selectedServices[0]?.instance_id
                        || null;

                    return selectedServices.length > 0;
                } catch (error) {
                    return false;
                }
            }

            function renderSelectedCustomer(customer) {
                if (!customerHint) {
                    return;
                }

                if (!customer) {
                    customerHint.textContent = '';
                    customerHint.classList.add('hidden');
                    return;
                }

                const parts = [customer.full_name || 'Customer'];
                if (customer.phone) {
                    parts.push(customer.phone);
                }
                const membership = customer.current_package || customer.membership_type || customer.membership_code;
                if (membership) {
                    parts.push(membership);
                }

                customerHint.textContent = `Linked to existing customer: ${parts.join(' | ')}`;
                customerHint.classList.remove('hidden');
            }

            function hideCustomerSuggestions() {
                if (!customerSuggestions) {
                    return;
                }

                customerSuggestions.innerHTML = '';
                customerSuggestions.classList.add('hidden');
            }

            function selectCustomer(customer) {
                if (customerIdInput) {
                    customerIdInput.value = customer.id || '';
                }
                if (customerNameInput) {
                    customerNameInput.value = customer.full_name || '';
                }
                if (customerPhoneInput && customer.phone) {
                    customerPhoneInput.value = customer.phone;
                }
                renderSelectedCustomer(customer);
                hideCustomerSuggestions();
            }

            function clearSelectedCustomer() {
                if (customerIdInput) {
                    customerIdInput.value = '';
                }
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
                        const membership = customer.current_package || customer.membership_type || customer.membership_code || '';
                        button.innerHTML = `
                            <div class="customer-suggestion__name">
                                <span>${customer.full_name || 'Customer'}</span>
                                ${membershipPillHtml(membership)}
                            </div>
                            <div class="customer-suggestion__meta">${customer.phone || 'No phone'}${membership ? ` | ${membership}` : ''}</div>
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

            if (isCheckInMode) {
                viewDateBoardButton?.addEventListener('click', openCalendarBoardModal);
                calendarBoardModalClose?.addEventListener('click', closeCalendarBoardModal);
                document.querySelectorAll('[data-traffic-list]').forEach((button) => {
                    button.addEventListener('click', () => openTrafficListModal(button.dataset.trafficList));
                });
                document.querySelectorAll('.checkin-remark-action').forEach((button) => {
                    button.addEventListener('click', () => openCheckinRemarkModal(button));
                });
                [trafficListClose, trafficListCancel].forEach((button) => {
                    button?.addEventListener('click', closeTrafficListModal);
                });
                [checkinRemarkClose, checkinRemarkCancel].forEach((button) => {
                    button?.addEventListener('click', closeCheckinRemarkModal);
                });
                trafficListPrint?.addEventListener('click', function () {
                    document.body.classList.add('is-printing-traffic');
                    window.print();
                    window.setTimeout(() => document.body.classList.remove('is-printing-traffic'), 250);
                });
                const requestedTrafficList = new URLSearchParams(window.location.search).get('status');
                if (requestedTrafficList && trafficListMeta[requestedTrafficList]) {
                    window.setTimeout(() => openTrafficListModal(requestedTrafficList), 80);
                }

                calendarBoardModal?.addEventListener('click', function (event) {
                    if (event.target === calendarBoardModal || event.target === calendarBoardModal.firstElementChild) {
                        closeCalendarBoardModal();
                    }
                });
                trafficListModal?.addEventListener('click', function (event) {
                    if (event.target === trafficListModal || event.target === trafficListModal.firstElementChild) {
                        closeTrafficListModal();
                    }
                });
                checkinRemarkModal?.addEventListener('click', function (event) {
                    if (event.target === checkinRemarkModal || event.target === checkinRemarkModal.firstElementChild) {
                        closeCheckinRemarkModal();
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

                document.addEventListener('click', function (event) {
                    if (!event.target.closest('.customer-picker')) {
                        hideCustomerSuggestions();
                    }
                });

                dateInput?.addEventListener('change', function () {
                    if (calendarBoardModal && !calendarBoardModal.classList.contains('hidden') && calendarBoardFrame) {
                        calendarBoardFrame.src = buildCalendarBoardUrl();
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && calendarBoardModal && !calendarBoardModal.classList.contains('hidden')) {
                        closeCalendarBoardModal();
                    }
                    if (event.key === 'Escape' && trafficListModal && !trafficListModal.classList.contains('hidden')) {
                        closeTrafficListModal();
                    }
                    if (event.key === 'Escape' && checkinRemarkModal && !checkinRemarkModal.classList.contains('hidden')) {
                        closeCheckinRemarkModal();
                    }
                });

                if (oldCustomerId && customerNameInput?.value) {
                    renderSelectedCustomer({
                        id: oldCustomerId,
                        full_name: customerNameInput.value,
                        phone: customerPhoneInput?.value || '',
                        membership_code: '',
                        membership_type: '',
                        current_package: '',
                    });
                }

                return;
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
            breakModeToggle?.addEventListener('change', function () {
                breakModeEnabled = breakModeToggle.checked;
                document.body.classList.toggle('break-mode-active', breakModeEnabled);
                renderPlannerBoard();
            });

            viewDateBoardButton?.addEventListener('click', openCalendarBoardModal);

            modalBody.addEventListener('click', function (event) {
                const button = event.target.closest('.option-choice');
                if (!button) {
                    return;
                }

                const groupId = button.dataset.groupId;
                const valueId = button.dataset.valueId;
                const shouldClearSelection = pendingModalSelections[groupId] === valueId;

                if (shouldClearSelection) {
                    delete pendingModalSelections[groupId];
                } else {
                    pendingModalSelections[groupId] = valueId;
                }

                modalBody.querySelectorAll(`[data-group-id="${groupId}"]`).forEach((choice) => {
                    const isSelected = !shouldClearSelection && choice.dataset.valueId === valueId;
                    choice.classList.toggle('btn-primary', isSelected);
                    choice.classList.toggle('btn-secondary', !isSelected);
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

            [breakRemarkClose, breakRemarkCancel].forEach((button) => {
                button?.addEventListener('click', () => closeBreakRemarkModal(null));
            });

            breakRemarkSave?.addEventListener('click', function () {
                const reason = breakRemarkInput?.value.trim() || '';

                if (!reason) {
                    breakRemarkError?.classList.remove('hidden');
                    breakRemarkInput?.focus();
                    return;
                }

                closeBreakRemarkModal(reason);
            });

            breakRemarkInput?.addEventListener('input', function () {
                if (this.value.trim()) {
                    breakRemarkError?.classList.add('hidden');
                }
            });

            breakRemarkInput?.addEventListener('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                    breakRemarkSave?.click();
                }
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

            breakRemarkModal?.addEventListener('click', function (event) {
                if (event.target === breakRemarkModal || event.target === breakRemarkModal.firstElementChild) {
                    closeBreakRemarkModal(null);
                }
            });

            calendarBoardModalClose?.addEventListener('click', closeCalendarBoardModal);

            calendarBoardModal?.addEventListener('click', function (event) {
                if (event.target === calendarBoardModal || event.target === calendarBoardModal.firstElementChild) {
                    closeCalendarBoardModal();
                }
            });

            customerNameInput?.addEventListener('input', function () {
                const query = this.value.trim();
                clearSelectedCustomer();
                persistDraft();

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

                persistDraft();
            });

            notesInput?.addEventListener('input', persistDraft);
            dateInput?.addEventListener('input', persistDraft);
            dateInput?.addEventListener('change', function () {
                persistDraft();

                if (reloadBookingBoardForSelectedDate()) {
                    return;
                }

                if (calendarBoardModal && !calendarBoardModal.classList.contains('hidden') && calendarBoardFrame) {
                    calendarBoardFrame.src = buildCalendarBoardUrl();
                }
            });

            createAppointmentSubmit?.addEventListener('click', function () {
                if (!selectedServices.length) {
                    window.alert('Select at least one service before creating the appointment.');
                    return;
                }

                const unassigned = selectedServices.filter((service) => !assignments[service.instance_id]);

                if (unassigned.length) {
                    activeInstanceId = unassigned[0].instance_id;
                    renderSelectedServices();
                    renderPlannerBoard();
                    window.alert('Assign every selected service into a staff time box before submitting.');
                    return;
                }

                submitDateInput.value = dateInput?.value || '';
                submitCustomerIdInput.value = customerIdInput?.value || '';
                submitCustomerNameInput.value = customerNameInput?.value || '';
                submitCustomerPhoneInput.value = customerPhoneInput?.value || '';
                submitNotesInput.value = notesInput?.value || '';
                submitFollowupIdInput.value = rescheduleFollowupId;
                submitReturnToInput.value = returnToList;
                submitReturnDateInput.value = returnDate || dateInput?.value || '';
                bookingPayloadInput.value = JSON.stringify(buildPayloadForSubmit());
                isSubmittingAppointment = true;
                clearPersistedDraft();
                appointmentSubmitForm?.requestSubmit();
            });

            bookingForm.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') {
                    return;
                }

                const target = event.target;
                const tagName = target?.tagName?.toLowerCase?.() || '';
                const inputType = target?.getAttribute?.('type') || '';
                const shouldAllowEnter = (
                    tagName === 'textarea'
                    || inputType === 'submit'
                    || target?.id === 'service-option-confirm'
                    || target?.id === 'confirm-remove-approve'
                );

                if (!shouldAllowEnter) {
                    event.preventDefault();
                }
            });

            document.addEventListener('click', function (event) {
                if (!event.target.closest('.customer-picker')) {
                    hideCustomerSuggestions();
                }
            });

            window.addEventListener('beforeunload', function () {
                if (isSubmittingAppointment) {
                    clearPersistedDraft();
                    return;
                }

                persistDraft();
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeOptionModal();
                }

                if (event.key === 'Escape' && !confirmRemoveModal.classList.contains('hidden')) {
                    closeConfirmRemoveModal();
                }

                if (event.key === 'Escape' && calendarBoardModal && !calendarBoardModal.classList.contains('hidden')) {
                    closeCalendarBoardModal();
                }
            });

            if (oldCustomerId && customerNameInput?.value) {
                renderSelectedCustomer({
                    id: oldCustomerId,
                    full_name: customerNameInput.value,
                    phone: customerPhoneInput?.value || '',
                    membership_code: '',
                    membership_type: '',
                    current_package: '',
                });
            }

            if (prefillCustomer && customerNameInput && !customerNameInput.value) {
                selectCustomer(prefillCustomer);
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

            if (!selectedServices.length) {
                restorePersistedDraft();
            }

            renderCategoryTabs();
            renderServiceGrid();
            renderSelectedServices();
            renderPlannerBoard();
        });
    </script>
</x-internal-layout>
