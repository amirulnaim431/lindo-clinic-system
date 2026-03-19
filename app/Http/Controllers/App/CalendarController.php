<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CalendarController extends Controller
{
    private const SLOT_MINUTES = 30;
    private const DAY_START_HOUR = 9;
    private const DAY_END_HOUR = 18;
    private const ROW_HEIGHT_PX = 72;

    private const SERVICE_PALETTES = [
        [
            'surface' => '#f3f8ff',
            'surface_strong' => '#dbeafe',
            'border' => '#bfdbfe',
            'accent' => '#2563eb',
            'accent_soft' => '#93c5fd',
            'chip_bg' => '#dbeafe',
            'chip_text' => '#1d4ed8',
            'text' => '#172554',
        ],
        [
            'surface' => '#f5fbfa',
            'surface_strong' => '#d1fae5',
            'border' => '#a7f3d0',
            'accent' => '#059669',
            'accent_soft' => '#6ee7b7',
            'chip_bg' => '#d1fae5',
            'chip_text' => '#047857',
            'text' => '#064e3b',
        ],
        [
            'surface' => '#fff7ed',
            'surface_strong' => '#fed7aa',
            'border' => '#fdba74',
            'accent' => '#ea580c',
            'accent_soft' => '#fb923c',
            'chip_bg' => '#fed7aa',
            'chip_text' => '#c2410c',
            'text' => '#7c2d12',
        ],
        [
            'surface' => '#fdf4ff',
            'surface_strong' => '#f5d0fe',
            'border' => '#f0abfc',
            'accent' => '#c026d3',
            'accent_soft' => '#e879f9',
            'chip_bg' => '#f5d0fe',
            'chip_text' => '#a21caf',
            'text' => '#701a75',
        ],
        [
            'surface' => '#fff8f1',
            'surface_strong' => '#fde68a',
            'border' => '#fcd34d',
            'accent' => '#d97706',
            'accent_soft' => '#fbbf24',
            'chip_bg' => '#fef3c7',
            'chip_text' => '#b45309',
            'text' => '#78350f',
        ],
        [
            'surface' => '#f5f3ff',
            'surface_strong' => '#ddd6fe',
            'border' => '#c4b5fd',
            'accent' => '#7c3aed',
            'accent_soft' => '#a78bfa',
            'chip_bg' => '#ddd6fe',
            'chip_text' => '#6d28d9',
            'text' => '#4c1d95',
        ],
        [
            'surface' => '#ecfeff',
            'surface_strong' => '#bae6fd',
            'border' => '#7dd3fc',
            'accent' => '#0891b2',
            'accent_soft' => '#38bdf8',
            'chip_bg' => '#bae6fd',
            'chip_text' => '#0e7490',
            'text' => '#164e63',
        ],
        [
            'surface' => '#fff1f2',
            'surface_strong' => '#fecdd3',
            'border' => '#fda4af',
            'accent' => '#e11d48',
            'accent_soft' => '#fb7185',
            'chip_bg' => '#fecdd3',
            'chip_text' => '#be123c',
            'text' => '#881337',
        ],
    ];

    private const STATUS_STYLES = [
        'booked' => [
            'label' => 'Pending',
            'dot' => '#f59e0b',
            'badge_bg' => '#fff7ed',
            'badge_border' => '#fed7aa',
            'badge_text' => '#c2410c',
        ],
        'confirmed' => [
            'label' => 'Confirmed',
            'dot' => '#0284c7',
            'badge_bg' => '#f0f9ff',
            'badge_border' => '#bae6fd',
            'badge_text' => '#0369a1',
        ],
        'checked_in' => [
            'label' => 'Checked In',
            'dot' => '#7c3aed',
            'badge_bg' => '#f5f3ff',
            'badge_border' => '#ddd6fe',
            'badge_text' => '#6d28d9',
        ],
        'completed' => [
            'label' => 'Completed',
            'dot' => '#059669',
            'badge_bg' => '#ecfdf5',
            'badge_border' => '#a7f3d0',
            'badge_text' => '#047857',
        ],
        'cancelled' => [
            'label' => 'Cancelled',
            'dot' => '#e11d48',
            'badge_bg' => '#fff1f2',
            'badge_border' => '#fecdd3',
            'badge_text' => '#be123c',
        ],
        'no_show' => [
            'label' => 'No-show',
            'dot' => '#475569',
            'badge_bg' => '#f8fafc',
            'badge_border' => '#cbd5e1',
            'badge_text' => '#475569',
        ],
    ];

    public function index(Request $request)
    {
        $selectedDate = $this->resolveSelectedDate($request);
        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::TUESDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(4)->endOfDay();
        $staffId = trim((string) $request->input('staff_id', ''));

        $staffList = Staff::query()
            ->select('id', 'full_name', 'role_key', 'job_title')
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get();

        $weekGroups = AppointmentGroup::query()
            ->with([
                'customer:id,full_name,phone,membership_type,membership_code,current_package',
                'items.service:id,name',
                'items.staff:id,full_name,role_key,job_title',
            ])
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->when($staffId !== '', function ($query) use ($staffId) {
                $query->whereHas('items', function ($itemQuery) use ($staffId) {
                    $itemQuery->where('staff_id', $staffId);
                });
            })
            ->orderBy('starts_at')
            ->get();

        $groupsForSelectedDay = $weekGroups
            ->filter(fn ($group) => optional($group->starts_at)?->isSameDay($selectedDate))
            ->values();

        $dayEvents = $groupsForSelectedDay
            ->map(fn (AppointmentGroup $group) => $this->mapGroupToEvent($group))
            ->filter()
            ->values();

        $timelineEvents = $this->applyTimelineLayout($dayEvents);
        $slots = $this->buildSlots($selectedDate);

        $days = collect(range(0, 4))->map(function (int $offset) use ($weekStart, $weekGroups, $selectedDate, $staffId) {
            $date = $weekStart->copy()->addDays($offset);
            $count = $weekGroups->filter(fn ($group) => optional($group->starts_at)?->isSameDay($date))->count();

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'full_label' => $date->format('l'),
                'display_date' => $date->format('d M'),
                'is_today' => $date->isToday(),
                'is_selected' => $date->isSameDay($selectedDate),
                'appointment_count' => $count,
                'url' => route('app.calendar', array_filter([
                    'week' => $weekStart->toDateString(),
                    'date' => $date->toDateString(),
                    'staff_id' => $staffId !== '' ? $staffId : null,
                ])),
            ];
        });

        $daySummary = $this->buildDaySummary($groupsForSelectedDay);
        $staffLoad = $this->buildStaffLoad($groupsForSelectedDay);
        $selectedStaff = $staffList->firstWhere('id', $staffId);

        return view('app.calendar.index', [
            'title' => 'Calendar',
            'subtitle' => 'Live operational schedule for front desk, treatment coordination, and quick booking.',
            'days' => $days,
            'slots' => $slots,
            'timelineEvents' => $timelineEvents,
            'timelineHeightPx' => count($slots) * self::ROW_HEIGHT_PX,
            'rowHeightPx' => self::ROW_HEIGHT_PX,
            'staffList' => $staffList,
            'staffId' => $staffId,
            'selectedStaff' => $selectedStaff,
            'selectedDate' => $selectedDate,
            'selectedDateLabel' => $selectedDate->format('l, d M Y'),
            'selectedDateIso' => $selectedDate->toDateString(),
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'currentWeek' => now()->startOfWeek(Carbon::TUESDAY)->toDateString(),
            'daySummary' => $daySummary,
            'staffLoad' => $staffLoad,
            'totalAppointments' => $groupsForSelectedDay->count(),
            'statusLegend' => $this->statusLegend(),
        ]);
    }

    private function resolveSelectedDate(Request $request): Carbon
    {
        $dateInput = trim((string) $request->input('date', ''));
        $weekInput = trim((string) $request->input('week', ''));

        $anchor = $dateInput !== ''
            ? Carbon::parse($dateInput)
            : ($weekInput !== '' ? Carbon::parse($weekInput) : now());

        $weekStart = $anchor->copy()->startOfWeek(Carbon::TUESDAY);
        $selectedDate = $dateInput !== ''
            ? Carbon::parse($dateInput)
            : $this->clampToClinicWeek($anchor, $weekStart);

        if ($selectedDate->lt($weekStart)) {
            return $weekStart->startOfDay();
        }

        if ($selectedDate->gt($weekStart->copy()->addDays(4))) {
            return $weekStart->copy()->addDays(4)->startOfDay();
        }

        return $selectedDate->startOfDay();
    }

    private function clampToClinicWeek(Carbon $date, Carbon $weekStart): Carbon
    {
        if ($date->betweenIncluded($weekStart, $weekStart->copy()->addDays(4))) {
            return $date->copy();
        }

        return $weekStart->copy();
    }

    private function buildSlots(Carbon $selectedDate): array
    {
        $slots = [];
        $cursor = $selectedDate->copy()->setTime(self::DAY_START_HOUR, 0);
        $end = $selectedDate->copy()->setTime(self::DAY_END_HOUR, 0);

        while ($cursor->lt($end)) {
            $slotTime = $cursor->format('H:i');

            $slots[] = [
                'time' => $slotTime,
                'label' => $cursor->format('h:i A'),
                'create_url' => route('app.appointments.index', [
                    'date' => $selectedDate->toDateString(),
                    'slot' => $slotTime,
                ]),
            ];

            $cursor->addMinutes(self::SLOT_MINUTES);
        }

        return $slots;
    }

    private function mapGroupToEvent(AppointmentGroup $group): ?array
    {
        $startsAt = $group->starts_at;
        $endsAt = $group->ends_at;

        if (! $startsAt || ! $endsAt) {
            return null;
        }

        $dayStart = $startsAt->copy()->setTime(self::DAY_START_HOUR, 0);
        $dayEnd = $startsAt->copy()->setTime(self::DAY_END_HOUR, 0);

        $visibleStart = $startsAt->copy()->max($dayStart);
        $visibleEnd = $endsAt->copy()->min($dayEnd);

        if ($visibleEnd->lte($visibleStart)) {
            return null;
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
                    'role' => $staff->job_title ?: $staff->role_key,
                ];
            })
            ->filter()
            ->unique(fn ($staff) => ($staff['name'] ?? '').'|'.($staff['role'] ?? ''))
            ->values();

        $serviceNames = $services->all();
        $staffNames = $staffMembers->pluck('name')->filter()->values()->all();
        $primaryService = $serviceNames[0] ?? 'General Service';
        $serviceStyles = $this->serviceVisuals($primaryService);
        $statusValue = $group->status instanceof AppointmentStatus
            ? $group->status->value
            : (string) $group->status;
        $statusStyles = $this->statusVisuals($statusValue);

        $topMinutes = $dayStart->diffInMinutes($visibleStart);
        $heightMinutes = max(self::SLOT_MINUTES, $visibleStart->diffInMinutes($visibleEnd));
        $pixelsPerMinute = self::ROW_HEIGHT_PX / self::SLOT_MINUTES;

        return [
            'id' => (string) $group->id,
            'customer_name' => optional($group->customer)->full_name ?: 'Unknown Customer',
            'customer_phone' => optional($group->customer)->phone ?: 'No phone recorded',
            'membership_label' => $this->membershipLabel($group),
            'service_names' => $serviceNames,
            'service_summary' => $this->summarizeNames($serviceNames, 'No service'),
            'staff_names' => $staffNames,
            'staff_summary' => $this->summarizeNames($staffNames, 'Unassigned'),
            'staff_details' => $staffMembers
                ->map(fn ($staff) => $staff['role'] ? $staff['name'].' - '.$staff['role'] : $staff['name'])
                ->values()
                ->all(),
            'start_time' => $startsAt->format('h:i A'),
            'end_time' => $endsAt->format('h:i A'),
            'date_label' => $startsAt->format('d M Y'),
            'status_value' => $statusValue,
            'status_label' => $statusStyles['label'],
            'notes' => $group->notes ?: null,
            'source' => $group->source ? str($group->source)->replace('_', ' ')->title()->toString() : null,
            'manage_url' => route('app.appointments.index', ['date' => $startsAt->toDateString()]),
            'create_url' => route('app.appointments.index', ['date' => $startsAt->toDateString(), 'slot' => $startsAt->format('H:i')]),
            'service_styles' => $serviceStyles,
            'status_styles' => $statusStyles,
            'primary_service' => $primaryService,
            'top_px' => (int) round($topMinutes * $pixelsPerMinute),
            'height_px' => max(60, (int) round($heightMinutes * $pixelsPerMinute) - 8),
            'start_minutes' => $dayStart->diffInMinutes($visibleStart),
            'end_minutes' => $dayStart->diffInMinutes($visibleEnd),
        ];
    }

    private function applyTimelineLayout(Collection $events): Collection
    {
        $sorted = $events->sortBy('start_minutes')->values();
        $clusters = [];
        $currentCluster = [];
        $clusterEnd = null;

        foreach ($sorted as $event) {
            if ($clusterEnd === null || $event['start_minutes'] < $clusterEnd) {
                $currentCluster[] = $event;
                $clusterEnd = $clusterEnd === null
                    ? $event['end_minutes']
                    : max($clusterEnd, $event['end_minutes']);
            } else {
                $clusters[] = $currentCluster;
                $currentCluster = [$event];
                $clusterEnd = $event['end_minutes'];
            }
        }

        if ($currentCluster !== []) {
            $clusters[] = $currentCluster;
        }

        $positioned = collect();

        foreach ($clusters as $cluster) {
            $lanes = [];
            $maxLaneIndex = -1;

            foreach ($cluster as $index => $event) {
                foreach ($lanes as $laneIndex => $laneEnd) {
                    if ($laneEnd <= $event['start_minutes']) {
                        unset($lanes[$laneIndex]);
                    }
                }

                $laneIndex = 0;
                while (array_key_exists($laneIndex, $lanes)) {
                    $laneIndex++;
                }

                $lanes[$laneIndex] = $event['end_minutes'];
                $maxLaneIndex = max($maxLaneIndex, $laneIndex);
                $cluster[$index]['lane_index'] = $laneIndex;
            }

            $columns = max(1, $maxLaneIndex + 1);

            foreach ($cluster as $event) {
                $event['width_pct'] = round(100 / $columns, 4);
                $event['left_pct'] = round($event['lane_index'] * $event['width_pct'], 4);
                $positioned->push($event);
            }
        }

        return $positioned->sortBy('start_minutes')->values();
    }

    private function buildDaySummary(Collection $groups): array
    {
        $statusCounts = [
            'pending' => 0,
            'confirmed' => 0,
            'checked_in' => 0,
            'completed' => 0,
            'cancelled_or_no_show' => 0,
        ];

        foreach ($groups as $group) {
            $status = $group->status instanceof AppointmentStatus
                ? $group->status->value
                : (string) $group->status;

            match ($status) {
                'confirmed' => $statusCounts['confirmed']++,
                'checked_in' => $statusCounts['checked_in']++,
                'completed' => $statusCounts['completed']++,
                'cancelled', 'no_show' => $statusCounts['cancelled_or_no_show']++,
                default => $statusCounts['pending']++,
            };
        }

        return [
            'total' => $groups->count(),
            'pending' => $statusCounts['pending'],
            'confirmed' => $statusCounts['confirmed'],
            'checked_in' => $statusCounts['checked_in'],
            'completed' => $statusCounts['completed'],
            'cancelled_or_no_show' => $statusCounts['cancelled_or_no_show'],
        ];
    }

    private function buildStaffLoad(Collection $groups): Collection
    {
        return $groups
            ->flatMap(fn ($group) => $group->items)
            ->groupBy('staff_id')
            ->map(function (Collection $items) {
                $staff = optional($items->first())->staff;
                $minutes = $items->sum(function ($item) {
                    if (! $item->starts_at || ! $item->ends_at) {
                        return 0;
                    }

                    return $item->starts_at->diffInMinutes($item->ends_at);
                });

                return [
                    'name' => $staff?->full_name ?? 'Unassigned',
                    'job_title' => $staff?->job_title ?: $staff?->role_key,
                    'appointments' => $items->count(),
                    'minutes' => $minutes,
                    'hours_label' => number_format($minutes / 60, 1).'h booked',
                ];
            })
            ->sortByDesc('minutes')
            ->values();
    }

    private function membershipLabel(AppointmentGroup $group): ?string
    {
        $customer = $group->customer;

        if (! $customer) {
            return null;
        }

        $parts = array_filter([
            $customer->membership_type,
            $customer->membership_code,
            $customer->current_package,
        ]);

        return $parts === [] ? null : implode(' | ', $parts);
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

    private function statusLegend(): array
    {
        return collect(self::STATUS_STYLES)
            ->map(fn ($style, $key) => [
                'key' => $key,
                'label' => $style['label'],
                'dot' => $style['dot'],
                'badge_bg' => $style['badge_bg'],
                'badge_border' => $style['badge_border'],
                'badge_text' => $style['badge_text'],
            ])
            ->values()
            ->all();
    }

    private function statusVisuals(string $status): array
    {
        return self::STATUS_STYLES[$status] ?? [
            'label' => str($status)->replace('_', ' ')->title()->toString(),
            'dot' => '#475569',
            'badge_bg' => '#f8fafc',
            'badge_border' => '#cbd5e1',
            'badge_text' => '#475569',
        ];
    }

    private function serviceVisuals(string $serviceName): array
    {
        $normalized = mb_strtolower(trim($serviceName));

        $palette = match (true) {
            str_contains($normalized, 'consult') => self::SERVICE_PALETTES[0],
            str_contains($normalized, 'facial'), str_contains($normalized, 'beauty'), str_contains($normalized, 'aesthetic') => self::SERVICE_PALETTES[7],
            str_contains($normalized, 'nail') => self::SERVICE_PALETTES[6],
            str_contains($normalized, 'inject') => self::SERVICE_PALETTES[5],
            str_contains($normalized, 'weight'), str_contains($normalized, 'detox') => self::SERVICE_PALETTES[4],
            default => self::SERVICE_PALETTES[abs(crc32($normalized)) % count(self::SERVICE_PALETTES)],
        };

        return $palette;
    }
}
