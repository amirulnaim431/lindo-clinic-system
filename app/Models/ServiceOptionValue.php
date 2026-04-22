<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceOptionValue extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'service_option_group_id',
        'value_code',
        'label',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ServiceOptionGroup::class, 'service_option_group_id');
    }
}
