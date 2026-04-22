<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppointmentItem extends Model
{
    use SoftDeletes, HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'appointment_group_id',
        'service_id',
        'service_name_snapshot',
        'service_category_key_snapshot',
        'service_category_label_snapshot',
        'staff_id',
        'staff_name_snapshot',
        'staff_role_snapshot',
        'required_role',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(AppointmentGroup::class, 'appointment_group_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id')->withTrashed();
    }

    public function optionSelections(): HasMany
    {
        return $this->hasMany(AppointmentItemOptionSelection::class)
            ->orderBy('display_order')
            ->orderBy('option_group_name');
    }

    public function displayServiceName(): string
    {
        return (string) ($this->service_name_snapshot ?: $this->service?->name ?: 'Service');
    }

    public function displayCategoryLabel(): string
    {
        if ($this->service_category_label_snapshot) {
            return (string) $this->service_category_label_snapshot;
        }

        $categoryKey = $this->service_category_key_snapshot ?: $this->service?->category_key;

        return Service::categoryOptions()[$categoryKey] ?? 'Service';
    }

    public function displayStaffName(): string
    {
        return (string) ($this->staff_name_snapshot ?: $this->staff?->full_name ?: 'Unassigned staff');
    }

    public function displayStaffRole(): ?string
    {
        return $this->staff_role_snapshot ?: $this->staff?->role_key;
    }
}
