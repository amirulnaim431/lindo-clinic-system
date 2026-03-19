<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
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
}
