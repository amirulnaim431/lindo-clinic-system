<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

    protected function accessLevelOptions(): array
    {
        return [
            'staff' => [
                'label' => 'Operational staff access',
                'description' => 'Role-based access for daily operations such as appointments, calendar, CRM, and HR tools.',
            ],
            'admin' => [
                'label' => 'Super admin access',
                'description' => 'Full internal access for HOD, board members, and executive leaders who need unrestricted oversight.',
            ],
        ];
    }

    protected function serviceOptions()
    {
        $query = Service::query()
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('name', '!=', '');

        if (Service::supportsCatalogFields()) {
            $query
                ->orderBy('category_key')
                ->orderBy('display_order');
        }

        return $query
            ->orderBy('name')
            ->get();
    }

    protected function validatedData(Request $request, ?Staff $staff = null): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'employee_code' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('staff', 'employee_code')->ignore($staff?->id),
            ],
            'job_title' => ['required', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:120'],
            'operational_role' => ['required', 'string', Rule::in(array_keys($this->operationalRoleOptions()))],
            'is_active' => ['nullable'],
            'can_login' => ['nullable'],
            'access_level' => ['nullable', 'string', Rule::in(array_keys($this->accessLevelOptions()))],
            'notes' => ['nullable', 'string', 'max:4000'],
            'access_permissions' => ['nullable', 'array'],
            'access_permissions.*' => ['string', Rule::in(array_keys($this->permissionOptions()))],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['string', 'exists:services,id'],
        ]);

        if ($request->boolean('can_login') && blank($data['email'] ?? null)) {
            throw ValidationException::withMessages([
                'email' => 'Enter a work email before enabling internal login access.',
            ]);
        }

        if (
            $request->boolean('can_login')
            && ($request->input('access_level', $staff?->user?->role ?? 'staff') !== 'admin')
            && ($staff?->user?->role === 'staff' || ! $staff?->user_id || $request->input('access_level', 'staff') === 'staff')
            && empty($data['access_permissions'])
        ) {
            throw ValidationException::withMessages([
                'access_permissions' => 'Select at least one access permission for a staff login.',
            ]);
        }

        $data['employee_code'] = filled($data['employee_code'] ?? null)
            ? Str::upper(trim((string) $data['employee_code']))
            : null;
        $data['access_level'] = $request->boolean('can_login')
            ? (string) ($data['access_level'] ?? ($staff?->user?->role === 'admin' ? 'admin' : 'staff'))
            : null;
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
            'accessLevelOptions' => $this->accessLevelOptions(),
            'services' => $this->serviceOptions(),
            'selectedServiceIds' => $staff->services->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'selectedPermissions' => collect($staff->access_permissions ?? [])->values()->all(),
            'selectedAccessLevel' => old('access_level', $staff->user?->role === 'admin' ? 'admin' : 'staff'),
            'accessStatus' => $staff->exists ? $staff->accessStatus() : [
                'label' => 'Not provisioned',
                'tone' => 'neutral',
                'description' => 'Saving with login enabled will provision a linked staff account automatically.',
            ],
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
                'user:id,name,email,role,password_setup_required,last_password_reset_sent_at',
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
            'employee_code' => $this->generateNextEmployeeCode(),
        ]);

        return view('app.staff.form', $this->formViewData('create', $staff));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        $staff = Staff::query()->create([
            'full_name' => $data['full_name'],
            'employee_code' => $data['employee_code'] ?: $this->generateNextEmployeeCode(),
            'job_title' => $data['job_title'],
            'department' => $data['department'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'operational_role' => $data['operational_role'],
            'is_active' => $data['is_active'],
            'can_login' => $data['can_login'],
            'notes' => $data['notes'] ?: null,
            'access_permissions' => $data['access_permissions'],
        ]);

        $staff->services()->sync($data['service_ids']);

        $delivery = null;

        if ($staff->can_login) {
            $delivery = $this->provisionStaffAccess($staff, ! $staff->user_id, $data['access_level'] ?? 'staff');
        }

        return $this->redirectWithAccessMessage(
            route('app.staff.index'),
            'Staff record created successfully.',
            $staff,
            $delivery
        );
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
            'employee_code' => $data['employee_code'] ?: ($staff->employee_code ?: $this->generateNextEmployeeCode()),
            'job_title' => $data['job_title'],
            'department' => $data['department'] ?: null,
            'phone' => $data['phone'] ?: null,
            'email' => $data['email'] ?: null,
            'operational_role' => $data['operational_role'],
            'is_active' => $data['is_active'],
            'can_login' => $data['can_login'],
            'notes' => $data['notes'] ?: null,
            'access_permissions' => $data['access_permissions'],
        ]);

        $staff->services()->sync($data['service_ids']);

        $delivery = null;

        if ($staff->can_login) {
            $delivery = $this->provisionStaffAccess($staff, true, $data['access_level'] ?? ($staff->user?->role === 'admin' ? 'admin' : 'staff'));
        }

        return $this->redirectWithAccessMessage(
            route('app.staff.index'),
            'Staff record updated successfully.',
            $staff,
            $delivery
        );
    }

    public function sendAccessInvite(Request $request, Staff $staff)
    {
        abort_unless($staff->exists, 404);

        $delivery = $this->provisionStaffAccess($staff, true, $staff->user?->role === 'admin' ? 'admin' : 'staff');

        return $this->redirectWithAccessMessage(
            route('app.staff.edit', $staff),
            'A fresh password setup email was triggered for this staff account.',
            $staff,
            $delivery
        );
    }

    public function updateAccessStatus(Request $request, Staff $staff)
    {
        $validated = $request->validate([
            'can_login' => ['required', Rule::in(['0', '1'])],
        ]);

        if ($validated['can_login'] === '1') {
            $staff->update(['can_login' => true]);
            $delivery = $this->provisionStaffAccess($staff, false, $staff->user?->role === 'admin' ? 'admin' : 'staff');

            return $this->redirectWithAccessMessage(
                route('app.staff.edit', $staff),
                'Login access has been enabled for this staff member.',
                $staff,
                $delivery
            );
        }

        $staff->update(['can_login' => false]);

        return redirect()
            ->route('app.staff.edit', $staff)
            ->with('success', 'Login access has been suspended for this staff member.');
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

    public function destroy(Request $request, Staff $staff)
    {
        DB::transaction(function () use ($staff) {
            $staff->services()->detach();

            $existingNotes = trim((string) ($staff->notes ?? ''));
            $removalNote = 'Staff removed from active directory on '.now()->format('Y-m-d H:i:s').'.';

            $staff->update([
                'is_active' => false,
                'can_login' => false,
                'user_id' => null,
                'access_permissions' => [],
                'notes' => $existingNotes !== '' ? $existingNotes."\n\n".$removalNote : $removalNote,
            ]);

            $staff->delete();
        });

        return redirect()
            ->route('app.staff.index', $request->only(['search', 'department', 'operational_role', 'status', 'login', 'page']))
            ->with('success', 'Staff record removed successfully.');
    }

    protected function generateNextEmployeeCode(): string
    {
        $latest = Staff::withTrashed()
            ->whereNotNull('employee_code')
            ->where('employee_code', 'like', 'LND-%')
            ->orderByDesc('employee_code')
            ->value('employee_code');

        $nextNumber = 1;

        if (is_string($latest) && preg_match('/LND-(\d+)/', $latest, $matches) === 1) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        do {
            $candidate = 'LND-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Staff::withTrashed()->where('employee_code', $candidate)->exists());

        return $candidate;
    }

    protected function provisionStaffAccess(Staff $staff, bool $refreshResetLink, string $accessLevel = 'staff'): ?array
    {
        $staff->loadMissing('user');

        if (blank($staff->email)) {
            throw ValidationException::withMessages([
                'email' => 'A work email is required before access can be provisioned.',
            ]);
        }

        $staffEmail = Str::lower(trim((string) $staff->email));
        $existingUser = User::query()->whereRaw('LOWER(email) = ?', [$staffEmail])->first();

        if ($existingUser && $existingUser->role === 'admin' && $existingUser->id !== $staff->user_id) {
            throw ValidationException::withMessages([
                'email' => 'This email already belongs to an admin account. Use a dedicated staff work email for staff access.',
            ]);
        }

        if ($staff->user_id && $existingUser && $existingUser->id !== $staff->user_id) {
            throw ValidationException::withMessages([
                'email' => 'This work email already belongs to another internal user account.',
            ]);
        }

        if ($existingUser) {
            $linkedStaff = Staff::query()
                ->where('user_id', $existingUser->id)
                ->whereKeyNot($staff->id)
                ->first();

            if ($linkedStaff) {
                $linkedStaffEmail = Str::lower(trim((string) ($linkedStaff->email ?? '')));
                $canReclaimLinkedAccount = $linkedStaffEmail === $staffEmail
                    && (! $linkedStaff->is_active || ! $linkedStaff->can_login);

                if ($canReclaimLinkedAccount) {
                    $linkedStaff->update([
                        'user_id' => null,
                        'can_login' => false,
                    ]);
                } else {
                    $linkedStaffLabel = $linkedStaff->full_name ?: 'another staff profile';

                    if ($linkedStaff->employee_code) {
                        $linkedStaffLabel .= ' ('.$linkedStaff->employee_code.')';
                    }

                    throw ValidationException::withMessages([
                        'email' => 'This work email is already linked to '.$linkedStaffLabel.'.',
                    ]);
                }
            }
        }

        if (! $staff->user_id) {
            $user = $existingUser;

            if (! $user) {
                $user = User::query()->create([
                    'name' => $staff->full_name,
                    'email' => $staffEmail,
                    'password' => Hash::make(Str::password(32)),
                    'password_setup_required' => true,
                    'role' => $accessLevel === 'admin' ? 'admin' : 'staff',
                ]);
            } elseif (! in_array($user->role, ['staff', 'admin'], true)) {
                throw ValidationException::withMessages([
                    'email' => 'Only staff or admin internal accounts can be linked to staff access.',
                ]);
            }

            $staff->update([
                'user_id' => $user->id,
                'can_login' => true,
            ]);

            $staff->setRelation('user', $user);
        }

        $user = $staff->fresh(['user'])->user;

        if (! $user) {
            return null;
        }

        $user->forceFill([
            'name' => $staff->full_name,
            'email' => $staffEmail,
            'role' => $accessLevel === 'admin' ? 'admin' : 'staff',
        ]);

        if ($refreshResetLink || $user->password_setup_required) {
            $user->forceFill([
                'password_setup_required' => true,
                'last_password_reset_sent_at' => now(),
            ])->save();

            return $this->dispatchPasswordSetupNotification($user);
        }

        $user->save();

        return null;
    }

    protected function dispatchPasswordSetupNotification(User $user): array
    {
        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        $mailer = (string) config('mail.default', 'log');

        if (in_array($mailer, ['log', 'array'], true)) {
            return [
                'mode' => 'logged',
                'link' => route('password.reset', [
                    'token' => $token,
                    'email' => $user->email,
                ]),
                'mailer' => $mailer,
            ];
        }

        return [
            'mode' => 'email',
            'mailer' => $mailer,
        ];
    }

    protected function redirectWithAccessMessage(string $route, string $message, Staff $staff, ?array $delivery = null)
    {
        $redirect = redirect()->to($route)->with('success', $message);

        if (! $delivery) {
            return $redirect;
        }

        $redirect->with('staff_access_email', $staff->email)
            ->with('staff_access_name', $staff->full_name)
            ->with('staff_access_delivery_mode', $delivery['mode'] ?? 'email')
            ->with('staff_access_mailer', $delivery['mailer'] ?? null);

        if (($delivery['mode'] ?? null) === 'logged' && ! empty($delivery['link'])) {
            $redirect->with('staff_access_link', $delivery['link']);
        }

        return $redirect;
    }
}
