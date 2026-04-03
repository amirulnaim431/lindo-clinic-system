<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\Service;
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
    private const MAX_VISIBLE_COLUMNS = 4;

    private const SERVICE_PALETTES = [
        ['surface' => '#fff6fa', 'surface_strong' => '#fde7ee', 'border' => '#efbfd0', 'accent' => '#c0718a', 'accent_soft' => '#e4a9bc', 'chip_bg' => '#fde7ee', 'chip_text' => '#9f546c', 'text' => '#5e3946'],
        ['surface' => '#f8fbff', 'surface_strong' => '#ddeaf7', 'border' => '#bdd0e6', 'accent' => '#6f90b4', 'accent_soft' => '#a5bfd9', 'chip_bg' => '#ddeaf7', 'chip_text' => '#48617e', 'text' => '#31465f'],
        ['surface' => '#fff8f1', 'surface_strong' => '#f8dfca', 'border' => '#edc5a1', 'accent' => '#c48a5a', 'accent_soft' => '#e0b58d', 'chip_bg' => '#f8dfca', 'chip_text' => '#9f6b41', 'text' => '#69462d'],
        ['surface' => '#faf6ff', 'surface_strong' => '#e9def8', 'border' => '#d0bce9', 'accent' => '#9a79bf', 'accent_soft' => '#bea4da', 'chip_bg' => '#e9def8', 'chip_text' => '#795a9a', 'text' => '#523b6f'],
    ];

    private const STATUS_STYLES = [
        'booked' => ['label' => 'Pending', 'dot' => '#d79d68', 'badge_bg' => '#fff4ea', 'badge_border' => '#efd3b7', 'badge_text' => '#9f6b41'],
        'confirmed' => ['label' => 'Confirmed', 'dot' => '#7d9abb', 'badge_bg' => '#f2f7fc', 'badge_border' => '#cbd9e8', 'badge_text' => '#48617e'],
        'checked_in' => ['label' => 'Checked In', 'dot' => '#9a79bf', 'badge_bg' => '#f6f1fc', 'badge_border' => '#ddcfef', 'badge_text' => '#795a9a'],
        'completed' => ['label' => 'Completed', 'dot' => '#79ab91', 'badge_bg' => '#f0f8f3', 'badge_border' => '#c6dece', 'badge_text' => '#4c7a60'],
        'cancelled' => ['label' => 'Reschedule', 'dot' => '#cf7d95', 'badge_bg' => '#fff3f6', 'badge_border' => '#ebc0cf', 'badge_text' => '#9a4f67'],
        'no_show' => ['label' => 'Reschedule', 'dot' => '#9f8a91', 'badge_bg' => '#fbf8f9', 'badge_border' => '#dfd2d7', 'badge_text' => '#7d666e'],
    ];

    public function index(Request $request)
    {
        $selectedDate = $this->resolveSelectedDate($request);
        $viewMode = $request->input('view') === 'month' ? 'month' : 'week';
        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::TUESDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(4)->endOfDay();
        $monthStart = $selectedDate->copy()->startOfMonth()->startOfDay();
        $monthEnd = $selectedDate->copy()->endOfMonth()->endOfDay();
        $staffId = trim((string) $request->input('staff_id', ''));

        $staffList = Staff::sortForPicSelector(
            Staff::query()
            ->select('id', 'full_name', 'role_key', 'job_title', 'operational_role')
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get()
        );
        $picGroups = Staff::groupForPicSelector($staffList);

        $rangeStart = $viewMode === 'month' ? $monthStart : $weekStart;
        $rangeEnd = $viewMode === 'month' ? $monthEnd : $weekEnd;

        $groups = AppointmentGroup::query()
            ->with([
                'customer:id,full_name,phone,membership_type,membership_code,current_package',
                'items.service:id,name',
                'items.staff:id,full_name,role_key,job_title',
            ])
            ->where('starts_at', '<=', $rangeEnd)
            ->where('ends_at', '>=', $rangeStart)
            ->when($staffId !== '', function ($query) use ($staffId) {
                $query->whereHas('items', function ($itemQuery) use ($staffId) {
                    $itemQuery->where('staff_id', $staffId);
                });
            })
            ->orderBy('starts_at')
            ->get();

        $groupsForSelectedDay = $groups
            ->filter(fn ($group) => optional($group->starts_at)?->isSameDay($selectedDate))
            ->values();

        $visibleTimelineGroups = $groupsForSelectedDay
            ->reject(function ($group) {
                $status = $group->status instanceof AppointmentStatus ? $group->status->value : (string) $group->status;
                return in_array($status, ['cancelled', 'no_show'], true);
            })
            ->values();

        $dayEvents = $visibleTimelineGroups
            ->flatMap(fn (AppointmentGroup $group) => $group->items->map(fn (AppointmentItem $item) => $this->mapItemToEvent($group, $item)))
            ->filter()
            ->values();

        [$timelineEvents, $overflowSummaries] = $this->applyTimelineLayout($dayEvents);
        $slots = $this->buildSlots($selectedDate);
        $weekDays = $this->buildWeekDays($weekStart, $groups, $selectedDate, $staffId, $viewMode);
        $monthDays = $this->buildMonthDays($monthStart, $groups, $selectedDate, $staffId, $viewMode);
        $daySummary = $this->buildDaySummary($groupsForSelectedDay);
        $selectedStaff = $staffList->firstWhere('id', $staffId);
        $serviceOptions = Service::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($service) => [
                'id' => (string) $service->id,
                'name' => $service->name,
            ])
            ->values();
        $staffOptions = Staff::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'job_title', 'role_key'])
            ->map(fn ($staff) => [
                'id' => (string) $staff->id,
                'full_name' => $staff->full_name,
                'label' => $staff->job_title
                    ? $staff->full_name.' - '.$staff->job_title
                    : $staff->full_name.($staff->role_key ? ' - '.str($staff->role_key)->replace('_', ' ')->title()->toString() : ''),
            ])
            ->values();
        $statusOptions = collect(AppointmentStatus::cases())
            ->map(fn (AppointmentStatus $status) => [
                'value' => $status->value,
                'label' => in_array($status->value, ['cancelled', 'no_show'], true) ? 'Reschedule' : $status->label(),
            ])
            ->unique('value')
            ->values();
        $sourceOptions = collect(['admin', 'walk_in', 'whatsapp', 'instagram', 'facebook', 'referral'])
            ->map(fn (string $source) => [
                'value' => $source,
                'label' => $this->formatSourceLabel($source) ?? str($source)->replace('_', ' ')->title()->toString(),
            ])
            ->values();

        return view('app.calendar.index', [
            'title' => 'Calendar',
            'subtitle' => 'Operational board for live daily scheduling.',
            'viewMode' => $viewMode,
            'weekDays' => $weekDays,
            'monthDays' => $monthDays,
            'slots' => $slots,
            'timelineEvents' => $timelineEvents,
            'overflowSummaries' => $overflowSummaries,
            'timelineHeightPx' => count($slots) * self::ROW_HEIGHT_PX,
            'rowHeightPx' => self::ROW_HEIGHT_PX,
            'staffList' => $staffList,
            'picGroups' => $picGroups,
            'staffId' => $staffId,
            'selectedStaff' => $selectedStaff,
            'selectedDate' => $selectedDate,
            'selectedDateLabel' => $selectedDate->format('l, d M Y'),
            'selectedDateIso' => $selectedDate->toDateString(),
            'weekStart' => $weekStart,
            'monthStart' => $monthStart,
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'previousMonth' => $monthStart->copy()->subMonthNoOverflow()->toDateString(),
            'nextMonth' => $monthStart->copy()->addMonthNoOverflow()->toDateString(),
            'currentWeek' => now()->startOfWeek(Carbon::TUESDAY)->toDateString(),
            'currentMonth' => now()->startOfMonth()->toDateString(),
            'daySummary' => $daySummary,
            'serviceOptions' => $serviceOptions,
            'staffOptions' => $staffOptions,
            'statusOptions' => $statusOptions,
            'sourceOptions' => $sourceOptions,
            'canManageAppointments' => auth()->user()?->hasAppPermission('appointments.manage') ?? false,
            'canViewMembershipBalance' => auth()->user()?->hasAppPermission('customers.view') ?? false,
        ]);
    }

    private function resolveSelectedDate(Request $request): Carbon
    {
        $dateInput = trim((string) $request->input('date', ''));
        $anchorInput = trim((string) $request->input('anchor', ''));

        $anchor = $dateInput !== ''
            ? Carbon::parse($dateInput)
            : ($anchorInput !== '' ? Carbon::parse($anchorInput) : now());

        return $anchor->startOfDay();
    }

    private function buildWeekDays(Carbon $weekStart, Collection $groups, Carbon $selectedDate, string $staffId, string $viewMode): Collection
    {
        return collect(range(0, 4))->map(function (int $offset) use ($weekStart, $groups, $selectedDate, $staffId, $viewMode) {
            $date = $weekStart->copy()->addDays($offset);
            $count = $groups->filter(fn ($group) => optional($group->starts_at)?->isSameDay($date))->count();

            return [
                'date' => $date->toDateString(),
                'full_label' => $date->format('l'),
                'display_date' => $date->format('d M'),
                'appointment_count' => $count,
                'is_today' => $date->isToday(),
                'is_selected' => $date->isSameDay($selectedDate),
                'url' => route('app.calendar', array_filter([
                    'view' => $viewMode,
                    'date' => $date->toDateString(),
                    'anchor' => $viewMode === 'month' ? $selectedDate->copy()->startOfMonth()->toDateString() : $weekStart->toDateString(),
                    'staff_id' => $staffId !== '' ? $staffId : null,
                ])),
            ];
        });
    }

    private function buildMonthDays(Carbon $monthStart, Collection $groups, Carbon $selectedDate, string $staffId, string $viewMode): Collection
    {
        $gridStart = $monthStart->copy()->startOfWeek();
        $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek();
        $cursor = $gridStart->copy();
        $days = collect();

        while ($cursor->lte($gridEnd)) {
            $date = $cursor->copy();
            $count = $groups->filter(fn ($group) => optional($group->starts_at)?->isSameDay($date))->count();

            $days->push([
                'date' => $date->toDateString(),
                'day_number' => $date->format('j'),
                'label' => $date->format('D'),
                'appointment_count' => $count,
                'is_selected' => $date->isSameDay($selectedDate),
                'is_today' => $date->isToday(),
                'is_outside_month' => ! $date->isSameMonth($monthStart),
                'is_clickable' => ! in_array($date->dayOfWeek, [Carbon::SUNDAY, Carbon::MONDAY], true),
                'url' => route('app.calendar', array_filter([
                    'view' => $viewMode,
                    'date' => $date->toDateString(),
                    'anchor' => $monthStart->toDateString(),
                    'staff_id' => $staffId !== '' ? $staffId : null,
                ])),
            ]);

            $cursor->addDay();
        }

        return $days;
    }

    private function buildSlots(Carbon $selectedDate): array
    {
        $slots = [];
        $cursor = $selectedDate->copy()->setTime(self::DAY_START_HOUR, 0);
        $end = $selectedDate->copy()->setTime(self::DAY_END_HOUR, 0);

        while ($cursor->lte($end)) {
            $slotTime = $cursor->format('H:i');
            $isClosingMarker = $cursor->equalTo($end);

            $slots[] = [
                'time' => $slotTime,
                'label' => $cursor->format('h:i A'),
                'is_closing_marker' => $isClosingMarker,
                'create_url' => $isClosingMarker
                    ? null
                    : route('app.appointments.index', [
                        'date' => $selectedDate->toDateString(),
                        'slot' => $slotTime,
                    ]),
            ];

            $cursor->addMinutes(self::SLOT_MINUTES);
        }

        return $slots;
    }

    private function mapItemToEvent(AppointmentGroup $group, AppointmentItem $item): ?array
    {
        $startsAt = $item->starts_at;
        $endsAt = $item->ends_at;

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

        $services = $group->items->map(fn ($appointmentItem) => optional($appointmentItem->service)->name)->filter()->unique()->values();
        $staffMembers = $group->items
            ->map(function ($appointmentItem) {
                $staff = $appointmentItem->staff;
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

        $itemServiceName = optional($item->service)->name ?: 'General Service';
        $itemStaffName = $item->staff?->full_name ?: 'Unassigned';
        $itemStaffRole = $item->staff?->job_title ?: $item->staff?->role_key;
        $statusValue = $group->status instanceof AppointmentStatus ? $group->status->value : (string) $group->status;
        $statusStyles = $this->statusVisuals($statusValue);
        $serviceStyles = $this->serviceVisuals($itemServiceName);

        $topMinutes = $dayStart->diffInMinutes($visibleStart);
        $heightMinutes = max(self::SLOT_MINUTES, $visibleStart->diffInMinutes($visibleEnd));
        $durationMinutes = max(self::SLOT_MINUTES, $startsAt->diffInMinutes($endsAt));
        $pixelsPerMinute = self::ROW_HEIGHT_PX / self::SLOT_MINUTES;

        return [
            'id' => (string) $item->id,
            'group_id' => (string) $group->id,
            'customer_id' => $group->customer?->id ? (string) $group->customer->id : null,
            'group_service_count' => $group->items->count(),
            'customer_name' => optional($group->customer)->full_name ?: 'Unknown Customer',
            'customer_phone' => optional($group->customer)->phone ?: 'No phone recorded',
            'membership_label' => $this->membershipLabel($group),
            'membership_type' => optional($group->customer)->membership_type,
            'membership_code' => optional($group->customer)->membership_code,
            'membership_balance' => optional($group->customer)->current_package,
            'service_names' => $services->all(),
            'service_summary' => $itemServiceName,
            'staff_ids' => $item->staff_id ? [(string) $item->staff_id] : [],
            'staff_names' => $staffMembers->pluck('name')->filter()->values()->all(),
            'staff_summary' => $itemStaffName,
            'staff_details' => $staffMembers->map(fn ($staff) => $staff['role'] ? $staff['name'].' - '.$staff['role'] : $staff['name'])->values()->all(),
            'linked_visit_services' => $group->items->map(function ($linkedItem) {
                $serviceName = $linkedItem->service?->name ?: 'Service';
                $staffName = $linkedItem->staff?->full_name ?: 'Unassigned';
                $timeLabel = ($linkedItem->starts_at?->format('h:i A') ?: '-').' - '.($linkedItem->ends_at?->format('h:i A') ?: '-');

                return $serviceName.' | '.$timeLabel.' | '.$staffName;
            })->values()->all(),
            'visit_summary' => $this->summarizeNames($services->all(), 'No service'),
            'start_time' => $startsAt->format('h:i A'),
            'end_time' => $endsAt->format('h:i A'),
            'date_label' => $startsAt->format('d M Y'),
            'status_value' => $statusValue,
            'status_label' => $statusStyles['label'],
            'notes' => $group->notes ?: null,
            'source_value' => (string) ($group->source ?: 'admin'),
            'source' => $this->formatSourceLabel($group->source),
            'manage_url' => route('app.appointments.index', ['date' => $startsAt->toDateString()]),
            'edit_url' => route('app.appointments.update', $group),
            'create_url' => route('app.appointments.index', ['date' => $startsAt->toDateString(), 'slot' => $startsAt->format('H:i')]),
            'reschedule_url' => route('app.appointments.items.reschedule', $item),
            'update_url' => route('app.appointments.update', $group),
            'service_styles' => $serviceStyles,
            'status_styles' => $statusStyles,
            'top_px' => (int) round($topMinutes * $pixelsPerMinute),
            'height_px' => max(60, (int) round($heightMinutes * $pixelsPerMinute) - 8),
            'start_minutes' => $dayStart->diffInMinutes($visibleStart),
            'end_minutes' => $dayStart->diffInMinutes($visibleEnd),
            'duration_minutes' => $durationMinutes,
            'date_iso' => $startsAt->toDateString(),
            'start_24' => $startsAt->format('H:i'),
            'item_role' => $itemStaffRole,
            'editable_items' => $group->items->map(function ($linkedItem) {
                return [
                    'id' => (string) $linkedItem->id,
                    'service_id' => $linkedItem->service_id ? (string) $linkedItem->service_id : null,
                    'staff_id' => $linkedItem->staff_id ? (string) $linkedItem->staff_id : null,
                    'service_name' => $linkedItem->service?->name ?: 'Service',
                    'staff_label' => $linkedItem->staff
                        ? ($linkedItem->staff->job_title
                            ? $linkedItem->staff->full_name.' - '.$linkedItem->staff->job_title
                            : $linkedItem->staff->full_name)
                        : 'Unassigned',
                    'date' => $linkedItem->starts_at?->format('Y-m-d') ?: $group->starts_at?->format('Y-m-d'),
                    'start_time' => $linkedItem->starts_at?->format('H:i') ?: '09:00',
                    'end_time' => $linkedItem->ends_at?->format('H:i') ?: '09:30',
                ];
            })->values()->all(),
        ];
    }

    private function applyTimelineLayout(Collection $events): array
    {
        $sorted = $events->sortBy('start_minutes')->values();
        $clusters = [];
        $currentCluster = [];
        $clusterEnd = null;

        foreach ($sorted as $event) {
            if ($clusterEnd === null || $event['start_minutes'] < $clusterEnd) {
                $currentCluster[] = $event;
                $clusterEnd = $clusterEnd === null ? $event['end_minutes'] : max($clusterEnd, $event['end_minutes']);
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
        $overflowSummaries = collect();

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
            $usesOverflow = $columns > self::MAX_VISIBLE_COLUMNS;
            $visibleColumns = $usesOverflow ? self::MAX_VISIBLE_COLUMNS - 1 : $columns;
            $renderedColumns = $usesOverflow ? self::MAX_VISIBLE_COLUMNS : $columns;

            $hiddenEvents = collect();

            foreach ($cluster as $event) {
                if ($usesOverflow && $event['lane_index'] >= $visibleColumns) {
                    $hiddenEvents->push($event);
                    continue;
                }

                $event['width_pct'] = round(100 / $renderedColumns, 4);
                $event['left_pct'] = round($event['lane_index'] * $event['width_pct'], 4);
                $positioned->push($event);
            }

            if ($usesOverflow && $hiddenEvents->isNotEmpty()) {
                $topPx = $hiddenEvents->min('top_px');
                $bottomPx = $hiddenEvents->max(fn ($event) => $event['top_px'] + $event['height_px']);

                $overflowSummaries->push([
                    'id' => 'overflow-'.md5(json_encode($hiddenEvents->pluck('id')->values()->all())),
                    'count' => $hiddenEvents->count(),
                    'top_px' => $topPx,
                    'height_px' => max(60, $bottomPx - $topPx),
                    'width_pct' => round(100 / $renderedColumns, 4),
                    'left_pct' => round(($renderedColumns - 1) * (100 / $renderedColumns), 4),
                    'items' => $hiddenEvents->map(function ($event) {
                        return [
                            'customer_name' => $event['customer_name'],
                            'service_summary' => $event['service_summary'],
                            'start_time' => $event['start_time'],
                            'end_time' => $event['end_time'],
                            'staff_summary' => $event['staff_summary'],
                            'status_label' => $event['status_label'],
                            'manage_url' => $event['manage_url'],
                        ];
                    })->values()->all(),
                ]);
            }
        }

        return [
            $positioned->sortBy('start_minutes')->values(),
            $overflowSummaries->sortBy('top_px')->values(),
        ];
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
            $status = $group->status instanceof AppointmentStatus ? $group->status->value : (string) $group->status;

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

    private function formatSourceLabel(?string $source): ?string
    {
        if ($source === null || trim((string) $source) === '') {
            return null;
        }

        return match ((string) $source) {
            'walk_in' => 'Walk-in',
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'referral' => 'Referral',
            default => str((string) $source)->replace('_', ' ')->title()->toString(),
        };
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

    private function statusVisuals(string $status): array
    {
        return self::STATUS_STYLES[$status] ?? [
            'label' => str($status)->replace('_', ' ')->title()->toString(),
            'dot' => '#9f8a91',
            'badge_bg' => '#fbf8f9',
            'badge_border' => '#dfd2d7',
            'badge_text' => '#7d666e',
        ];
    }

    private function serviceVisuals(string $serviceName): array
    {
        $normalized = mb_strtolower(trim($serviceName));

        $palette = match (true) {
            str_contains($normalized, 'consult') => self::SERVICE_PALETTES[1],
            str_contains($normalized, 'facial'), str_contains($normalized, 'beauty'), str_contains($normalized, 'aesthetic') => self::SERVICE_PALETTES[0],
            str_contains($normalized, 'nail') => self::SERVICE_PALETTES[3],
            default => self::SERVICE_PALETTES[abs(crc32($normalized)) % count(self::SERVICE_PALETTES)],
        };

        return $palette;
    }
}
