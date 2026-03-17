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
        'weight',
        'height',
        'allergies',
        'notes',
    ];

    protected $casts = [
        'dob' => 'date',
        'current_package_since' => 'date',
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
}