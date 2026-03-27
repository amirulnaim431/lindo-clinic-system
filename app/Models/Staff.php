<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const OPERATIONAL_ROLE_OPTIONS = [
        'doctor' => 'Doctor',
        'nurse' => 'Nurse',
        'beautician' => 'Beautician',
        'admin' => 'Administration',
        'operations' => 'Operations',
        'marketing' => 'Sales & Marketing',
        'management' => 'Management',
        'support' => 'Support',
    ];

    public const ACCESS_PERMISSION_OPTIONS = [
        'dashboard.view' => [
            'label' => 'Dashboard',
            'description' => 'View the internal dashboard and daily clinic overview.',
        ],
        'appointments.view' => [
            'label' => 'Appointments',
            'description' => 'View appointment queues and booking workload.',
        ],
        'appointments.manage' => [
            'label' => 'Manage appointments',
            'description' => 'Create appointments and update appointment statuses.',
        ],
        'calendar.view' => [
            'label' => 'Calendar',
            'description' => 'Open the operational calendar scheduler.',
        ],
        'customers.view' => [
            'label' => 'Customers',
            'description' => 'View customer lists and customer profiles.',
        ],
        'customers.manage' => [
            'label' => 'Edit customers',
            'description' => 'Edit customer information from the internal CRM.',
        ],
        'customers.import' => [
            'label' => 'Import customers',
            'description' => 'Import customer files into the CRM.',
        ],
        'staff.view' => [
            'label' => 'Staff directory',
            'description' => 'View staff records, service assignments, and login linkage.',
        ],
        'staff.manage' => [
            'label' => 'Manage staff',
            'description' => 'Create staff, update records, assign services, and manage access.',
        ],
        'hr.schedule' => [
            'label' => 'HR schedule',
            'description' => 'Open the HR staff schedule workspace and manage roster visibility.',
        ],
    ];

    protected $table = 'staff';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'full_name',
        'job_title',
        'department',
        'phone',
        'email',
        'operational_role',
        'role_key',
        'role',
        'is_active',
        'can_login',
        'user_id',
        'notes',
        'access_permissions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'can_login' => 'boolean',
        'access_permissions' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $staff): void {
            $normalizedRole = $staff->attributes['operational_role']
                ?? $staff->attributes['role_key']
                ?? $staff->attributes['role']
                ?? null;

            if ($normalizedRole !== null && $normalizedRole !== '') {
                $staff->attributes['operational_role'] = $normalizedRole;
                $staff->attributes['role_key'] = $normalizedRole;
                $staff->attributes['role'] = $normalizedRole;
            }

            foreach (['job_title', 'department', 'phone', 'email', 'notes'] as $attribute) {
                if (array_key_exists($attribute, $staff->attributes) && blank($staff->attributes[$attribute])) {
                    $staff->attributes[$attribute] = null;
                }
            }

            $permissions = collect($staff->attributes['access_permissions'] ?? [])
                ->filter(fn ($permission) => is_string($permission) && isset(self::ACCESS_PERMISSION_OPTIONS[$permission]))
                ->unique()
                ->values()
                ->all();

            $staff->attributes['access_permissions'] = json_encode($permissions);
        });
    }

    public static function operationalRoleOptions(): array
    {
        return self::OPERATIONAL_ROLE_OPTIONS;
    }

    public static function accessPermissionOptions(): array
    {
        return self::ACCESS_PERMISSION_OPTIONS;
    }

    public function getOperationalRoleAttribute($value): ?string
    {
        return $value ?: ($this->attributes['role_key'] ?? $this->attributes['role'] ?? null);
    }

    public function getRoleKeyAttribute($value): ?string
    {
        return $value ?: ($this->attributes['operational_role'] ?? $this->attributes['role'] ?? null);
    }

    public function getRoleAttribute($value): ?string
    {
        return $value ?: ($this->attributes['operational_role'] ?? $this->attributes['role_key'] ?? null);
    }

    public function getOperationalRoleLabelAttribute(): string
    {
        $role = $this->operational_role;

        if (! $role) {
            return 'Unassigned';
        }

        return self::OPERATIONAL_ROLE_OPTIONS[$role]
            ?? str($role)->replace('_', ' ')->title()->toString();
    }

    public function hasAccessPermission(string $permission): bool
    {
        $granted = collect($this->access_permissions ?? []);

        if ($permission === 'dashboard.view' && $granted->isNotEmpty()) {
            return true;
        }

        if ($granted->contains($permission)) {
            return true;
        }

        $impliedPermissions = [
            'appointments.view' => 'appointments.manage',
            'customers.view' => 'customers.manage',
            'staff.view' => 'staff.manage',
        ];

        return isset($impliedPermissions[$permission]) && $granted->contains($impliedPermissions[$permission]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services', 'staff_id', 'service_id')
            ->using(StaffService::class)
            ->withTimestamps();
    }

    public function appointmentItems(): HasMany
    {
        return $this->hasMany(AppointmentItem::class, 'staff_id');
    }
}
