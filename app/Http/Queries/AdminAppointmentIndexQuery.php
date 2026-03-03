<?php

namespace App\Http\Queries;

use App\Models\AppointmentGroup;
use Illuminate\Database\Eloquent\Builder;

class AdminAppointmentIndexQuery
{
    public function build(array $filters): Builder
    {
        $date = $filters['date'] ?? now()->toDateString();
        $staffId = $filters['staff_id'] ?? null;

        return AppointmentGroup::query()
            ->with([
                'customer:id,full_name,phone',
                'items.staff:id,full_name,role',
            ])
            ->when($date, function (Builder $q) use ($date) {
                $q->whereDate('starts_at', $date);
            })
            ->when($staffId, function (Builder $q) use ($staffId) {
                $q->whereHas('items', function ($qq) use ($staffId) {
                    $qq->where('staff_id', $staffId);
                });
            })
            ->orderBy('starts_at');
    }
}