<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentItem extends Model
{
    use SoftDeletes, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'appointment_group_id',
        'service_id',
        'staff_id',
        'required_role',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(App\Models\AppointmentGroup::class, 'appointment_group_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(App\Models\Service::class, 'service_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(App\Models\Staff::class, 'staff_id');
    }
}
