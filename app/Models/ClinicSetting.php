<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function appointmentSchedule(): array
    {
        $defaults = [
            'start_time' => '10:00',
            'end_time' => '19:00',
            'slot_duration_minutes' => 45,
            'slot_step_minutes' => 60,
            'boxes_per_slot' => 2,
        ];

        $setting = static::query()->where('key', 'appointment_schedule')->first();

        return array_merge($defaults, is_array($setting?->value) ? $setting->value : []);
    }

    public static function putAppointmentSchedule(array $value): void
    {
        static::query()->updateOrCreate(
            ['key' => 'appointment_schedule'],
            ['value' => array_merge(static::appointmentSchedule(), $value)]
        );
    }
}
