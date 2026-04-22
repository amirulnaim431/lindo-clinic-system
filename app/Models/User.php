<?php

namespace App\Models;

use App\Notifications\StaffPasswordSetupNotification;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'password_setup_required',
        'last_password_reset_sent_at',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_setup_required' => 'boolean',
            'last_password_reset_sent_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isStaffOrAdmin(): bool
    {
        return in_array($this->role, ['staff', 'admin'], true);
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function canAccessInternalApp(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isStaff()) {
            return false;
        }

        $staffProfile = $this->staffProfile;

        if (! $staffProfile) {
            return true;
        }

        return $staffProfile->is_active && $staffProfile->can_login;
    }

    public function hasAppPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isStaff()) {
            return false;
        }

        $staffProfile = $this->staffProfile;

        if (! $staffProfile) {
            return true;
        }

        if (! $staffProfile->is_active || ! $staffProfile->can_login) {
            return false;
        }

        return $staffProfile->hasAccessPermission($permission);
    }

    public function canAccessHrSchedule(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (! $this->isStaff()) {
            return false;
        }

        $staffProfile = $this->staffProfile;

        if (! $staffProfile || ! $staffProfile->is_active || ! $staffProfile->can_login) {
            return false;
        }

        if ($staffProfile->hasAccessPermission('hr.schedule')) {
            return true;
        }

        return $staffProfile->department === 'Human Resources';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new StaffPasswordSetupNotification($token));
    }
}
