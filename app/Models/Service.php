<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const CATEGORY_OPTIONS = [
        'consultations' => 'Consultations',
        'wellness' => 'Wellness',
        'aesthetics' => 'Aesthetics',
        'spa' => 'Spa',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'category_key',
        'description',
        'duration_minutes',
        'price',
        'promo_price',
        'is_promo',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'price' => 'integer',
        'promo_price' => 'integer',
        'is_promo' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public static function categoryOptions(): array
    {
        return self::CATEGORY_OPTIONS;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_OPTIONS[$this->category_key]
            ?? str((string) $this->category_key)->replace('_', ' ')->title()->toString();
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'staff_services', 'service_id', 'staff_id')
            ->using(StaffService::class)
            ->withTimestamps();
    }
}
