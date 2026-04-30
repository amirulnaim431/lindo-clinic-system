<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'full_name',
        'phone',
        'email',
        'dob',
        'ic_passport',
        'gender',
        'marital_status',
        'nationality',
        'occupation',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'membership_code',
        'membership_type',
        'current_package',
        'current_package_since',
        'membership_package_value_cents',
        'membership_balance_cents',
        'weight',
        'height',
        'allergies',
        'notes',
    ];

    protected $casts = [
        'dob' => 'date',
        'current_package_since' => 'date',
        'membership_package_value_cents' => 'integer',
        'membership_balance_cents' => 'integer',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function appointmentGroups(): HasMany
    {
        return $this->hasMany(AppointmentGroup::class)->latest('starts_at');
    }

    public function appointmentItems(): HasManyThrough
    {
        return $this->hasManyThrough(
            AppointmentItem::class,
            AppointmentGroup::class,
            'customer_id',
            'appointment_group_id',
            'id',
            'id'
        );
    }

    public function getMembershipPackageValueAttribute(): ?float
    {
        return $this->membership_package_value_cents === null
            ? null
            : $this->membership_package_value_cents / 100;
    }

    public function getMembershipBalanceAttribute(): ?float
    {
        return $this->membership_balance_cents === null
            ? null
            : $this->membership_balance_cents / 100;
    }
}
