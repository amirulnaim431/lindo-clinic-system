<x-internal-layout :title="'Edit Appointment'" :subtitle="'Adjust appointment timing, services, and front desk remarks safely.'">
    @php
        $appointmentDate = old('date', $appointmentGroup->starts_at?->format('Y-m-d'));
        $appointmentTime = old('start_time', $appointmentGroup->starts_at?->format('H:i'));
        $editableServicePayload = $editableServices
            ->mapWithKeys(fn ($service) => [
                (string) $service->id => [
                    'id' => (string) $service->id,
                    'name' => $service->name,
                    'category' => $service->category_label,
                    'staff' => $service->staff
                        ->map(fn ($staff) => [
                            'id' => (string) $staff->id,
                            'name' => $staff->full_name,
                            'role' => $staff->role_key,
                        ])
                        ->values()
                        ->all(),
                    'option_groups' => $service->optionGroups
                        ->map(fn ($group) => [
                            'id' => (string) $group->id,
                            'name' => $group->name,
                            'required' => (bool) ($group->pivot?->is_required ?? true),
                            'values' => $group->values
                                ->map(fn ($value) => [
                                    'id' => (string) $value->id,
                                    'label' => $value->label,
                                ])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                ],
            ])
            ->all();
    @endphp

    <div class="stack">
        @if (session('success'))
            <div class="flash flash--success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="flash flash--error">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="panel">
            <div class="panel-header">
                <div class="filter-bar__head">
                    <x-section-heading
                        kicker="Appointment edit"
                        title="{{ $appointmentGroup->customer?->full_name ?: 'Customer' }}"
                        subtitle="Update the date, time, remark, or service list. Saving will re-check staff eligibility and half-slot availability."
                    />
                    <div class="small-note mt-2">Saving will not allow duplicate staff boxes or an unavailable Medex machine slot.</div>
                    <div class="btn-row btn-row--end">
                        <a href="{{ route('app.calendar', ['date' => $appointmentGroup->starts_at?->format('Y-m-d') ?? now()->format('Y-m-d')]) }}" class="btn btn-secondary">Back to calendar</a>
                        <form method="POST" action="{{ route('app.appointments.destroy', $appointmentGroup) }}" onsubmit="return confirm('Delete this appointment from the active calendar board? This will free the staff time box.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary appointment-delete-button">Delete appointment</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="panel-body">
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="micro-label">Customer phone</div>
                        <div class="selection-card__title mt-2">{{ $appointmentGroup->customer?->phone ?: '-' }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="micro-label">Status</div>
                        <div class="selection-card__title mt-2">{{ $statusLabel }}</div>
                    </div>
                    <div class="summary-card">
                        <div class="micro-label">Current window</div>
                        <div class="selection-card__title mt-2">
                            {{ $appointmentGroup->starts_at?->format('d M Y, h:i A') ?: '-' }}
                            @if ($appointmentGroup->ends_at)
                                - {{ $appointmentGroup->ends_at->format('h:i A') }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('app.appointments.timing.update', $appointmentGroup) }}" class="panel">
            @csrf
            @method('PATCH')

            <div class="panel-header">
                <x-section-heading kicker="When, services, and remark" title="Update appointment" subtitle="Keep, remove, or add services while preserving safe slot checks." />
            </div>
            <div class="panel-body stack">
                <div class="appointment-edit-time-grid">
                    <div class="field-block appointment-edit-field">
                        <label class="field-label" for="date">Appointment date</label>
                        <input id="date" name="date" type="date" value="{{ $appointmentDate }}" class="form-input" required>
                    </div>

                    <div class="field-block appointment-edit-field">
                        <label class="field-label" for="start_time">Start time</label>
                        <input id="start_time" name="start_time" type="time" value="{{ $appointmentTime }}" class="form-input" required>
                        <div class="small-note mt-2">The existing treatment duration and spacing will be preserved.</div>
                    </div>
                </div>

                <div class="field-block">
                    <label class="field-label" for="notes">Remark</label>
                    <textarea id="notes" name="notes" rows="5" class="form-textarea" placeholder="Front desk remark or appointment note">{{ old('notes', $appointmentGroup->notes) }}</textarea>
                </div>

                <div class="stack appointment-service-editor">
                    <div class="filter-bar__head">
                        <div>
                            <div class="micro-label">Current services</div>
                            <div class="selection-card__title mt-2">Keep or remove existing treatments</div>
                        </div>
                        <span class="chip">{{ $appointmentGroup->items->count() }}</span>
                    </div>

                    @forelse ($appointmentGroup->items as $item)
                        <div class="summary-card appointment-service-editor__card">
                            <input type="hidden" name="existing_items[{{ $item->id }}][keep]" value="0">
                            <div class="filter-bar__head" style="align-items:flex-start;">
                                <div>
                                    <div class="micro-label">{{ $item->displayCategoryLabel() }}</div>
                                    <div class="selection-card__title mt-2">{{ $item->displayServiceName() }}</div>
                                    <div class="small-note mt-2">
                                        {{ $item->displayStaffName() }}
                                        @if ($item->slotReservation)
                                            · Box {{ $item->slotReservation->slot_index }}
                                        @endif
                                    </div>
                                </div>
                                <label class="appointment-keep-toggle">
                                    <input type="checkbox" name="existing_items[{{ $item->id }}][keep]" value="1" checked>
                                    <span>Keep service</span>
                                </label>
                            </div>
                            @if ($item->optionSelections->isNotEmpty())
                                <div class="inline-chip-row mt-3">
                                    @foreach ($item->optionSelections as $selection)
                                        <span class="status-chip status-chip--info">{{ $selection->option_group_name }}: {{ $selection->option_value_label }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state empty-state--dashed">
                            <div class="empty-state__title">No service details available</div>
                        </div>
                    @endforelse
                </div>

                <div class="stack appointment-service-editor">
                    <div>
                        <div class="micro-label">Add services</div>
                        <div class="selection-card__title mt-2">Add another treatment into this appointment window</div>
                        <div class="small-note mt-2">Choose the service, eligible staff, and exact box. Optional service options can be left blank.</div>
                    </div>

                    @for ($index = 0; $index < 3; $index++)
                        <div class="summary-card appointment-new-service-row" data-new-service-row="{{ $index }}">
                            <div class="appointment-new-service-grid">
                                <div class="field-block">
                                    <label class="field-label" for="new-service-search-{{ $index }}">Service</label>
                                    <input id="new-service-search-{{ $index }}" type="search" class="form-input appointment-service-search" data-row="{{ $index }}" placeholder="Search service name or category" autocomplete="off">
                                    <div class="appointment-service-suggestions" data-service-suggestions="{{ $index }}" hidden></div>
                                    <select id="new-service-{{ $index }}" name="new_items[{{ $index }}][service_id]" class="form-input appointment-service-select appointment-service-native" data-row="{{ $index }}">
                                        <option value="">No added service</option>
                                        @foreach ($editableServices as $service)
                                            <option value="{{ $service->id }}">{{ $service->category_label }} · {{ $service->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="field-block">
                                    <label class="field-label" for="new-staff-{{ $index }}">Eligible staff</label>
                                    <select id="new-staff-{{ $index }}" name="new_items[{{ $index }}][staff_id]" class="form-input appointment-staff-select" data-row="{{ $index }}">
                                        <option value="">Choose service first</option>
                                    </select>
                                </div>

                                <div class="field-block">
                                    <label class="field-label">Timeslot</label>
                                    <button type="button" class="btn btn-secondary appointment-slot-button" data-row="{{ $index }}">Select timeslot</button>
                                    <div class="small-note mt-2" data-slot-label="{{ $index }}">No slot selected</div>
                                    <label class="field-label appointment-native-slot-label" for="new-box-{{ $index }}">Box</label>
                                    <select id="new-box-{{ $index }}" name="new_items[{{ $index }}][slot_index]" class="form-input">
                                        @for ($slotIndex = 1; $slotIndex <= $slotCapacity; $slotIndex++)
                                            <option value="{{ $slotIndex }}">Box {{ $slotIndex }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>

                            <div class="appointment-option-fields mt-4" data-option-fields="{{ $index }}"></div>
                        </div>
                    @endfor
                </div>

                <div class="btn-row">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('app.calendar', ['date' => $appointmentGroup->starts_at?->format('Y-m-d') ?? now()->format('Y-m-d')]) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </div>
        </form>
    </div>

    <div id="edit-slot-modal" class="modal-shell hidden" aria-hidden="true">
        <div class="modal-backdrop"></div>
        <div class="modal-stage modal-stage--wide edit-slot-modal-stage" role="dialog" aria-modal="true" aria-labelledby="edit-slot-title">
            <div class="modal-card">
                <div class="modal-head">
                    <div>
                        <div class="modal-kicker">Staff availability</div>
                        <h3 class="modal-title" id="edit-slot-title">Select timeslot</h3>
                        <p class="modal-subtitle" id="edit-slot-subtitle">Pick an available box for the selected staff.</p>
                    </div>
                    <button type="button" class="modal-close" id="edit-slot-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body stack">
                    <div class="edit-slot-toolbar">
                        <div class="field-block">
                            <label class="field-label" for="edit-slot-date">View date</label>
                            <input id="edit-slot-date" type="date" class="form-input" value="{{ $appointmentDate }}">
                        </div>
                        <button type="button" class="btn btn-primary" id="edit-slot-load">Apply</button>
                    </div>
                    <div id="edit-slot-board" class="edit-slot-board"></div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .appointment-edit-time-grid {
            display: grid;
            grid-template-columns: minmax(240px, 340px) minmax(240px, 340px);
            gap: 1.5rem;
            align-items: start;
            max-width: 760px;
        }

        .appointment-edit-field {
            min-width: 0;
        }

        .appointment-edit-field .form-input {
            width: 100%;
            min-height: 58px;
        }

        .appointment-delete-button {
            border-color: rgba(181, 65, 86, 0.28);
            color: #8f2f43;
        }

        .appointment-delete-button:hover {
            background: rgba(181, 65, 86, 0.08);
            border-color: rgba(181, 65, 86, 0.42);
            color: #722234;
        }

        .appointment-service-editor {
            border-top: 1px solid rgba(99, 46, 62, 0.08);
            padding-top: 1.25rem;
        }

        .appointment-service-editor__card {
            transition: opacity 0.2s ease, border-color 0.2s ease, background 0.2s ease;
        }

        .appointment-service-editor__card:has(.appointment-keep-toggle input:not(:checked)) {
            opacity: 0.62;
            border-color: rgba(181, 65, 86, 0.34);
            background: rgba(181, 65, 86, 0.04);
        }

        .appointment-keep-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid rgba(99, 46, 62, 0.12);
            border-radius: 999px;
            padding: 0.6rem 0.9rem;
            font-weight: 800;
            color: #632e3e;
            cursor: pointer;
            white-space: nowrap;
        }

        .appointment-keep-toggle input {
            accent-color: #c77f99;
        }

        .appointment-new-service-grid {
            display: grid;
            grid-template-columns: minmax(320px, 1.1fr) minmax(240px, 1fr) minmax(180px, 220px);
            gap: 1rem;
            align-items: end;
        }

        .appointment-option-fields {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .appointment-service-native,
        .appointment-native-slot-label,
        .appointment-new-service-row select[name$="[slot_index]"] {
            display: none;
        }

        .appointment-service-suggestions {
            position: relative;
            z-index: 20;
            margin-top: 0.5rem;
            max-height: 260px;
            overflow-y: auto;
            border: 1px solid rgba(99, 46, 62, 0.12);
            border-radius: 22px;
            background: #fff;
            box-shadow: 0 18px 45px rgba(92, 58, 69, 0.14);
        }

        .appointment-service-suggestion {
            display: block;
            width: 100%;
            border: 0;
            border-bottom: 1px solid rgba(99, 46, 62, 0.07);
            padding: 0.85rem 1rem;
            background: transparent;
            color: #2f1d26;
            text-align: left;
            cursor: pointer;
        }

        .appointment-service-suggestion:hover {
            background: #fff7fa;
        }

        .appointment-service-suggestion strong {
            display: block;
        }

        .appointment-slot-button {
            width: 100%;
            min-height: 58px;
            justify-content: center;
        }

        .edit-slot-modal-stage {
            width: min(1120px, calc(100vw - 32px));
            max-height: calc(100vh - 40px);
        }

        .edit-slot-toolbar {
            display: flex;
            align-items: end;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .edit-slot-board {
            max-height: min(68vh, 760px);
            overflow: auto;
            padding-right: 0.25rem;
        }

        .edit-slot-row {
            display: grid;
            grid-template-columns: 170px repeat(2, minmax(0, 1fr));
            border-top: 1px solid rgba(99, 46, 62, 0.08);
        }

        .edit-slot-time {
            display: flex;
            align-items: center;
            padding: 1rem;
            font-weight: 900;
        }

        .edit-slot-box {
            margin: 0.75rem;
            min-height: 78px;
            border: 1px dashed rgba(99, 46, 62, 0.16);
            border-radius: 20px;
            background: #fffbfd;
            color: #2f1d26;
            text-align: left;
            padding: 0.95rem 1rem;
        }

        .edit-slot-box.is-available {
            cursor: pointer;
        }

        .edit-slot-box.is-available:hover {
            border-color: rgba(198, 124, 154, 0.62);
            background: #fff7fa;
        }

        .edit-slot-box.is-unavailable {
            border-style: solid;
            background: #f4eef1;
            color: #8a6975;
            cursor: not-allowed;
        }

        .edit-slot-box__time {
            color: #bf7893;
            font-size: 0.78rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        @media (max-width: 720px) {
            .appointment-edit-time-grid {
                grid-template-columns: 1fr;
                max-width: none;
            }

            .appointment-new-service-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        (() => {
            const services = @json($editableServicePayload);
            const serviceList = Object.values(services);
            const availabilityUrl = @json(route('app.appointments.availability-board'));
            const dateInput = document.getElementById('date');
            const startTimeInput = document.getElementById('start_time');
            const slotModal = document.getElementById('edit-slot-modal');
            const slotClose = document.getElementById('edit-slot-close');
            const slotDate = document.getElementById('edit-slot-date');
            const slotLoad = document.getElementById('edit-slot-load');
            const slotBoard = document.getElementById('edit-slot-board');
            const slotTitle = document.getElementById('edit-slot-title');
            const slotSubtitle = document.getElementById('edit-slot-subtitle');
            let activeSlotRow = null;
            let activeAvailability = null;

            const escapeHtml = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const renderStaffOptions = (row, service) => {
                const staffSelect = document.querySelector(`.appointment-staff-select[data-row="${row}"]`);

                if (!staffSelect) {
                    return;
                }

                if (!service) {
                    staffSelect.innerHTML = '<option value="">Choose service first</option>';
                    return;
                }

                staffSelect.innerHTML = [
                    '<option value="">Choose eligible staff</option>',
                    ...(service.staff || []).map((staff) => `<option value="${escapeHtml(staff.id)}">${escapeHtml(staff.name)}${staff.role ? ` (${escapeHtml(staff.role)})` : ''}</option>`),
                ].join('');
            };

            const boxTimeLabel = (slotTime, slotIndex) => {
                const [hour, minute] = String(slotTime || '00:00').split(':').map((value) => Number(value || 0));
                const date = new Date();
                date.setHours(hour, minute + ((Number(slotIndex) - 1) * 30), 0, 0);

                return date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
            };

            const renderServiceSuggestions = (row, query) => {
                const target = document.querySelector(`[data-service-suggestions="${row}"]`);

                if (!target) {
                    return;
                }

                const term = String(query || '').trim().toLowerCase();

                if (term.length < 2) {
                    target.hidden = true;
                    target.innerHTML = '';
                    return;
                }

                const matches = serviceList
                    .filter((service) => `${service.name} ${service.category}`.toLowerCase().includes(term))
                    .slice(0, 18);

                if (!matches.length) {
                    target.hidden = false;
                    target.innerHTML = '<div class="small-note" style="padding:1rem;">No matching services found.</div>';
                    return;
                }

                target.hidden = false;
                target.innerHTML = matches.map((service) => `
                    <button type="button" class="appointment-service-suggestion" data-service-choice="${escapeHtml(service.id)}" data-row="${escapeHtml(row)}">
                        <strong>${escapeHtml(service.name)}</strong>
                        <span>${escapeHtml(service.category || 'Service')}</span>
                    </button>
                `).join('');
            };

            const selectService = (row, serviceId) => {
                const service = services[serviceId] || null;
                const nativeSelect = document.querySelector(`.appointment-service-select[data-row="${row}"]`);
                const searchInput = document.querySelector(`.appointment-service-search[data-row="${row}"]`);
                const suggestions = document.querySelector(`[data-service-suggestions="${row}"]`);

                if (nativeSelect) {
                    nativeSelect.value = serviceId || '';
                }

                if (searchInput) {
                    searchInput.value = service ? `${service.name} - ${service.category}` : '';
                }

                if (suggestions) {
                    suggestions.hidden = true;
                    suggestions.innerHTML = '';
                }

                renderStaffOptions(row, service);
                renderOptions(row, service);
            };

            const renderSlotBoard = (row) => {
                const staffSelect = document.querySelector(`.appointment-staff-select[data-row="${row}"]`);
                const staffId = staffSelect?.value || '';
                const staffName = staffSelect?.selectedOptions?.[0]?.textContent || 'Selected staff';

                if (!slotBoard || !activeAvailability) {
                    return;
                }

                if (!staffId) {
                    slotBoard.innerHTML = '<div class="empty-state empty-state--dashed"><div class="empty-state__title">Choose eligible staff first</div></div>';
                    return;
                }

                const staff = (activeAvailability.staff || []).find((candidate) => candidate.id === staffId);
                const occupancy = activeAvailability.occupancy?.[staffId] || {};
                const capacity = Number(activeAvailability.capacity_per_slot || 2);
                const slots = activeAvailability.slots || [];

                slotTitle.textContent = staffName;
                slotSubtitle.textContent = `Choose an available box on ${slotDate.value}.`;

                if (!staff || !slots.length) {
                    slotBoard.innerHTML = '<div class="empty-state empty-state--dashed"><div class="empty-state__title">No availability found</div></div>';
                    return;
                }

                slotBoard.innerHTML = slots.map((slot) => {
                    const slotOccupancy = occupancy?.[slot.time] || {};
                    const appointments = Array.isArray(slotOccupancy.appointments) ? slotOccupancy.appointments : [];
                    const blocks = Array.isArray(slotOccupancy.blocks) ? slotOccupancy.blocks : [];
                    const boxes = [];

                    for (let slotIndex = 1; slotIndex <= capacity; slotIndex++) {
                        const appointment = appointments.find((item) => Number(item.slot_index || 1) === slotIndex);
                        const block = blocks.find((item) => Number(item.slot_index || 1) === slotIndex);
                        const label = boxTimeLabel(slot.time, slotIndex);

                        if (appointment) {
                            boxes.push(`<button type="button" class="edit-slot-box is-unavailable" disabled><div class="edit-slot-box__time">${escapeHtml(label)}</div><strong>${escapeHtml(appointment.customer_name)}</strong><div>${escapeHtml(appointment.service_name)}</div></button>`);
                        } else if (block) {
                            boxes.push(`<button type="button" class="edit-slot-box is-unavailable" disabled><div class="edit-slot-box__time">${escapeHtml(label)}</div><strong>Blocked</strong><div>${escapeHtml(block.reason || 'Break')}</div></button>`);
                        } else {
                            boxes.push(`<button type="button" class="edit-slot-box is-available" data-slot-time="${escapeHtml(slot.time)}" data-slot-index="${slotIndex}" data-slot-label="${escapeHtml(label)}"><div class="edit-slot-box__time">${escapeHtml(label)}</div><strong>Available</strong><div>Click to use this box</div></button>`);
                        }
                    }

                    return `<div class="edit-slot-row"><div class="edit-slot-time">${escapeHtml(slot.label)}</div>${boxes.join('')}</div>`;
                }).join('');
            };

            const loadAvailability = async () => {
                if (!slotDate?.value) {
                    return;
                }

                slotBoard.innerHTML = '<div class="small-note">Loading staff availability...</div>';
                const url = new URL(availabilityUrl);
                url.searchParams.set('date', slotDate.value);
                const response = await fetch(url.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                activeAvailability = await response.json();
                renderSlotBoard(activeSlotRow);
            };

            const openSlotModal = (row) => {
                activeSlotRow = row;
                slotDate.value = dateInput?.value || slotDate.value;
                slotModal?.classList.remove('hidden');
                slotModal?.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                loadAvailability().catch(() => {
                    slotBoard.innerHTML = '<div class="flash flash--error">Could not load availability. Please try again.</div>';
                });
            };

            const closeSlotModal = () => {
                slotModal?.classList.add('hidden');
                slotModal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
            };

            const renderOptions = (row, service) => {
                const target = document.querySelector(`[data-option-fields="${row}"]`);

                if (!target) {
                    return;
                }

                if (!service || !service.option_groups?.length) {
                    target.innerHTML = '';
                    return;
                }

                target.innerHTML = service.option_groups.map((group) => `
                    <div class="field-block">
                        <label class="field-label" for="new-option-${row}-${escapeHtml(group.id)}">
                            ${escapeHtml(group.name)}${group.required ? '' : ' (optional)'}
                        </label>
                        <select id="new-option-${row}-${escapeHtml(group.id)}" name="new_options[${row}][${escapeHtml(group.id)}]" class="form-input">
                            <option value="">${group.required ? 'Choose option' : 'Skip option'}</option>
                            ${(group.values || []).map((option) => `<option value="${escapeHtml(option.id)}">${escapeHtml(option.label)}</option>`).join('')}
                        </select>
                    </div>
                `).join('');
            };

            document.querySelectorAll('.appointment-service-search').forEach((input) => {
                input.addEventListener('input', () => {
                    const nativeSelect = document.querySelector(`.appointment-service-select[data-row="${input.dataset.row}"]`);
                    if (nativeSelect) {
                        nativeSelect.value = '';
                    }
                    renderStaffOptions(input.dataset.row, null);
                    renderOptions(input.dataset.row, null);
                    renderServiceSuggestions(input.dataset.row, input.value);
                });
                input.addEventListener('focus', () => renderServiceSuggestions(input.dataset.row, input.value));
            });

            document.addEventListener('click', (event) => {
                const choice = event.target.closest('[data-service-choice]');

                if (choice) {
                    selectService(choice.dataset.row, choice.dataset.serviceChoice);
                    return;
                }

                if (!event.target.closest('.appointment-service-suggestions') && !event.target.closest('.appointment-service-search')) {
                    document.querySelectorAll('.appointment-service-suggestions').forEach((target) => target.hidden = true);
                }
            });

            document.querySelectorAll('.appointment-service-select').forEach((select) => {
                select.addEventListener('change', () => {
                    const row = select.dataset.row;
                    const service = services[select.value] || null;

                    renderStaffOptions(row, service);
                    renderOptions(row, service);
                });
            });

            document.querySelectorAll('.appointment-slot-button').forEach((button) => {
                button.addEventListener('click', () => openSlotModal(button.dataset.row));
            });

            slotLoad?.addEventListener('click', () => loadAvailability());
            slotClose?.addEventListener('click', closeSlotModal);
            slotModal?.addEventListener('click', (event) => {
                if (event.target === slotModal || event.target === slotModal.firstElementChild) {
                    closeSlotModal();
                }
            });
            slotBoard?.addEventListener('click', (event) => {
                const box = event.target.closest('[data-slot-time]');

                if (!box || activeSlotRow === null) {
                    return;
                }

                const slotIndexInput = document.querySelector(`.appointment-slot-index[data-row="${activeSlotRow}"]`);
                const nativeSlotSelect = document.querySelector(`select[name="new_items[${activeSlotRow}][slot_index]"]`);
                const label = document.querySelector(`[data-slot-label="${activeSlotRow}"]`);
                const button = document.querySelector(`.appointment-slot-button[data-row="${activeSlotRow}"]`);

                if (dateInput) {
                    dateInput.value = slotDate.value;
                }

                if (startTimeInput) {
                    startTimeInput.value = box.dataset.slotTime;
                }

                if (slotIndexInput) {
                    slotIndexInput.value = box.dataset.slotIndex;
                }

                if (nativeSlotSelect) {
                    nativeSlotSelect.value = box.dataset.slotIndex;
                }

                if (label) {
                    label.textContent = `${box.dataset.slotLabel} on ${slotDate.value}`;
                }

                if (button) {
                    button.textContent = `Selected ${box.dataset.slotLabel}`;
                }

                closeSlotModal();
            });
        })();
    </script>
</x-internal-layout>
