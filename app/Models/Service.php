<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;
use Illuminate\Support\Facades\Schema;

class Service extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const CATEGORY_OPTIONS = [
        'consultations' => 'Consultation',
        'wellness' => 'Wellness',
        'aesthetics' => 'Aesthetic',
        'spa' => 'Beauty Spa',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'service_code',
        'name',
        'category_key',
        'default_staff_role',
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

    public static function supportsCatalogFields(): bool
    {
        static $supportsCatalogFields = null;

        if ($supportsCatalogFields === null) {
            try {
                $supportsCatalogFields =
                    Schema::hasColumn('services', 'category_key')
                    && Schema::hasColumn('services', 'service_code')
                    && Schema::hasColumn('services', 'default_staff_role')
                    && Schema::hasColumn('services', 'description')
                    && Schema::hasColumn('services', 'promo_price')
                    && Schema::hasColumn('services', 'is_promo')
                    && Schema::hasColumn('services', 'display_order');
            } catch (Throwable) {
                $supportsCatalogFields = false;
            }
        }

        return $supportsCatalogFields;
    }

    public function getCategoryKeyAttribute($value): string
    {
        if (! self::supportsCatalogFields()) {
            return 'consultations';
        }

        return filled($value) ? (string) $value : 'consultations';
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_OPTIONS[$this->category_key]
            ?? str((string) $this->category_key)->replace('_', ' ')->title()->toString();
    }

    public function getPromoPriceAttribute($value): ?int
    {
        if (! self::supportsCatalogFields() || $value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function getIsPromoAttribute($value): bool
    {
        if (! self::supportsCatalogFields()) {
            return false;
        }

        return (bool) $value;
    }

    public function getDisplayOrderAttribute($value): int
    {
        if (! self::supportsCatalogFields() || $value === null || $value === '') {
            return 0;
        }

        return (int) $value;
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(ServiceOptionGroup::class, 'service_option_group_service')
            ->withPivot(['id', 'is_required', 'display_order'])
            ->withTimestamps()
            ->orderByPivot('display_order')
            ->orderBy('service_option_groups.display_order')
            ->orderBy('service_option_groups.name');
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'staff_services', 'service_id', 'staff_id')
            ->using(StaffService::class)
            ->withTimestamps();
    }
}
