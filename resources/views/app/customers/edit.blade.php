<x-internal-layout :title="'Edit Customer'" :subtitle="'Update customer master data while keeping appointment and treatment history read-only.'">
    <div class="stack">
        <section class="hero-panel">
            <div class="panel-body">
                <div class="filter-bar__head">
                    <x-section-heading
                        kicker="Customer maintenance"
                        :title="$customer->full_name ?: 'Unnamed Customer'"
                        subtitle="Edit customer profile, clinic information, membership, and administrative details." />

                    <div class="page-actions">
                        <a href="{{ route('app.customers.show', $customer) }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </section>

        @if ($errors->any())
            <div class="alert alert-error">
                <div>Please correct the highlighted fields.</div>
                <ul style="margin: 0.6rem 0 0 1rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('app.customers.update', $customer) }}" class="stack">
            @csrf
            @method('PUT')

            <div class="dashboard-grid">
                <div class="stack">
                    <div class="panel">
                        <div class="panel-header">
                            <x-section-heading kicker="Profile" title="Core identity" subtitle="Customer identity and contact information." />
                        </div>
                        <div class="panel-body">
                            <div class="form-grid">
                                <div class="col-12 field-block"><label for="full_name" class="field-label">Full name</label><input id="full_name" name="full_name" type="text" value="{{ old('full_name', $customer->full_name) }}" class="form-input" required></div>
                                <div class="col-6 field-block"><label for="phone" class="field-label">Phone</label><input id="phone" name="phone" type="text" value="{{ old('phone', $customer->phone) }}" class="form-input"></div>
                                <div class="col-6 field-block"><label for="email" class="field-label">Email</label><input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}" class="form-input"></div>
                                <div class="col-4 field-block"><label for="dob" class="field-label">Date of birth</label><input id="dob" name="dob" type="date" value="{{ old('dob', $customer->dob?->format('Y-m-d')) }}" class="form-input"></div>
                                <div class="col-4 field-block"><label for="ic_passport" class="field-label">IC / Passport</label><input id="ic_passport" name="ic_passport" type="text" value="{{ old('ic_passport', $customer->ic_passport) }}" class="form-input"></div>
                                <div class="col-4 field-block"><label for="gender" class="field-label">Gender</label><input id="gender" name="gender" type="text" value="{{ old('gender', $customer->gender) }}" class="form-input"></div>
                                <div class="col-4 field-block"><label for="marital_status" class="field-label">Marital status</label><input id="marital_status" name="marital_status" type="text" value="{{ old('marital_status', $customer->marital_status) }}" class="form-input"></div>
                                <div class="col-4 field-block"><label for="nationality" class="field-label">Nationality</label><input id="nationality" name="nationality" type="text" value="{{ old('nationality', $customer->nationality) }}" class="form-input"></div>
                                <div class="col-4 field-block"><label for="occupation" class="field-label">Occupation</label><input id="occupation" name="occupation" type="text" value="{{ old('occupation', $customer->occupation) }}" class="form-input"></div>
                                <div class="col-12 field-block"><label for="address" class="field-label">Address</label><textarea id="address" name="address" rows="4" class="form-textarea">{{ old('address', $customer->address) }}</textarea></div>
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <x-section-heading kicker="Clinic info" title="Medical / clinic data" subtitle="Editable customer clinic information stored in CRM." />
                        </div>
                        <div class="panel-body">
                            <div class="form-grid">
                                <div class="col-6 field-block"><label for="weight" class="field-label">Weight (kg)</label><input id="weight" name="weight" type="number" step="0.01" min="0" value="{{ old('weight', $customer->weight) }}" class="form-input"></div>
                                <div class="col-6 field-block"><label for="height" class="field-label">Height (cm)</label><input id="height" name="height" type="number" step="0.01" min="0" value="{{ old('height', $customer->height) }}" class="form-input"></div>
                                <div class="col-12 field-block"><label for="allergies" class="field-label">Allergies</label><textarea id="allergies" name="allergies" rows="4" class="form-textarea">{{ old('allergies', $customer->allergies) }}</textarea></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stack">
                    <div class="panel">
                        <div class="panel-header"><div class="panel-title">Emergency contact</div></div>
                        <div class="panel-body stack">
                            <div class="field-block"><label for="emergency_contact_name" class="field-label">Name</label><input id="emergency_contact_name" name="emergency_contact_name" type="text" value="{{ old('emergency_contact_name', $customer->emergency_contact_name) }}" class="form-input"></div>
                            <div class="field-block"><label for="emergency_contact_phone" class="field-label">Phone</label><input id="emergency_contact_phone" name="emergency_contact_phone" type="text" value="{{ old('emergency_contact_phone', $customer->emergency_contact_phone) }}" class="form-input"></div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header"><div class="panel-title">Membership</div></div>
                        <div class="panel-body stack">
                            <div class="field-block"><label for="membership_code" class="field-label">Membership code</label><input id="membership_code" name="membership_code" type="text" value="{{ old('membership_code', $customer->membership_code) }}" class="form-input"></div>
                            <div class="field-block"><label for="membership_type" class="field-label">Membership type</label><input id="membership_type" name="membership_type" type="text" value="{{ old('membership_type', $customer->membership_type) }}" class="form-input"></div>
                            <div class="field-block"><label for="current_package" class="field-label">Current package</label><input id="current_package" name="current_package" type="text" value="{{ old('current_package', $customer->current_package) }}" class="form-input"></div>
                            <div class="field-block"><label for="current_package_since" class="field-label">Current package since</label><input id="current_package_since" name="current_package_since" type="date" value="{{ old('current_package_since', $customer->current_package_since?->format('Y-m-d')) }}" class="form-input"></div>
                            <div class="field-block"><label for="membership_package_value" class="field-label">Package value (RM)</label><input id="membership_package_value" name="membership_package_value" type="number" min="0" step="0.01" value="{{ old('membership_package_value', $customer->membership_package_value !== null ? number_format($customer->membership_package_value, 2, '.', '') : '') }}" class="form-input"></div>
                            <div class="field-block"><label for="membership_balance" class="field-label">Balance left (RM)</label><input id="membership_balance" name="membership_balance" type="number" min="0" step="0.01" value="{{ old('membership_balance', $customer->membership_balance !== null ? number_format($customer->membership_balance, 2, '.', '') : '') }}" class="form-input"></div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header"><div class="panel-title">Administrative</div></div>
                        <div class="panel-body stack">
                            <div class="field-block"><label for="notes" class="field-label">Notes</label><textarea id="notes" name="notes" rows="6" class="form-textarea">{{ old('notes', $customer->notes) }}</textarea></div>
                            <div class="summary-card">
                                <div class="selection-card__title">Read-only operational history</div>
                                <div class="small-note">Previous appointments, services performed, and appointment history remain read-only here to preserve audit integrity.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn-row btn-row--end">
                <a href="{{ route('app.customers.show', $customer) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save customer changes</button>
            </div>
        </form>
    </div>
</x-internal-layout>
