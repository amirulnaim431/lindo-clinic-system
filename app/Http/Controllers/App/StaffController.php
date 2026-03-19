<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    protected function operationalRoleOptions(): array
    {
        return Staff::operationalRoleOptions();
    }

    protected function departmentOptions(): array
    {
        return [
            'Executive Office' => 'Executive Office',
            'Medical' => 'Medical',
            'Beauty & Aesthetic' => 'Beauty & Aesthetic',
            'Administration' => 'Administration',
            'Finance & Accounts' => 'Finance & Accounts',
            'Human Resources' => 'Human Resources',
            'Sales & Marketing' => 'Sales & Marketing',
            'Operations Support' => 'Operations Support',
        ];
    }

    protected function permissionOptions(): array
    {
        return Staff::accessPermissionOptions();
    }

    protected function serviceOptions()
    {
        return Service::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->get();
    }

    protected function userOptions()
    {
        return User::query()
            ->whereIn('role', ['admin', 'staff'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);
    }

    protected function validatedData(Request $request, ?Staff $staff = null): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'job_title' => ['required', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'operational_role' => ['required', 'string', Rule::in(array_keys($this->operationalRoleOptions()))],
            'is_active' => ['nullable'],
            'can_login' => ['nullable'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('staff', 'user_id')->ignore($staff?->id),
            ],
            'notes' => ['nullable', 'string', 'max:4000'],
            'access_permissions' => ['nullable', 'array'],
            'access_permissions.*' => ['string', Rule::in(array_keys($this->permissionOptions()))],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['string', 'exists:services,id'],
        ]);

        if (! empty($data['user_id'])) {
            $linkedUser = User::query()->find($data['user_id']);

            if (! $linkedUser || ! in_array($linkedUser->role, ['admin', 'staff'], true)) {
                throw ValidationException::withMessages([
                    'user_id' => 'Selected user must be a staff or admin login.',
                ]);
            }
        }

        if ($request->boolean('can_login') && empty($data['user_id'])) {
            throw ValidationException::withMessages([
                'user_id' => 'Link a login user before enabling staff login.',
            ]);
        }

        if (
            $request->boolean('can_login')
            && ! empty($data['user_id'])
            && User::query()->whereKey($data['user_id'])->value('role') === 'staff'
            && empty($data['access_permissions'])
        ) {
            throw ValidationException::withMessages([
                'access_permissions' => 'Select at least one access permission for a staff login.',
            ]);
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['can_login'] = $request->boolean('can_login');
        $data['access_permissions'] = collect($data['access_permissions'] ?? [])->values()->all();
        $data['service_ids'] = collect($data['service_ids'] ?? [])->map(fn ($id) => (string) $id)->values()->all();

        return $data;
    }

    protected function formViewData(string $mode, Staff $staff): array
    {
        $staff->load('services', 'user');

        return [
            'mode' => $mode,
            'staff' => $staff,
            'roleOptions' => $this->operationalRoleOptions(),
            'departmentOptions' => $this->departmentOptions(),
            'permissionOptions' => $this->permissionOptions(),
            'services' => $this->serviceOptions(),
            'availableUsers' => $this->userOptions(),
            'selectedServiceIds' => $staff->services->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'selectedPermissions' => collect($staff->access_permissions ?? [])->values()->all(),
        ];
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'department' => trim((string) $request->input('department', '')),
            'operational_role' => trim((string) $request->input('operational_role', '')),
            'status' => trim((string) $request->input('status', 'active')),
            'login' => trim((string) $request->input('login', 'all')),
        ];

        $staff = Staff::query()
            ->with([
                'services' => fn ($query) => $query->orderBy('name'),
                'user:id,name,email,role',
            ])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($nested) use ($filters) {
                    $nested
                        ->where('full_name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('job_title', 'like', '%'.$filters['search'].'%')
                        ->orWhere('department', 'like', '%'.$filters['search'].'%')
                        ->orWhere('phone', 'like', '%'.$filters['search'].'%')
                        ->orWhere('email', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['department'] !== '', fn ($query) => $query->where('department', $filters['department']))
            ->when($filters['operational_role'] !== '', fn ($query) => $query->where('operational_role', $filters['operational_role']))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($filters['login'] === 'enabled', fn ($query) => $query->where('can_login', true))
            ->when($filters['login'] === 'disabled', fn ($query) => $query->where('can_login', false))
            ->orderByDesc('is_active')
            ->orderBy('department')
            ->orderBy('job_title')
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return view('app.staff.index', [
            'staff' => $staff,
            'filters' => $filters,
            'stats' => [
                'total' => Staff::query()->count(),
                'active' => Staff::query()->where('is_active', true)->count(),
                'inactive' => Staff::query()->where('is_active', false)->count(),
                'login_enabled' => Staff::query()->where('can_login', true)->count(),
            ],
            'roleOptions' => $this->operationalRoleOptions(),
            'departmentOptions' => $this->departmentOptions(),
            'canManageStaff' => auth()->user()?->hasAppPermission('staff.manage') ?? false,
        ]);
    }

    public function create()
    {
        $staff = new Staff([
            'is_active' => true,
            'can_login' => false,
            'access_permissions' => [],
        ]);

        return view('app.staff.form', $this->formViewData('create', $staff));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $staff = Staff::query()->create([
            'full_name' => $data['full_name'],
            'job_title' => $data['job_title'],
            'department' => $data['department'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'operational_role' => $data['operational_role'],
            'is_active' => $data['is_active'],
            'can_login' => $data['can_login'],
            'user_id' => $data['user_id'] ?? null,
            'notes' => $data['notes'] ?: null,
            'access_permissions' => $data['access_permissions'],
        ]);

        $staff->services()->sync($data['service_ids']);

        return redirect()
            ->route('app.staff.index')
            ->with('success', 'Staff record created successfully.');
    }

    public function edit(Staff $staff)
    {
        return view('app.staff.form', $this->formViewData('edit', $staff));
    }

    public function update(Request $request, Staff $staff)
    {
        $data = $this->validatedData($request, $staff);

        $staff->update([
            'full_name' => $data['full_name'],
            'job_title' => $data['job_title'],
            'department' => $data['department'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'operational_role' => $data['operational_role'],
            'is_active' => $data['is_active'],
            'can_login' => $data['can_login'],
            'user_id' => $data['user_id'] ?? null,
            'notes' => $data['notes'] ?: null,
            'access_permissions' => $data['access_permissions'],
        ]);

        $staff->services()->sync($data['service_ids']);

        return redirect()
            ->route('app.staff.index')
            ->with('success', 'Staff record updated successfully.');
    }

    public function updateStatus(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'is_active' => ['required', Rule::in(['0', '1'])],
        ]);

        $staff->update([
            'is_active' => $validated['is_active'] === '1',
        ]);

        return redirect()
            ->route('app.staff.index', $request->only(['search', 'department', 'operational_role', 'status', 'login', 'page']))
            ->with('success', 'Staff status updated successfully.');
    }
}
