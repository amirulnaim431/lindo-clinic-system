<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Service extends Model
{
    use SoftDeletes, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['name', 'duration_minutes', 'price', 'is_active'];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'integer',
        'is_active' => 'boolean',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'staff_services', 'service_id', 'staff_id');
    }
}
