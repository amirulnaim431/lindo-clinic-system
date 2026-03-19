<x-internal-layout
    :title="$mode === 'create' ? 'Create Staff' : 'Edit Staff'"
    :subtitle="$mode === 'create'
        ? 'Add a staff profile with job title, operational role, service eligibility, and optional system access.'
        : 'Update the staff profile, service eligibility, linked login, and access permissions.'"
>
    @php
        $selectedServiceIds = collect(old('service_ids', $selectedServiceIds ?? []))
            ->map(fn ($id) => (string) $id)
            ->all();

        $selectedPermissions = collect(old('access_permissions', $selectedPermissions ?? []))
            ->filter()
            ->values()
            ->all();
    @endphp

    <style>
        .staff-card-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .selection-card {
            border: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            border-radius: 18px;
            padding: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            transition: 0.18s ease;
        }

        .selection-card:hover {
            border-color: #94a3b8;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
        }

        .selection-card.is-selected {
            border-color: #0f172a;
            box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .selection-card__header {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: start;
        }

        .selection-card__title {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        .selection-card__meta {
            color: #64748b;
            font-size: 12px;
            margin-top: 6px;
        }

        .selection-card__badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            border: 1px solid #cbd5e1;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 800;
            color: #334155;
            background: #ffffff;
            white-space: nowrap;
        }

        @media (max-width: 920px) {
            .staff-card-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="stack" style="max-width:1180px;">
        @if ($errors->any())
            <div class="alert alert-error">
                <div style="font-weight:800; margin-bottom:8px;">Please fix the following:</div>
                <ul style="margin:0; padding-left:18px;">
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
                <div class="panel__header">
                    <h2 class="panel__title">Profile details</h2>
                    <div class="panel__subtitle">
                        Capture the staff member's official title and contact details separately from their operational service role.
                    </div>
                </div>

                <div class="panel__body">
                    <div class="form-grid">
                        <div class="col-6">
                            <label class="field-label" for="full_name">Full name</label>
                            <input id="full_name" name="full_name" type="text" class="field-input" value="{{ old('full_name', $staff->full_name) }}" required>
                        </div>

                        <div class="col-6">
                            <label class="field-label" for="job_title">Job title</label>
                            <input id="job_title" name="job_title" type="text" class="field-input" value="{{ old('job_title', $staff->job_title) }}" required>
                        </div>

                        <div class="col-4">
                            <label class="field-label" for="department">Department</label>
                            <select id="department" name="department" class="field-select">
                                <option value="">Select department</option>
                                @foreach ($departmentOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('department', $staff->department) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-4">
                            <label class="field-label" for="operational_role">Operational role</label>
                            <select id="operational_role" name="operational_role" class="field-select" required>
                                <option value="">Select role</option>
                                @foreach ($roleOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('operational_role', $staff->operational_role) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-4">
                            <label class="field-label" for="email">Email</label>
                            <input id="email" name="email" type="email" class="field-input" value="{{ old('email', $staff->email) }}" placeholder="Optional">
                        </div>

                        <div class="col-4">
                            <label class="field-label" for="phone">Phone</label>
                            <input id="phone" name="phone" type="text" class="field-input" value="{{ old('phone', $staff->phone) }}" placeholder="Optional">
                        </div>

                        <div class="col-8">
                            <label class="field-label" for="notes">Notes</label>
                            <textarea id="notes" name="notes" class="field-input" rows="4" placeholder="Optional admin notes about operations, coverage, or internal handling.">{{ old('notes', $staff->notes) }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="btn-row" style="align-items:center;">
                                <label style="display:inline-flex; align-items:center; gap:10px;">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active))>
                                    <span>Active staff record</span>
                                </label>

                                <span class="text-muted">
                                    Inactive staff remain in the directory for history but are excluded from active scheduling lists.
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__header">
                    <h2 class="panel__title">Login linkage and permissions</h2>
                    <div class="panel__subtitle">
                        Link an existing internal user account if this staff member should access the system. Permissions are separate from job title and service role.
                    </div>
                </div>

                <div class="panel__body stack">
                    <div class="form-grid">
                        <div class="col-6">
                            <label class="field-label" for="user_id">Linked login user</label>
                            <select id="user_id" name="user_id" class="field-select">
                                <option value="">No linked login user</option>
                                @foreach ($availableUsers as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('user_id', $staff->user_id) === (string) $user->id)>
                                        {{ $user->name }} - {{ $user->email }} - {{ strtoupper($user->role) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="text-muted" style="margin-top:8px;">
                                Staff-role logins use the selected permissions below. Admin-role logins retain full admin access.
                            </div>
                        </div>

                        <div class="col-6" style="display:flex; align-items:end;">
                            <div class="btn-row" style="align-items:center;">
                                <label style="display:inline-flex; align-items:center; gap:10px;">
                                    <input type="hidden" name="can_login" value="0">
                                    <input type="checkbox" name="can_login" value="1" @checked(old('can_login', $staff->can_login))>
                                    <span>Allow linked staff login</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="field-label">System access</div>
                        <div class="text-muted" style="margin-bottom:14px;">
                            Choose which internal modules the linked staff login can access or manage.
                        </div>

                        <div class="staff-card-grid">
                            @foreach ($permissionOptions as $permissionKey => $permission)
                                @php
                                    $checked = in_array($permissionKey, $selectedPermissions, true);
                                @endphp

                                <label class="selection-card {{ $checked ? 'is-selected' : '' }}">
                                    <input type="checkbox" class="permission-checkbox" name="access_permissions[]" value="{{ $permissionKey }}" {{ $checked ? 'checked' : '' }} style="position:absolute; opacity:0; pointer-events:none;">

                                    <div class="selection-card__header">
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
                <div class="panel__header">
                    <h2 class="panel__title">Service assignment</h2>
                    <div class="panel__subtitle">
                        Assign only the services this staff member is operationally allowed to handle.
                    </div>
                </div>

                <div class="panel__body">
                    @if ($services->count())
                        <div class="staff-card-grid">
                            @foreach ($services as $service)
                                @php
                                    $isSelected = in_array((string) $service->id, $selectedServiceIds, true);
                                @endphp

                                <label class="selection-card {{ $isSelected ? 'is-selected' : '' }}">
                                    <input type="checkbox" class="service-checkbox" name="service_ids[]" value="{{ $service->id }}" {{ $isSelected ? 'checked' : '' }} style="position:absolute; opacity:0; pointer-events:none;">

                                    <div class="selection-card__header">
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
                        <div class="empty-state">
                            No active services are available for assignment.
                        </div>
                    @endif
                </div>
            </div>

            <div class="btn-row" style="justify-content:space-between;">
                <a href="{{ route('app.staff.index') }}" class="btn btn-secondary">Back to Staff</a>
                <button type="submit" class="btn btn-primary">{{ $mode === 'create' ? 'Create Staff' : 'Save Changes' }}</button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.permission-checkbox').forEach(function (checkbox) {
                const card = checkbox.closest('.selection-card');
                const badge = card ? card.querySelector('.selection-card__badge') : null;

                const syncState = function () {
                    if (! card) {
                        return;
                    }

                    card.classList.toggle('is-selected', checkbox.checked);

                    if (badge) {
                        badge.textContent = checkbox.checked ? 'Enabled' : 'Optional';
                    }
                };

                syncState();
                checkbox.addEventListener('change', syncState);
            });

            document.querySelectorAll('.service-checkbox').forEach(function (checkbox) {
                const card = checkbox.closest('.selection-card');
                const badge = card ? card.querySelector('.selection-card__badge') : null;

                const syncState = function () {
                    if (! card) {
                        return;
                    }

                    card.classList.toggle('is-selected', checkbox.checked);

                    if (badge) {
                        badge.textContent = checkbox.checked ? 'Assigned' : 'Optional';
                    }
                };

                syncState();
                checkbox.addEventListener('change', syncState);
            });
        });
    </script>
</x-internal-layout>
