<x-internal-layout :title="'Staff Directory'" :subtitle="'Manage staff profiles, service eligibility, login access, and operational permissions.'">
    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card__label">Total staff</div>
                <div class="stat-card__value">{{ $stats['total'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">Active staff</div>
                <div class="stat-card__value">{{ $stats['active'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">Inactive staff</div>
                <div class="stat-card__value">{{ $stats['inactive'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">Login enabled</div>
                <div class="stat-card__value">{{ $stats['login_enabled'] }}</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel__header" style="display:flex; justify-content:space-between; gap:16px; align-items:start; flex-wrap:wrap;">
                <div>
                    <h2 class="panel__title">Staff filters</h2>
                    <div class="panel__subtitle">
                        Search staff by name, job title, phone, or email, then narrow by operational role, department, status, or login availability.
                    </div>
                </div>

                @if ($canManageStaff)
                    <a href="{{ route('app.staff.create') }}" class="btn btn-primary">Add Staff</a>
                @endif
            </div>

            <div class="panel__body">
                <form method="GET" action="{{ route('app.staff.index') }}" class="form-grid">
                    <div class="col-4">
                        <label class="field-label" for="search">Search</label>
                        <input id="search" name="search" type="text" class="field-input" value="{{ $filters['search'] }}" placeholder="Name, title, phone, or email">
                    </div>

                    <div class="col-3">
                        <label class="field-label" for="department">Department</label>
                        <select id="department" name="department" class="field-select">
                            <option value="">All departments</option>
                            @foreach ($departmentOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['department'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-2">
                        <label class="field-label" for="operational_role">Operational role</label>
                        <select id="operational_role" name="operational_role" class="field-select">
                            <option value="">All roles</option>
                            @foreach ($roleOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['operational_role'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-2">
                        <label class="field-label" for="status">Status</label>
                        <select id="status" name="status" class="field-select">
                            <option value="all" @selected($filters['status'] === 'all')>All</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </div>

                    <div class="col-1">
                        <label class="field-label" for="login">Login</label>
                        <select id="login" name="login" class="field-select">
                            <option value="all" @selected($filters['login'] === 'all')>All</option>
                            <option value="enabled" @selected($filters['login'] === 'enabled')>Enabled</option>
                            <option value="disabled" @selected($filters['login'] === 'disabled')>Disabled</option>
                        </select>
                    </div>

                    <div class="col-12" style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center;">
                        <div class="text-muted">
                            Showing {{ $staff->total() }} staff record{{ $staff->total() === 1 ? '' : 's' }}.
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="{{ route('app.staff.index') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel__header">
                <h2 class="panel__title">Staff records</h2>
                <div class="panel__subtitle">
                    Job title, operational role, service assignment, login linkage, and system access are managed separately for cleaner administration.
                </div>
            </div>

            <div class="panel__body">
                @if ($staff->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Staff member</th>
                                    <th>Department</th>
                                    <th>Operational role</th>
                                    <th>Assigned services</th>
                                    <th>Login access</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($staff as $member)
                                    <tr>
                                        <td>
                                            <div style="font-weight:800;">{{ $member->full_name }}</div>
                                            <div class="text-muted">{{ $member->job_title ?: 'No job title set' }}</div>

                                            @if ($member->email || $member->phone)
                                                <div class="text-muted" style="margin-top:6px;">
                                                    {{ $member->email ?: 'No email' }}
                                                    @if ($member->email && $member->phone)
                                                        -
                                                    @endif
                                                    {{ $member->phone ?: 'No phone' }}
                                                </div>
                                            @endif
                                        </td>

                                        <td><span class="chip chip--soft">{{ $member->department ?: 'Unassigned' }}</span></td>

                                        <td>
                                            <div style="font-weight:700;">{{ $member->operational_role_label }}</div>
                                            <div class="text-muted">{{ $member->job_title ?: 'No title' }}</div>
                                        </td>

                                        <td>
                                            @if ($member->services->count())
                                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                    @foreach ($member->services as $service)
                                                        <span class="chip chip--soft">{{ $service->name }}</span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-muted">No services assigned</span>
                                            @endif
                                        </td>

                                        <td>
                                            @if ($member->user)
                                                <div style="font-weight:700;">{{ $member->user->name }}</div>
                                                <div class="text-muted">{{ $member->user->email }}</div>
                                                <div style="margin-top:8px;">
                                                    @if ($member->can_login)
                                                        <span class="chip" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46;">Login enabled</span>
                                                    @else
                                                        <span class="chip chip--soft">Login disabled</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-muted">No linked login user</span>
                                            @endif
                                        </td>

                                        <td>
                                            @if ($member->is_active)
                                                <span class="chip" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46;">Active</span>
                                            @else
                                                <span class="chip chip--soft">Inactive</span>
                                            @endif
                                        </td>

                                        <td style="text-align:right;">
                                            @if ($canManageStaff)
                                                <div class="btn-row" style="justify-content:flex-end;">
                                                    <a href="{{ route('app.staff.edit', $member) }}" class="btn btn-secondary">Edit</a>

                                                    <form method="POST" action="{{ route('app.staff.status', $member) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="is_active" value="{{ $member->is_active ? '0' : '1' }}">
                                                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                                        <input type="hidden" name="department" value="{{ $filters['department'] }}">
                                                        <input type="hidden" name="operational_role" value="{{ $filters['operational_role'] }}">
                                                        <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                                        <input type="hidden" name="login" value="{{ $filters['login'] }}">
                                                        <input type="hidden" name="page" value="{{ request('page', 1) }}">
                                                        <button type="submit" class="btn btn-secondary">
                                                            {{ $member->is_active ? 'Set Inactive' : 'Set Active' }}
                                                        </button>
                                                    </form>

                                                    <form method="POST" action="{{ route('app.staff.destroy', $member) }}" onsubmit="return confirm('Remove this staff record from the active directory? Historical appointments will be preserved.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                                        <input type="hidden" name="department" value="{{ $filters['department'] }}">
                                                        <input type="hidden" name="operational_role" value="{{ $filters['operational_role'] }}">
                                                        <input type="hidden" name="status" value="{{ $filters['status'] }}">
                                                        <input type="hidden" name="login" value="{{ $filters['login'] }}">
                                                        <input type="hidden" name="page" value="{{ request('page', 1) }}">
                                                        <button type="submit" class="btn btn-secondary" style="border-color:#fecdd3;color:#9f1239;background:#fff1f2;">
                                                            Remove
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-muted">View only</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:20px;">
                        {{ $staff->links() }}
                    </div>
                @else
                    <div class="empty-state">
                        No staff matched the selected filters.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-internal-layout>
