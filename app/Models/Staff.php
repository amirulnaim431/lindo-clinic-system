<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasUlids;

    protected $table = 'staff';

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
        return $this->belongsToMany(Service::class, 'staff_services', 'staff_id', 'service_id')
            ->using(StaffService::class)
            ->withTimestamps();
    }

    public function appointmentItems()
    {
        return $this->hasMany(AppointmentItem::class, 'staff_id');
    }
}