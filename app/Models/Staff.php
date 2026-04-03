<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

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

    public const PIC_GROUP_LABELS = [
        'management' => 'Management',
        'aesthetic' => 'Aesthetic',
        'doctor' => 'Doctor',
        'others' => 'Others',
    ];

    protected $table = 'staff';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'full_name',
        'employee_code',
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

    public static function normalizePicGroup(?string $role): string
    {
        $normalized = mb_strtolower(trim((string) $role));

        return match ($normalized) {
            'management' => 'management',
            'aesthetic', 'aestatic', 'nurse', 'beautician' => 'aesthetic',
            'doctor' => 'doctor',
            default => 'others',
        };
    }

    public static function picGroupLabel(?string $role): string
    {
        $group = self::normalizePicGroup($role);

        return self::PIC_GROUP_LABELS[$group]
            ?? str($group)->replace('_', ' ')->title()->toString();
    }

    public static function picGroupRank(?string $role): int
    {
        return match (self::normalizePicGroup($role)) {
            'management' => 1,
            'aesthetic' => 2,
            'doctor' => 3,
            default => 4,
        };
    }

    public static function sortForPicSelector(Collection $staffList): Collection
    {
        return $staffList
            ->sort(function (self $left, self $right) {
                $leftRank = self::picGroupRank($left->operational_role ?: $left->role_key ?: $left->role);
                $rightRank = self::picGroupRank($right->operational_role ?: $right->role_key ?: $right->role);

                if ($leftRank === $rightRank) {
                    return strcasecmp($left->full_name, $right->full_name);
                }

                return $leftRank <=> $rightRank;
            })
            ->values();
    }

    public static function groupForPicSelector(Collection $staffList): Collection
    {
        return self::sortForPicSelector($staffList)
            ->groupBy(fn (self $staff) => self::normalizePicGroup($staff->operational_role ?: $staff->role_key ?: $staff->role))
            ->map(fn (Collection $group, string $key) => [
                'key' => $key,
                'label' => self::PIC_GROUP_LABELS[$key]
                    ?? str($key)->replace('_', ' ')->title()->toString(),
                'staff' => $group->values(),
            ])
            ->values();
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

    public function leaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class, 'staff_id');
    }

    public function accessStatus(): array
    {
        if (! $this->user_id || ! $this->user) {
            return [
                'label' => 'Not provisioned',
                'tone' => 'neutral',
                'description' => 'No internal login has been created for this staff member yet.',
            ];
        }

        if (! $this->is_active || ! $this->can_login) {
            return [
                'label' => 'Suspended',
                'tone' => 'neutral',
                'description' => 'The linked account is retained, but sign-in is currently disabled.',
            ];
        }

        if ($this->user->password_setup_required) {
            return [
                'label' => 'Invite pending',
                'tone' => 'warning',
                'description' => 'A password setup link should be completed before the account is considered active.',
            ];
        }

        return [
            'label' => 'Active',
            'tone' => 'success',
            'description' => 'The linked account is provisioned and can access the internal workspace.',
        ];
    }

    public function accessLevelLabel(): string
    {
        if (! $this->user) {
            return 'No internal access';
        }

        return $this->user->role === 'admin'
            ? 'Super admin access'
            : 'Operational staff access';
    }
}
