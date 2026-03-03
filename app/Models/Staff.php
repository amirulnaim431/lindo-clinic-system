<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Staff extends Model
{
    use SoftDeletes, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'full_name',
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function services()
    {
        return $this->belongsToMany(Service::class, 'staff_services', 'staff_id', 'service_id');
    }

    /**
     * Appointment items assigned to this staff member.
     * This must exist because AppointmentController uses whereDoesntHave('appointmentItems').
     */
    public function appointmentItems()
    {
        return $this->hasMany(AppointmentItem::class, 'staff_id');
    }
}