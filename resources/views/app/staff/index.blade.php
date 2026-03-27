<x-internal-layout :title="'Staff Directory'" :subtitle="'Manage staff profiles, service eligibility, login access, and operational permissions.'">
    <div class="stack">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <section class="stats-grid">
            <x-stat-card label="Total staff" :value="$stats['total']" />
            <x-stat-card label="Active staff" :value="$stats['active']" />
            <x-stat-card label="Inactive staff" :value="$stats['inactive']" />
            <x-stat-card label="Login enabled" :value="$stats['login_enabled']" />
        </section>

        <section class="panel">
            <div class="panel-header">
                <div class="filter-bar__head">
                    <x-section-heading
                        kicker="Staff filters"
                        title="Team directory"
                        subtitle="Search staff by name, job title, phone, or email, then narrow by operational role, department, status, or login availability." />

                    @if ($canManageStaff)
                        <a href="{{ route('app.staff.create') }}" class="btn btn-primary">Add staff</a>
                    @endif
                </div>
            </div>

            <div class="panel-body">
                <form method="GET" action="{{ route('app.staff.index') }}" class="form-grid">
                    <div class="col-4 field-block">
                        <label class="field-label" for="search">Search</label>
                        <input id="search" name="search" type="text" class="form-input" value="{{ $filters['search'] }}" placeholder="Name, title, phone, or email">
                    </div>
                    <div class="col-3 field-block">
                        <label class="field-label" for="department">Department</label>
                        <select id="department" name="department" class="form-select">
                            <option value="">All departments</option>
                            @foreach ($departmentOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['department'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-2 field-block">
                        <label class="field-label" for="operational_role">Operational role</label>
                        <select id="operational_role" name="operational_role" class="form-select">
                            <option value="">All roles</option>
                            @foreach ($roleOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['operational_role'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-2 field-block">
                        <label class="field-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="all" @selected($filters['status'] === 'all')>All</option>
                            <option value="active" @selected($filters['status'] === 'active')>Active</option>
                            <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-1 field-block">
                        <label class="field-label" for="login">Login</label>
                        <select id="login" name="login" class="form-select">
                            <option value="all" @selected($filters['login'] === 'all')>All</option>
                            <option value="enabled" @selected($filters['login'] === 'enabled')>Enabled</option>
                            <option value="disabled" @selected($filters['login'] === 'disabled')>Disabled</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="filter-bar__head">
                            <div class="small-note">Showing {{ $staff->total() }} staff record{{ $staff->total() === 1 ? '' : 's' }}.</div>
                            <div class="btn-row">
                                <button type="submit" class="btn btn-primary">Apply filters</button>
                                <a href="{{ route('app.staff.index') }}" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <x-section-heading
                    kicker="Records"
                    title="Staff profiles"
                    subtitle="Job title, operational role, service assignment, login linkage, and system access are managed separately for cleaner administration." />
            </div>

            <div class="panel-body">
                @if ($staff->count())
                    <div class="table-shell">
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
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($staff as $member)
                                        <tr>
                                            <td>
                                                <div class="selection-card__title">{{ $member->full_name }}</div>
                                                <div class="small-note">{{ $member->job_title ?: 'No job title set' }}</div>
                                                @if ($member->email || $member->phone)
                                                    <div class="small-note mt-2">
                                                        {{ $member->email ?: 'No email' }}
                                                        @if ($member->email && $member->phone)
                                                            -
                                                        @endif
                                                        {{ $member->phone ?: 'No phone' }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td><span class="chip">{{ $member->department ?: 'Unassigned' }}</span></td>
                                            <td>
                                                <div class="selection-card__title">{{ $member->operational_role_label }}</div>
                                                <div class="small-note">{{ $member->job_title ?: 'No title' }}</div>
                                            </td>
                                            <td>
                                                @if ($member->services->count())
                                                    <div class="inline-chip-row">
                                                        @foreach ($member->services as $service)
                                                            <span class="chip">{{ $service->name }}</span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="small-note">No services assigned</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($member->user)
                                                    <div class="selection-card__title">{{ $member->user->name }}</div>
                                                    <div class="small-note">{{ $member->user->email }}</div>
                                                    <div class="mt-2">
                                                        <x-status-pill :label="$member->can_login ? 'Login enabled' : 'Login disabled'" :tone="$member->can_login ? 'success' : 'neutral'" />
                                                    </div>
                                                @else
                                                    <span class="small-note">No linked login user</span>
                                                @endif
                                            </td>
                                            <td>
                                                <x-status-pill :label="$member->is_active ? 'Active' : 'Inactive'" :tone="$member->is_active ? 'success' : 'neutral'" class="staff-directory-status-pill" />
                                            </td>
                                            <td class="text-right">
                                                @if ($canManageStaff)
                                                    <div class="btn-row btn-row--end">
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
                                                            <button type="submit" class="btn btn-secondary">{{ $member->is_active ? 'Set inactive' : 'Set active' }}</button>
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
                                                            <button type="submit" class="btn btn-danger staff-directory-remove-btn">Remove</button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <span class="small-note">View only</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4">
                        {{ $staff->links() }}
                    </div>
                @else
                    <div class="empty-state empty-state--dashed">
                        <div class="empty-state__title">No staff matched the selected filters.</div>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-internal-layout>
