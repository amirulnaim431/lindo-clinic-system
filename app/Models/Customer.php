<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Customer extends Model
{
    use SoftDeletes, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['full_name', 'phone', 'email', 'dob'];

    protected $casts = [
        'dob' => 'date',
    ];

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }
}