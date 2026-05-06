<x-internal-layout :title="'Edit Appointment'" :subtitle="'Adjust appointment timing and front desk remarks without changing services or staff.'">
    @php
        $appointmentDate = old('date', $appointmentGroup->starts_at?->format('Y-m-d'));
        $appointmentTime = old('start_time', $appointmentGroup->starts_at?->format('H:i'));
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
                        subtitle="Only the appointment date, time, and remark can be edited here."
                    />
                    <div class="small-note mt-2">Only the appointment date, time, and remark can be edited here.</div>
                    <a href="{{ route('app.calendar', ['date' => $appointmentGroup->starts_at?->format('Y-m-d') ?? now()->format('Y-m-d')]) }}" class="btn btn-secondary">Back to calendar</a>
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
                <x-section-heading kicker="When and remark" title="Update appointment" subtitle="Services and assigned PIC stay unchanged." />
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

                <div class="stack">
                    <div class="filter-bar__head">
                        <div class="selection-card__title">Read-only services</div>
                        <span class="chip">{{ $appointmentGroup->items->count() }}</span>
                    </div>

                    @forelse ($appointmentGroup->items as $item)
                        <div class="summary-card">
                            <div class="filter-bar__head" style="align-items:flex-start;">
                                <div>
                                    <div class="micro-label">{{ $item->displayCategoryLabel() }}</div>
                                    <div class="selection-card__title mt-2">{{ $item->displayServiceName() }}</div>
                                </div>
                                <span class="chip">{{ $item->displayStaffName() }}</span>
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

        @media (max-width: 720px) {
            .appointment-edit-time-grid {
                grid-template-columns: 1fr;
                max-width: none;
            }
        }
    </style>
</x-internal-layout>
