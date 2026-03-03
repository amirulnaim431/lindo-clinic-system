<?php

namespace App\Domain\Appointments;

use App\Enums\AppointmentStatus;

class AppointmentStatusFlow
{
    public static function canTransition(AppointmentStatus $from, AppointmentStatus $to): bool
    {
        if ($from === $to) return true;

        return match ($from) {
            AppointmentStatus::Booked => in_array($to, [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Cancelled,
            ], true),

            AppointmentStatus::Confirmed => in_array($to, [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
            ], true),

            AppointmentStatus::Completed => false, // lock after completed (audit-friendly)
            AppointmentStatus::Cancelled => false, // lock after cancelled
        };
    }

    public static function allowedTargets(AppointmentStatus $from): array
    {
        return array_values(array_filter(
            AppointmentStatus::cases(),
            fn($to) => self::canTransition($from, $to)
        ));
    }
}