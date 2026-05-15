<x-internal-layout :title="'Edit Appointment'" :subtitle="'Adjust appointment timing, services, and front desk remarks safely.'">
    @php
        $appointmentDate = old('date', $appointmentGroup->starts_at?->format('Y-m-d'));
        $appointmentTime = old('start_time', $appointmentGroup->starts_at?->format('H:i'));
        $editableServicePayload = $editableServices
            ->mapWithKeys(fn ($service) => [
                (string) $service->id => [
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
                                    <label class="field-label" for="new-service-{{ $index }}">Service</label>
                                    <select id="new-service-{{ $index }}" name="new_items[{{ $index }}][service_id]" class="form-input appointment-service-select" data-row="{{ $index }}">
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
                                    <label class="field-label" for="new-box-{{ $index }}">Box</label>
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
            grid-template-columns: minmax(260px, 1fr) minmax(240px, 1fr) minmax(120px, 160px);
            gap: 1rem;
            align-items: end;
        }

        .appointment-option-fields {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
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

            document.querySelectorAll('.appointment-service-select').forEach((select) => {
                select.addEventListener('change', () => {
                    const row = select.dataset.row;
                    const service = services[select.value] || null;

                    renderStaffOptions(row, service);
                    renderOptions(row, service);
                });
            });
        })();
    </script>
</x-internal-layout>
