<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class StaffService extends Pivot
{
    use HasUlids;

    protected $table = 'staff_services';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'staff_id',
        'service_id',
    ];
}