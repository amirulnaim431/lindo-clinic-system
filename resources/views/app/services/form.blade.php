<x-internal-layout :title="$mode === 'create' ? 'Create Service' : 'Edit Service'" :subtitle="null">
    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ $mode === 'create' ? route('app.services.store') : route('app.services.update', $service) }}" class="stack">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <section class="panel">
                <div class="panel-header">
                    <h3 class="panel-title-display" style="font-size:24px;">Service Details</h3>
                </div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="col-6 field-block">
                            <label class="field-label" for="name">Service name</label>
                            <input id="name" name="name" type="text" class="form-input" value="{{ old('name', $service->name) }}" required>
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="category_key">Category</label>
                            <select id="category_key" name="category_key" class="form-select" required>
                                @foreach ($categoryOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category_key', $service->category_key) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-3 field-block" data-consultation-category-field>
                            <label class="field-label" for="consultation_category_key">Consultation department</label>
                            <select id="consultation_category_key" name="consultation_category_key" class="form-select">
                                <option value="">Choose department</option>
                                @foreach ($consultationCategoryOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('consultation_category_key', $service->consultation_category_key) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="display_order">Display order</label>
                            <input id="display_order" name="display_order" type="number" min="0" class="form-input" value="{{ old('display_order', $service->display_order ?? 0) }}">
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="default_staff_role">Default staff role</label>
                            <select id="default_staff_role" name="default_staff_role" class="form-select">
                                <option value="">No default role</option>
                                @foreach ($roleOptions as $key => $label)
                                    <option value="{{ $key }}" @selected(old('default_staff_role', $service->default_staff_role) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="price">Standard price</label>
                            <input id="price" name="price" type="number" min="0" class="form-input" value="{{ old('price', $service->price) }}">
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="promo_price">Promo price</label>
                            <input id="promo_price" name="promo_price" type="number" min="0" class="form-input" value="{{ old('promo_price', $service->promo_price) }}">
                        </div>

                        <div class="col-3 field-block">
                            <label class="field-label" for="is_promo">Promo service</label>
                            <select id="is_promo" name="is_promo" class="form-select">
                                <option value="0" @selected(! old('is_promo', $service->is_promo))>No</option>
                                <option value="1" @selected((bool) old('is_promo', $service->is_promo))>Yes</option>
                            </select>
                        </div>

                        <div class="col-12 field-block">
                            <label class="field-label" for="description">Service note</label>
                            <textarea id="description" name="description" class="form-input booking-textarea">{{ old('description', $service->description) }}</textarea>
                        </div>

                        <div class="col-12 field-block">
                            <label class="field-label">Reusable option groups</label>
                            <div class="selection-grid">
                                @foreach ($optionGroups as $group)
                                    @php
                                        $selected = in_array((string) $group->id, old('option_group_ids', $selectedOptionGroupIds ?? []), true);
                                        $requirement = old(
                                            'option_group_requirement.'.(string) $group->id,
                                            ($selectedOptionGroupRequirements[(string) $group->id] ?? false) ? 'required' : 'optional'
                                        );
                                    @endphp
                                    <label class="selection-card {{ $selected ? 'is-selected' : '' }}">
                                        <input type="checkbox" name="option_group_ids[]" value="{{ $group->id }}" class="selection-input service-option-checkbox" data-badge-off="{{ $group->values->count().' options' }}" {{ $selected ? 'checked' : '' }}>
                                        <div class="selection-card__head">
                                            <div>
                                                <div class="selection-card__title">{{ $group->name }}</div>
                                                <div class="selection-card__meta">{{ $group->values->pluck('label')->implode(' | ') }}</div>
                                            </div>
                                            <span class="selection-card__badge">{{ $selected ? ($requirement === 'required' ? 'Required' : 'Optional') : $group->values->count().' options' }}</span>
                                        </div>
                                        <div class="option-requirement-toggle {{ $selected ? '' : 'hidden' }}">
                                            <span class="micro-label">Booking requirement</span>
                                            <select name="option_group_requirement[{{ $group->id }}]" class="form-select service-option-requirement">
                                                <option value="optional" @selected($requirement !== 'required')>Optional</option>
                                                <option value="required" @selected($requirement === 'required')>Required</option>
                                            </select>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12 field-block">
                            <label class="field-label">Eligible staff</label>
                            <div class="small-note" style="margin-bottom:0.75rem;">This uses the same service assignment list shown in Staff Directory. Updating either side will tally after saving.</div>
                            <div class="selection-grid">
                                @foreach ($staffOptions as $staff)
                                    @php
                                        $selected = in_array((string) $staff->id, old('staff_ids', $selectedStaffIds ?? []), true);
                                    @endphp
                                    <label class="selection-card {{ $selected ? 'is-selected' : '' }}">
                                        <input type="checkbox" name="staff_ids[]" value="{{ $staff->id }}" class="selection-input service-staff-checkbox" data-badge-off="Not assigned" {{ $selected ? 'checked' : '' }}>
                                        <div class="selection-card__head">
                                            <div>
                                                <div class="selection-card__title">{{ $staff->full_name }}</div>
                                                <div class="selection-card__meta">{{ $staff->role_key }}</div>
                                            </div>
                                            <span class="selection-card__badge">{{ $selected ? 'Assigned' : 'Not assigned' }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $service->is_active ?? true) ? 'checked' : '' }}>
                                <span>Active service</span>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <div class="btn-row">
                <button type="submit" class="btn btn-primary">{{ $mode === 'create' ? 'Create Service' : 'Save Changes' }}</button>
                <a href="{{ route('app.services.index') }}" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const categoryInput = document.getElementById('category_key');
            const consultationField = document.querySelector('[data-consultation-category-field]');
            const consultationSelect = document.getElementById('consultation_category_key');

            const syncConsultationField = function () {
                const isConsultation = categoryInput?.value === 'consultations';

                consultationField?.classList.toggle('hidden', !isConsultation);

                if (!isConsultation && consultationSelect) {
                    consultationSelect.value = '';
                }
            };

            document.querySelectorAll('.service-option-checkbox, .service-staff-checkbox').forEach(function (checkbox) {
                const syncCard = function () {
                    const card = checkbox.closest('.selection-card');
                    const badge = card ? card.querySelector('.selection-card__badge') : null;
                    const requirementWrap = card ? card.querySelector('.option-requirement-toggle') : null;
                    const requirementSelect = card ? card.querySelector('.service-option-requirement') : null;

                    card?.classList.toggle('is-selected', checkbox.checked);
                    requirementWrap?.classList.toggle('hidden', !checkbox.checked);

                    if (badge) {
                        if (checkbox.checked && requirementSelect) {
                            badge.textContent = requirementSelect.value === 'required' ? 'Required' : 'Optional';
                        } else {
                            badge.textContent = checkbox.checked ? 'Assigned' : (checkbox.dataset.badgeOff || 'Not assigned');
                        }
                    }
                };

                syncCard();
                checkbox.addEventListener('change', syncCard);
                checkbox.closest('.selection-card')?.querySelector('.service-option-requirement')?.addEventListener('change', syncCard);
            });

            syncConsultationField();
            categoryInput?.addEventListener('change', syncConsultationField);
        });
    </script>
</x-internal-layout>
