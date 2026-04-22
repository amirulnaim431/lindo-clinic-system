<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentItemOptionSelection extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'appointment_item_id',
        'service_option_group_id',
        'service_option_value_id',
        'option_group_name',
        'option_value_label',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function appointmentItem(): BelongsTo
    {
        return $this->belongsTo(AppointmentItem::class);
    }

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(ServiceOptionGroup::class, 'service_option_group_id');
    }

    public function optionValue(): BelongsTo
    {
        return $this->belongsTo(ServiceOptionValue::class, 'service_option_value_id');
    }
}
