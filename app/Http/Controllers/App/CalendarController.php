<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CalendarController extends Controller
{
    private const COLOR_PALETTE = [
        [
            'card' => 'border-sky-200 bg-sky-50 text-sky-950 hover:bg-sky-100',
            'dot' => 'bg-sky-500',
            'badge' => 'border-sky-200 bg-sky-100 text-sky-800',
        ],
        [
            'card' => 'border-emerald-200 bg-emerald-50 text-emerald-950 hover:bg-emerald-100',
            'dot' => 'bg-emerald-500',
            'badge' => 'border-emerald-200 bg-emerald-100 text-emerald-800',
        ],
        [
            'card' => 'border-violet-200 bg-violet-50 text-violet-950 hover:bg-violet-100',
            'dot' => 'bg-violet-500',
            'badge' => 'border-violet-200 bg-violet-100 text-violet-800',
        ],
        [
            'card' => 'border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100',
            'dot' => 'bg-amber-500',
            'badge' => 'border-amber-200 bg-amber-100 text-amber-800',
        ],
        [
            'card' => 'border-rose-200 bg-rose-50 text-rose-950 hover:bg-rose-100',
            'dot' => 'bg-rose-500',
            'badge' => 'border-rose-200 bg-rose-100 text-rose-800',
        ],
        [
            'card' => 'border-cyan-200 bg-cyan-50 text-cyan-950 hover:bg-cyan-100',
            'dot' => 'bg-cyan-500',
            'badge' => 'border-cyan-200 bg-cyan-100 text-cyan-800',
        ],
        [
            'card' => 'border-fuchsia-200 bg-fuchsia-50 text-fuchsia-950 hover:bg-fuchsia-100',
            'dot' => 'bg-fuchsia-500',
            'badge' => 'border-fuchsia-200 bg-fuchsia-100 text-fuchsia-800',
        ],
        [
            'card' => 'border-teal-200 bg-teal-50 text-teal-950 hover:bg-teal-100',
            'dot' => 'bg-teal-500',
            'badge' => 'border-teal-200 bg-teal-100 text-teal-800',
        ],
    ];

    public function index(Request $request)
    {
        $weekInput = $request->string('week')->toString();
        $anchor = $weekInput !== ''
            ? Carbon::parse($weekInput)
            : now();

        $weekStart = $anchor->copy()->startOfWeek(Carbon::TUESDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(4)->endOfDay();

        $days = collect(range(0, 4))->map(function (int $offset) use ($weekStart) {
            $date = $weekStart->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'full_label' => $date->format('l'),
                'display_date' => $date->format('d M'),
                'is_today' => $date->isToday(),
            ];
        });

        $staffList = Staff::query()
            ->select('id', 'full_name', 'role_key')
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $staffId = $request->string('staff_id')->toString();

        $groups = AppointmentGroup::query()
            ->with([
                'customer:id,full_name,phone',
                'items.service:id,name',
                'items.staff:id,full_name,role_key',
            ])
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->when($staffId !== '', function ($query) use ($staffId) {
                $query->whereHas('items', function ($itemQuery) use ($staffId) {
                    $itemQuery->where('staff_id', $staffId);
                });
            })
            ->orderBy('starts_at')
            ->get();

        $eventsByDay = $days->mapWithKeys(function (array $day) {
            return [$day['date'] => collect()];
        });

        foreach ($groups as $group) {
            $dateKey = optional($group->starts_at)?->toDateString();

            if (! $dateKey || ! $eventsByDay->has($dateKey)) {
                continue;
            }

            $services = $group->items
                ->map(fn ($item) => optional($item->service)->name)
                ->filter()
                ->unique()
                ->values();

            $staffMembers = $group->items
                ->map(function ($item) {
                    $staff = $item->staff;

                    if (! $staff) {
                        return null;
                    }

                    return [
                        'name' => $staff->full_name,
                        'role' => $staff->role_key,
                    ];
                })
                ->filter()
                ->unique(fn ($staff) => ($staff['name'] ?? '').'|'.($staff['role'] ?? ''))
                ->values();

            $primaryService = $services->first() ?: 'General Service';
            $colors = $this->serviceColors($primaryService);

            $serviceNames = $services->all();
            $staffNames = $staffMembers->pluck('name')->filter()->values()->all();

            $event = [
                'id' => (string) $group->id,
                'customer_name' => optional($group->customer)->full_name ?: 'Unknown Customer',
                'customer_phone' => optional($group->customer)->phone ?: '—',
                'service_names' => $serviceNames,
                'service_summary' => $this->summarizeNames($serviceNames, 'No service'),
                'staff_names' => $staffNames,
                'staff_summary' => $this->summarizeNames($staffNames, 'Unassigned'),
                'staff_details' => $staffMembers->map(function ($staff) {
                    $role = $staff['role'] ?? null;

                    return $role
                        ? $staff['name'].' ('.$role.')'
                        : $staff['name'];
                })->values()->all(),
                'starts_at' => optional($group->starts_at)?->format('Y-m-d H:i:s'),
                'ends_at' => optional($group->ends_at)?->format('Y-m-d H:i:s'),
                'start_time' => optional($group->starts_at)?->format('h:i A') ?: '—',
                'end_time' => optional($group->ends_at)?->format('h:i A') ?: '—',
                'status_value' => $group->status instanceof \BackedEnum
                    ? $group->status->value
                    : (string) $group->status,
                'status_label' => $this->statusLabel($group->status),
                'notes' => $group->notes ?: null,
                'source' => $group->source ?: null,
                'color_card' => $colors['card'],
                'color_dot' => $colors['dot'],
                'color_badge' => $colors['badge'],
                'primary_service' => $primaryService,
            ];

            $eventsByDay[$dateKey]->push($event);
        }

        $eventsByDay = $eventsByDay->map(function (Collection $events) {
            return $events->sortBy('starts_at')->values();
        });

        $allEvents = $eventsByDay
            ->flatten(1)
            ->values();

        return view('app.calendar.index', [
            'title' => 'Calendar',
            'subtitle' => 'Operational clinic view for Tuesday to Saturday appointments.',
            'days' => $days,
            'eventsByDay' => $eventsByDay,
            'staffList' => $staffList,
            'staffId' => $staffId,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'currentWeek' => now()->startOfWeek(Carbon::TUESDAY)->toDateString(),
            'totalAppointments' => $allEvents->count(),
        ]);
    }

    private function summarizeNames(array $names, string $fallback): string
    {
        $names = array_values(array_filter($names));

        if (count($names) === 0) {
            return $fallback;
        }

        if (count($names) === 1) {
            return $names[0];
        }

        return $names[0].' +'.(count($names) - 1);
    }

    private function statusLabel(mixed $status): string
    {
        $value = $status instanceof \BackedEnum
            ? $status->value
            : (string) $status;

        if ($value === '') {
            return '—';
        }

        return str($value)->replace('_', ' ')->title()->toString();
    }

    private function serviceColors(string $serviceName): array
    {
        $hash = abs(crc32(mb_strtolower(trim($serviceName))));
        $index = $hash % count(self::COLOR_PALETTE);

        return self::COLOR_PALETTE[$index];
    }
}