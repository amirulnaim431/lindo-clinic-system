<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceOptionGroup extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'selection_mode',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ServiceOptionValue::class)
            ->orderBy('display_order')
            ->orderBy('label');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_option_group_service')
            ->withPivot(['id', 'is_required', 'display_order'])
            ->withTimestamps();
    }
}
