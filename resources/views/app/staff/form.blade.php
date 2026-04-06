<x-internal-layout
    :title="$mode === 'create' ? 'Create Staff' : 'Edit Staff'"
    :subtitle="$mode === 'create'
        ? 'Provision a staff profile, assign services, and decide whether this person should receive operational or executive-level system access.'
        : 'Update staff identity, operational scope, and login access from one controlled workspace.'"
>
    @php
        $selectedServiceIds = collect(old('service_ids', $selectedServiceIds ?? []))->map(fn ($id) => (string) $id)->all();
        $selectedPermissions = collect(old('access_permissions', $selectedPermissions ?? []))->filter()->values()->all();
        $selectedAccessLevel = old('access_level', $selectedAccessLevel ?? 'staff');
        $accessStatus = $accessStatus ?? ['label' => 'Not provisioned', 'tone' => 'neutral', 'description' => 'No internal login has been created for this staff member yet.'];
        $linkedUser = $staff->user;
        $accessPreviewLabel = $selectedAccessLevel === 'admin' ? 'Super admin access' : 'Operational staff access';
        $loginEnabled = old('can_login', $staff->can_login);
    @endphp

    <div class="stack">
        @if (session('staff_access_delivery_mode') === 'email')
            <div class="alert alert-success">
                <div><strong>Invite email sent.</strong> A password setup email was sent to {{ session('staff_access_email') ?: 'the staff member' }}.</div>
            </div>
        @endif

        @if (session('staff_access_delivery_mode') === 'logged')
            <div class="alert alert-success">
                <div><strong>Email delivery is not active on this server.</strong> The current mailer is set to `{{ session('staff_access_mailer') }}`, so the invite was written to logs instead of being delivered.</div>
                <div class="mt-2" style="word-break: break-all;">
                    <a href="{{ session('staff_access_link') }}" target="_blank" rel="noopener">{{ session('staff_access_link') }}</a>
                </div>
                @if (session('staff_access_email'))
                    <div class="field-note mt-2">Fallback setup link for {{ session('staff_access_email') }}</div>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert" aria-live="polite">
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

            <section class="staff-form-hero panel">
                <div class="panel-body staff-form-hero__body">
                    <div>
                        <div class="section-kicker">Provisioning workspace</div>
                        <div class="panel-title-display">{{ $mode === 'create' ? 'Set up the staff member first, then decide their access tier.' : 'Review identity, access tier, and account readiness at a glance.' }}</div>
                    </div>

                    <div class="staff-form-hero__stats">
                        <div class="staff-brief-card">
                            <div class="micro-label">Employee code</div>
                            <div class="selection-card__title mt-2">{{ old('employee_code', $staff->employee_code ?: 'Auto-generated on save') }}</div>
                        </div>
                        <div class="staff-brief-card">
                            <div class="micro-label">Access status</div>
                            <div class="selection-card__title mt-2">{{ $accessStatus['label'] }}</div>
                        </div>
                        <div class="staff-brief-card">
                            <div class="micro-label">Access tier</div>
                            <div class="selection-card__title mt-2">{{ $linkedUser ? $staff->accessLevelLabel() : $accessPreviewLabel }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="staff-form-layout">
                <div class="stack">
                    <section class="panel">
                        <div class="panel-header">
                            <x-section-heading kicker="Identity" title="Core staff profile" subtitle="Keep the official staff record clean and complete before provisioning access." />
                        </div>
                        <div class="panel-body">
                            <div class="form-grid">
                                <div class="col-8 field-block">
                                    <label class="field-label" for="full_name">Full name</label>
                                    <input id="full_name" name="full_name" type="text" class="form-input" value="{{ old('full_name', $staff->full_name) }}" required>
                                </div>
                                <div class="col-4 field-block">
                                    <label class="field-label" for="employee_code">Employee code</label>
                                    <input id="employee_code" name="employee_code" type="text" class="form-input" value="{{ old('employee_code', $staff->employee_code) }}" placeholder="Auto-generated">
                                </div>

                                <div class="col-6 field-block">
                                    <label class="field-label" for="job_title">Job title</label>
                                    <input id="job_title" name="job_title" type="text" class="form-input" value="{{ old('job_title', $staff->job_title) }}" required>
                                </div>
                                <div class="col-3 field-block">
                                    <label class="field-label" for="department">Department</label>
                                    <select id="department" name="department" class="form-select">
                                        <option value="">Select department</option>
                                        @foreach ($departmentOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('department', $staff->department) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-3 field-block">
                                    <label class="field-label" for="operational_role">Operational role</label>
                                    <select id="operational_role" name="operational_role" class="form-select" required>
                                        <option value="">Select role</option>
                                        @foreach ($roleOptions as $value => $label)
                                            <option value="{{ $value }}" @selected(old('operational_role', $staff->operational_role) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-6 field-block">
                                    <label class="field-label" for="email">Work email</label>
                                    <input id="email" name="email" type="email" class="form-input @error('email') form-input--error @enderror" value="{{ old('email', $staff->email) }}" placeholder="Required for login access">
                                    <div class="field-note">This becomes the sign-in identity if internal access is enabled.</div>
                                    @error('email')
                                        <div class="field-error">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6 field-block">
                                    <label class="field-label" for="phone">Phone</label>
                                    <input id="phone" name="phone" type="text" class="form-input" value="{{ old('phone', $staff->phone) }}" placeholder="Optional">
                                </div>

                                <div class="col-12 field-block">
                                    <label class="field-label" for="notes">Notes</label>
                                    <textarea id="notes" name="notes" class="form-textarea" rows="4" placeholder="Optional admin notes about responsibilities, reporting lines, or handling instructions.">{{ old('notes', $staff->notes) }}</textarea>
                                </div>

                                <div class="col-12">
                                    <label class="btn-row btn-row--start">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active))>
                                        <span class="helper-text">Active staff record</span>
                                    </label>
                                    <div class="field-note">Inactive staff remain in the directory for historical traceability but are excluded from active operations.</div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-header">
                            <x-section-heading kicker="Operational scope" title="Eligible services" subtitle="Assign only the services this person can deliver or oversee operationally." />
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
                    </section>
                </div>

                <div class="stack">
                    <section class="panel staff-access-panel">
                        <div class="panel-header">
                            <x-section-heading kicker="Access strategy" title="Login access" subtitle="Choose whether this person should stay offline, receive operational access, or be elevated to super admin." />
                        </div>
                        <div class="panel-body stack">
                            <div class="staff-access-status-card">
                                <div class="selection-card__head">
                                    <div>
                                        <div class="selection-card__title">{{ $accessStatus['label'] }}</div>
                                        <div class="selection-card__meta">{{ $accessStatus['description'] }}</div>
                                    </div>
                                    <x-status-pill :label="$linkedUser ? $staff->accessLevelLabel() : $accessPreviewLabel" :tone="$selectedAccessLevel === 'admin' ? 'warning' : 'info'" />
                                </div>
                                <div class="staff-access-status-card__note">
                                    <div class="staff-access-facts">
                                        <div class="staff-access-fact">
                                            <span class="staff-access-fact__label">Linked account</span>
                                            <span class="staff-access-fact__value">{{ $linkedUser?->email ?: 'No linked account yet' }}</span>
                                        </div>
                                        <div class="staff-access-fact">
                                            <span class="staff-access-fact__label">Internal login</span>
                                            <span class="staff-access-fact__value">{{ $loginEnabled ? 'Enabled' : 'Disabled' }}</span>
                                        </div>
                                        <div class="staff-access-fact">
                                            <span class="staff-access-fact__label">Setup link</span>
                                            <span class="staff-access-fact__value">
                                                {{ $linkedUser?->last_password_reset_sent_at ? 'Generated '.$linkedUser->last_password_reset_sent_at->diffForHumans() : 'Not generated yet' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="staff-access-toggle-card">
                                <div class="staff-access-toggle-card__row">
                                    <div>
                                        <div class="selection-card__title">Allow internal login</div>
                                        <div class="selection-card__meta">Turn this on only when the work email is correct and this person should sign in to the internal workspace.</div>
                                    </div>
                                    <label class="staff-access-toggle" for="staff-can-login">
                                        <input type="hidden" name="can_login" value="0">
                                        <input id="staff-can-login" type="checkbox" name="can_login" value="1" @checked($loginEnabled)>
                                        <span class="staff-access-toggle__switch" aria-hidden="true"></span>
                                        <span class="staff-access-toggle__text">{{ $loginEnabled ? 'Enabled' : 'Disabled' }}</span>
                                    </label>
                                </div>
                                <div class="staff-access-toggle-card__note">When enabled, the system provisions a linked account automatically and prepares a secure password setup link.</div>
                            </div>

                            <div class="stack">
                                <div>
                                    <div class="field-label">Access tier</div>
                                    <div class="field-note">Use super admin only for approved HOD, board, or executive users who need unrestricted access.</div>
                                </div>
                                <div class="stack">
                                    @foreach ($accessLevelOptions as $levelKey => $level)
                                        @php $isLevelSelected = $selectedAccessLevel === $levelKey; @endphp
                                        <label class="selection-card {{ $isLevelSelected ? 'is-selected' : '' }}">
                                            <input type="radio" class="selection-input access-level-radio" name="access_level" value="{{ $levelKey }}" {{ $isLevelSelected ? 'checked' : '' }}>
                                            <div class="selection-card__head">
                                                <div>
                                                    <div class="selection-card__title">{{ $level['label'] }}</div>
                                                    <div class="selection-card__meta">{{ $level['description'] }}</div>
                                                </div>
                                                <span class="selection-card__badge">{{ $isLevelSelected ? 'Selected' : 'Available' }}</span>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div class="stack">
                                <div>
                                    <div class="field-label">Permissions</div>
                                    <div class="field-note">Operational staff access uses explicit permissions. Super admin accounts automatically receive full access.</div>
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

                            @if ($mode === 'edit' && $linkedUser)
                                <div class="staff-access-actions">
                                    <button type="submit" form="staff-access-invite-form" class="btn btn-secondary">Generate setup/reset link</button>

                                    <button type="submit" form="staff-access-status-form" class="btn btn-secondary">{{ $staff->can_login ? 'Suspend login' : 'Enable login' }}</button>
                                </div>
                            @endif
                        </div>
                    </section>
                </div>
            </div>

            <div class="btn-row btn-row--between">
                <a href="{{ route('app.staff.index') }}" class="btn btn-secondary">Back to staff</a>
                <button type="submit" class="btn btn-primary">{{ $mode === 'create' ? 'Create staff profile' : 'Save staff changes' }}</button>
            </div>
        </form>

        @if ($mode === 'edit' && $linkedUser)
            <form id="staff-access-invite-form" method="POST" action="{{ route('app.staff.access.invite', $staff) }}" class="hidden">
                @csrf
            </form>

            <form id="staff-access-status-form" method="POST" action="{{ route('app.staff.access.status', $staff) }}" class="hidden">
                @csrf
                @method('PATCH')
                <input type="hidden" name="can_login" value="{{ $staff->can_login ? '0' : '1' }}">
            </form>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleSelectionCardState = function (checkbox) {
                const card = checkbox.closest('.selection-card');
                const badge = card ? card.querySelector('.selection-card__badge') : null;

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

            document.querySelectorAll('.permission-checkbox, .service-checkbox').forEach(function (checkbox) {
                toggleSelectionCardState(checkbox);
                checkbox.addEventListener('change', function () {
                    toggleSelectionCardState(checkbox);
                });
            });

            const permissionCheckboxes = Array.from(document.querySelectorAll('.permission-checkbox'));
            const accessLevelRadios = Array.from(document.querySelectorAll('.access-level-radio'));

            const syncAccessLevelCards = function () {
                accessLevelRadios.forEach(function (radio) {
                    const card = radio.closest('.selection-card');
                    const badge = card ? card.querySelector('.selection-card__badge') : null;

                    if (!card) {
                        return;
                    }

                    card.classList.toggle('is-selected', radio.checked);

                    if (badge) {
                        badge.textContent = radio.checked ? 'Selected' : 'Available';
                    }
                });
            };

            const syncPermissionState = function () {
                const selectedLevel = accessLevelRadios.find(function (radio) {
                    return radio.checked;
                });
                const isAdminLevel = selectedLevel && selectedLevel.value === 'admin';

                permissionCheckboxes.forEach(function (checkbox) {
                    checkbox.disabled = !!isAdminLevel;
                    const card = checkbox.closest('.selection-card');

                    if (card) {
                        card.classList.toggle('is-disabled', !!isAdminLevel);
                    }
                });
            };

            accessLevelRadios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    syncAccessLevelCards();
                    syncPermissionState();
                });
            });

            syncAccessLevelCards();
            syncPermissionState();
        });
    </script>
</x-internal-layout>
