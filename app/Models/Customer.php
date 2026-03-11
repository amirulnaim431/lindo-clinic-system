<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Customer extends Model
{
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
        'weight',
        'height',
        'allergies',
        'emergency_contact_name',
        'emergency_contact_phone',
        'membership_code',
        'membership_type',
        'current_package',
        'current_package_since',
        'notes',
        'legacy_code',
    ];

    protected $casts = [
        'dob' => 'date',
        'current_package_since' => 'date',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function appointmentGroups(): HasMany
    {
        return $this->hasMany(AppointmentGroup::class)->latest('appointment_date');
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
}