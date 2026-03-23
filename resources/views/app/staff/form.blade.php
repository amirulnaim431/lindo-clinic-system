<x-internal-layout
    :title="$mode === 'create' ? 'Create Staff' : 'Edit Staff'"
    :subtitle="$mode === 'create'
        ? 'Add a staff profile with job title, operational role, service eligibility, and optional system access.'
        : 'Update the staff profile, service eligibility, linked login, and access permissions.'"
>
    @php
        $selectedServiceIds = collect(old('service_ids', $selectedServiceIds ?? []))->map(fn ($id) => (string) $id)->all();
        $selectedPermissions = collect(old('access_permissions', $selectedPermissions ?? []))->filter()->values()->all();
    @endphp

    <div class="stack">
        @if ($errors->any())
            <div class="alert alert-error">
                <div>Please fix the following:</div>
                <ul style="margin:0.6rem 0 0 1rem;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ $mode === 'create' ? route('app.staff.store') : route('app.staff.update', $staff) }}" class="stack">
            @csrf
            @if ($mode === 'edit')
                @method('PUT')
            @endif

            <div class="panel">
                <div class="panel-header">
                    <x-section-heading kicker="Profile details" title="Core staff record" subtitle="Capture the staff member's official title and contact details separately from their operational role." />
                </div>
                <div class="panel-body">
                    <div class="form-grid">
                        <div class="col-6 field-block"><label class="field-label" for="full_name">Full name</label><input id="full_name" name="full_name" type="text" class="form-input" value="{{ old('full_name', $staff->full_name) }}" required></div>
                        <div class="col-6 field-block"><label class="field-label" for="job_title">Job title</label><input id="job_title" name="job_title" type="text" class="form-input" value="{{ old('job_title', $staff->job_title) }}" required></div>
                        <div class="col-4 field-block"><label class="field-label" for="department">Department</label><select id="department" name="department" class="form-select"><option value="">Select department</option>@foreach ($departmentOptions as $value => $label)<option value="{{ $value }}" @selected(old('department', $staff->department) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-4 field-block"><label class="field-label" for="operational_role">Operational role</label><select id="operational_role" name="operational_role" class="form-select" required><option value="">Select role</option>@foreach ($roleOptions as $value => $label)<option value="{{ $value }}" @selected(old('operational_role', $staff->operational_role) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-4 field-block"><label class="field-label" for="email">Email</label><input id="email" name="email" type="email" class="form-input" value="{{ old('email', $staff->email) }}" placeholder="Optional"></div>
                        <div class="col-4 field-block"><label class="field-label" for="phone">Phone</label><input id="phone" name="phone" type="text" class="form-input" value="{{ old('phone', $staff->phone) }}" placeholder="Optional"></div>
                        <div class="col-8 field-block"><label class="field-label" for="notes">Notes</label><textarea id="notes" name="notes" class="form-textarea" rows="4" placeholder="Optional admin notes about operations, coverage, or internal handling.">{{ old('notes', $staff->notes) }}</textarea></div>
                        <div class="col-12">
                            <label class="btn-row btn-row--start">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active))>
                                <span class="helper-text">Active staff record</span>
                            </label>
                            <div class="field-note">Inactive staff remain in the directory for history but are excluded from active scheduling lists.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <x-section-heading kicker="Access control" title="Login linkage and permissions" subtitle="Link an existing internal user account if this staff member should access the system." />
                </div>
                <div class="panel-body stack">
                    <div class="form-grid">
                        <div class="col-6 field-block">
                            <label class="field-label" for="user_id">Linked login user</label>
                            <select id="user_id" name="user_id" class="form-select">
                                <option value="">No linked login user</option>
                                @foreach ($availableUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('user_id', $staff->user_id) === (string) $user->id)>
                                        {{ $user->name }} - {{ $user->email }} - {{ strtoupper($user->role) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="field-note">Staff-role logins use the selected permissions below. Admin-role logins retain full admin access.</div>
                        </div>

                        <div class="col-6 field-block" style="align-self:end;">
                            <label class="btn-row btn-row--start">
                                <input type="hidden" name="can_login" value="0">
                                <input type="checkbox" name="can_login" value="1" @checked(old('can_login', $staff->can_login))>
                                <span class="helper-text">Allow linked staff login</span>
                            </label>
                        </div>
                    </div>

                    <div class="stack">
                        <div>
                            <div class="field-label">System access</div>
                            <div class="field-note">Choose which internal modules the linked staff login can access or manage.</div>
                        </div>
                        <div class="selection-grid">
                            @foreach ($permissionOptions as $permissionKey => $permission)
                                @php $checked = in_array($permissionKey, $selectedPermissions, true); @endphp
                                <label class="selection-card {{ $checked ? 'is-selected' : '' }}">
                                    <input type="checkbox" class="selection-input permission-checkbox" name="access_permissions[]" value="{{ $permissionKey }}" {{ $checked ? 'checked' : '' }}>
                                    <div class="selection-card__head">
                                        <div>
                                            <div class="selection-card__title">{{ $permission['label'] }}</div>
                                            <div class="selection-card__meta">{{ $permission['description'] }}</div>
                                        </div>
                                        <span class="selection-card__badge">{{ $checked ? 'Enabled' : 'Optional' }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <x-section-heading kicker="Service assignment" title="Eligible services" subtitle="Assign only the services this staff member is operationally allowed to handle." />
                </div>
                <div class="panel-body">
                    @if ($services->count())
                        <div class="selection-grid">
                            @foreach ($services as $service)
                                @php $isSelected = in_array((string) $service->id, $selectedServiceIds, true); @endphp
                                <label class="selection-card {{ $isSelected ? 'is-selected' : '' }}">
                                    <input type="checkbox" class="selection-input service-checkbox" name="service_ids[]" value="{{ $service->id }}" {{ $isSelected ? 'checked' : '' }}>
                                    <div class="selection-card__head">
                                        <div>
                                            <div class="selection-card__title">{{ $service->name }}</div>
                                            @if ($service->description)
                                                <div class="selection-card__meta">{{ $service->description }}</div>
                                            @endif
                                        </div>
                                        <span class="selection-card__badge">{{ $isSelected ? 'Assigned' : (((int) ($service->duration_minutes ?? 60)) . ' min') }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="empty-state empty-state--dashed">
                            <div class="empty-state__title">No active services are available for assignment.</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="btn-row btn-row--between">
                <a href="{{ route('app.staff.index') }}" class="btn btn-secondary">Back to staff</a>
                <button type="submit" class="btn btn-primary">{{ $mode === 'create' ? 'Create staff' : 'Save changes' }}</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.permission-checkbox, .service-checkbox').forEach(function (checkbox) {
                const card = checkbox.closest('.selection-card');
                const badge = card ? card.querySelector('.selection-card__badge') : null;

                const syncState = function () {
                    if (!card) {
                        return;
                    }

                    card.classList.toggle('is-selected', checkbox.checked);

                    if (badge) {
                        badge.textContent = checkbox.classList.contains('permission-checkbox')
                            ? (checkbox.checked ? 'Enabled' : 'Optional')
                            : (checkbox.checked ? 'Assigned' : 'Optional');
                    }
                };

                syncState();
                checkbox.addEventListener('change', syncState);
            });
        });
    </script>
</x-internal-layout>
