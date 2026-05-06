<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\AppointmentSlotBlock;
use App\Models\ClinicSetting;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    private const PIC_ORDER = [
        'aqilah' => 1,
        'adila' => 2,
        'amanda' => 3,
        'farhana' => 4,
        'emma' => 5,
        'monica' => 6,
        'sora' => 7,
    ];

    public function index(Request $request)
    {
        $selectedDate = $this->resolveSelectedDate($request);
        $selectedDateLabel = $selectedDate->format('l (j/n/Y)');
        $embedded = $request->boolean('embedded');
        $compact = $request->boolean('compact');

        $items = AppointmentItem::query()
            ->with([
                'group.customer:id,full_name,phone,membership_type,membership_code,current_package',
                'staff:id,full_name,role_key,job_title,department,operational_role',
                'optionSelections',
            ])
            ->whereHas('group', fn ($query) => $query->whereIn('status', $this->activeAppointmentStatuses()))
            ->where('starts_at', '>=', $selectedDate->copy()->startOfDay())
            ->where('starts_at', '<=', $selectedDate->copy()->endOfDay())
            ->orderBy('starts_at')
            ->get();

        $customerVisitStats = AppointmentGroup::query()
            ->selectRaw('customer_id, count(*) as total_visits, min(starts_at) as first_visit_at')
            ->whereIn('customer_id', $items->pluck('group.customer_id')->filter()->unique()->all())
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $activeStaff = Staff::query()
            ->where('is_active', true)
            ->get(['id', 'full_name', 'role_key', 'job_title', 'department', 'operational_role']);
        $activeStaff = Staff::sortForPicSelector($activeStaff);
        $schedule = ClinicSetting::appointmentSchedule();

        $groupedByStaff = $items
            ->groupBy(function (AppointmentItem $item) {
                $staffName = $item->staff?->full_name ?: ($item->staff_name_snapshot ?: 'Unassigned');

                return mb_strtolower($this->formatPicName($staffName));
            })
            ->map(function ($group, $staffKey) use ($customerVisitStats, $selectedDate) {
                $staff = $group->first()?->staff;
                $staffName = $staff?->full_name ?: ($group->first()?->staff_name_snapshot ?: 'Unassigned');
                $rows = $this->buildMergedScheduleRows($group, $customerVisitStats, $selectedDate);

                return [
                    'staff_id' => (string) $staffKey,
                    'staff_name' => $this->formatPicName($staffName),
                    'staff_role' => $staff?->job_title ?: ($staff?->role_key ?: $group->first()?->staff_role_snapshot),
                    'sort_rank' => $this->picSortRank($staff ?: $staffName),
                    'count' => count($rows),
                    'rows' => $rows,
                ];
            });

        $groupedSchedules = $groupedByStaff
            ->sortBy(fn (array $section) => sprintf('%02d-%s', $section['sort_rank'], mb_strtolower($section['staff_name'])))
            ->values()
            ->all();

        $availabilitySections = $activeStaff
            ->groupBy(fn (Staff $staff) => mb_strtolower($this->formatPicName($staff->full_name)))
            ->map(function ($staffGroup) use ($items, $selectedDate, $schedule) {
                $staff = $staffGroup->first();
                $staffIds = $staffGroup->pluck('id')->map(fn ($id) => (string) $id)->all();
                $staffItems = $items
                    ->filter(fn (AppointmentItem $item) => in_array((string) $item->staff_id, $staffIds, true))
                    ->groupBy(fn (AppointmentItem $item) => $item->starts_at?->format('H:i') ?: 'unknown');

                return [
                    'staff_id' => (string) $staffIds[0],
                    'staff_name' => $this->formatPicName($staff->full_name),
                    'staff_role' => $staff->job_title ?: ($staff->role_key ?: null),
                    'sort_rank' => $this->picSortRank($staff),
                    'booking_windows' => $this->bookingWindowCount($selectedDate, $schedule),
                    'rows' => $this->buildAvailabilityRowsForStaff($selectedDate, $staffItems, $schedule, $staffIds),
                ];
            })
            ->sortBy(fn (array $section) => sprintf('%02d-%s', $section['sort_rank'], mb_strtolower($section['staff_name'])))
            ->values()
            ->all();

        $appointmentGroups = AppointmentGroup::query()
            ->whereIn('status', $this->activeAppointmentStatuses())
            ->where('starts_at', '<=', $selectedDate->copy()->endOfDay())
            ->where('ends_at', '>=', $selectedDate->copy()->startOfDay())
            ->get(['id', 'status']);

        $statusCounts = [
            'total' => $appointmentGroups->count(),
            'checked_in' => 0,
            'completed' => 0,
            'reschedule' => 0,
        ];

        foreach ($appointmentGroups as $group) {
            $statusValue = $group->status instanceof \BackedEnum ? $group->status->value : (string) $group->status;

            if ($statusValue === 'checked_in') {
                $statusCounts['checked_in']++;
            } elseif ($statusValue === 'completed') {
                $statusCounts['completed']++;
            } elseif (in_array($statusValue, ['cancelled', 'no_show'], true)) {
                $statusCounts['reschedule']++;
            }
        }

        $categoryCounts = [
            'wellness' => 0,
            'aesthetics' => 0,
            'spa' => 0,
        ];

        foreach ($items as $item) {
            $categoryKey = (string) ($item->service_category_key_snapshot ?: $item->service?->category_key ?: '');
            $categoryKey = match ($categoryKey) {
                'aesthetic' => 'aesthetics',
                'beauty_spa' => 'spa',
                default => $categoryKey,
            };

            if (array_key_exists($categoryKey, $categoryCounts)) {
                $categoryCounts[$categoryKey]++;
            }
        }

        $topSummaryCards = [
            ['label' => 'Date', 'value' => $selectedDate->format('d M'), 'meta' => $selectedDate->format('l')],
            ['label' => 'Grand Total', 'value' => $statusCounts['total'], 'meta' => null],
        ];

        $bottomSummaryCards = [
            ['label' => 'Total Wellness', 'value' => $categoryCounts['wellness'], 'meta' => null],
            ['label' => 'Total Aesthetic', 'value' => $categoryCounts['aesthetics'], 'meta' => null],
            ['label' => 'Total Spa', 'value' => $categoryCounts['spa'], 'meta' => null],
        ];

        return view('app.calendar.index', [
            'title' => 'Calendar',
            'subtitle' => 'Clinic board grouped by PIC for the selected date.',
            'embedded' => $embedded,
            'compact' => $compact,
            'selectedDate' => $selectedDate,
            'selectedDateIso' => $selectedDate->toDateString(),
            'selectedDateLabel' => $selectedDateLabel,
            'previousDate' => $selectedDate->copy()->subDay()->toDateString(),
            'nextDate' => $selectedDate->copy()->addDay()->toDateString(),
            'scheduleSections' => $groupedSchedules,
            'availabilitySections' => $availabilitySections,
            'totalRows' => $items->count(),
            'topSummaryCards' => $topSummaryCards,
            'bottomSummaryCards' => $bottomSummaryCards,
        ]);
    }

    private function resolveSelectedDate(Request $request): Carbon
    {
        $dateInput = trim((string) $request->input('date', ''));

        return $dateInput !== ''
            ? Carbon::parse($dateInput)->startOfDay()
            : now()->startOfDay();
    }

    private function activeAppointmentStatuses(): array
    {
        return [
            AppointmentStatus::Booked->value,
            AppointmentStatus::Confirmed->value,
            AppointmentStatus::CheckedIn->value,
            AppointmentStatus::Completed->value,
        ];
    }

    private function buildMergedScheduleRows($items, $customerVisitStats, Carbon $selectedDate): array
    {
        return $items
            ->sortBy(fn (AppointmentItem $item) => sprintf('%s-%s', $item->starts_at?->format('H:i') ?: '99:99', $item->group?->customer?->full_name ?: ''))
            ->groupBy(function (AppointmentItem $item) {
                $customer = $item->group?->customer;
                $customerKey = $customer?->id
                    ?: mb_strtolower(trim(($customer?->full_name ?: 'Customer').'|'.($customer?->phone ?: '')));

                return implode('|', [
                    $item->starts_at?->format('H:i') ?: 'unknown',
                    $customerKey,
                ]);
            })
            ->values()
            ->map(function ($group) use ($customerVisitStats, $selectedDate) {
                $first = $group->first();
                $customer = $first->group?->customer;
                $customerStats = $customer ? $customerVisitStats->get($customer->id) : null;
                $isNewCustomer = $customerStats
                    ? ((int) ($customerStats->total_visits ?? 0) <= 1 || Carbon::parse($customerStats->first_visit_at)->isSameDay($selectedDate))
                    : false;

                return [
                    'item_id' => $group->pluck('id')->filter()->implode(','),
                    'time' => $first->starts_at?->format('g:i A') ?: '-',
                    'client' => $customer?->full_name ?: 'Customer',
                    'phone' => $customer?->phone ?: '',
                    'date_label' => $first->starts_at?->format('j M Y') ?: $selectedDate->format('j M Y'),
                    'membership' => $this->formatMembershipCell($customer, $isNewCustomer),
                    'treatment' => $group
                        ->map(fn (AppointmentItem $item) => $this->formatTreatmentCell($item))
                        ->filter()
                        ->unique()
                        ->implode(' | '),
                    'pic' => $this->formatPicName($first->displayStaffName()),
                    'remarks' => $group
                        ->map(fn (AppointmentItem $item) => $item->group?->notes)
                        ->filter()
                        ->unique()
                        ->implode(' | '),
                    'manage_url' => route('app.appointments.index', ['date' => $first->starts_at?->format('Y-m-d') ?: $selectedDate->toDateString()]),
                ];
            })
            ->all();
    }

    private function formatPicName(string $name): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $name));

        return match ($normalized) {
            "Dr. Syarifah Munira 'Aaqilah Binti Al Sayed Mohamad" => 'Dr Aqilah',
            'Dr. Amanda Binti Elli' => 'Dr Amanda',
            'Nur Adilla Binti Mohd Ali' => 'Adila',
            'Nur Farhanna Binti Abdul Malek' => 'Farhana',
            default => $normalized,
        };
    }

    private function picSortRank(Staff|string|null $staff): int
    {
        $name = $staff instanceof Staff ? $staff->full_name : (string) $staff;
        $normalized = mb_strtolower($name);

        foreach (self::PIC_ORDER as $needle => $rank) {
            if (str_contains($normalized, $needle)) {
                return $rank;
            }
        }

        return $staff instanceof Staff ? 50 + Staff::appointmentGroupRankForStaff($staff) : 99;
    }

    private function buildAvailabilityRowsForStaff(Carbon $selectedDate, $staffItems, array $schedule, array $staffIds): array
    {
        $rows = [];
        $cursor = $this->timeOnDate($selectedDate, $schedule['start_time']);
        $cutoff = $this->timeOnDate($selectedDate, $schedule['end_time']);
        $slotDuration = (int) $schedule['slot_duration_minutes'];
        $slotStep = (int) $schedule['slot_step_minutes'];
        $capacity = (int) $schedule['boxes_per_slot'];
        $blocks = AppointmentSlotBlock::query()
            ->whereIn('staff_id', $staffIds)
            ->whereDate('slot_date', $selectedDate->toDateString())
            ->get()
            ->groupBy(fn (AppointmentSlotBlock $block) => substr((string) $block->start_time, 0, 5));

        while ($cursor->copy()->addMinutes($slotDuration)->lte($cutoff)) {
            $slotEnd = $cursor->copy()->addMinutes($slotDuration);
            $slotItems = collect($staffItems->get($cursor->format('H:i'), []))->values();
            $slotBlocks = collect($blocks->get($cursor->format('H:i'), []))->values();

            $rows[] = [
                'label' => $cursor->format('g:i A').' - '.$slotEnd->format('g:i A'),
                'boxes' => $this->buildAvailabilityBoxes($slotItems, $slotBlocks, $capacity),
            ];

            $cursor->addMinutes($slotStep);
        }

        return $rows;
    }

    private function buildAvailabilityBoxes($slotItems, $slotBlocks, int $capacity): array
    {
        $occupiedBoxes = $slotItems
            ->groupBy(function (AppointmentItem $item) {
                $customer = $item->group?->customer;

                return $customer?->id
                    ?: mb_strtolower(trim(($customer?->full_name ?: 'Customer').'|'.($customer?->phone ?: '')));
            })
            ->values()
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'type' => 'occupied',
                    'title' => $first->group?->customer?->full_name ?: 'Customer',
                    'body' => $group
                        ->map(fn (AppointmentItem $item) => $this->formatTreatmentCell($item))
                        ->filter()
                        ->unique()
                        ->implode(' | '),
                ];
            })
            ->take($capacity)
            ->values();

        foreach ($slotBlocks as $block) {
            if ($occupiedBoxes->count() >= $capacity) {
                break;
            }

            $occupiedBoxes->push([
                'type' => 'blocked',
                'title' => 'Blocked',
                'body' => $block->reason,
            ]);
        }

        while ($occupiedBoxes->count() < $capacity) {
            $occupiedBoxes->push([
                'type' => 'empty',
                'title' => 'Empty box',
                'body' => 'Available',
            ]);
        }

        return $occupiedBoxes->all();
    }

    private function bookingWindowCount(Carbon $selectedDate, array $schedule): int
    {
        $count = 0;
        $cursor = $this->timeOnDate($selectedDate, $schedule['start_time']);
        $cutoff = $this->timeOnDate($selectedDate, $schedule['end_time']);

        while ($cursor->copy()->addMinutes((int) $schedule['slot_duration_minutes'])->lte($cutoff)) {
            $count++;
            $cursor->addMinutes((int) $schedule['slot_step_minutes']);
        }

        return $count;
    }

    private function timeOnDate(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $date->copy()->setTime($hour, $minute);
    }

    private function formatMembershipCell($customer, bool $isNewCustomer): string
    {
        if (! $customer) {
            return $isNewCustomer ? 'NEW' : 'NONE';
        }

        $membership = $customer->current_package
            ?: ($customer->membership_type ?: $customer->membership_code);

        if ($membership) {
            return $membership;
        }

        return $isNewCustomer ? 'NEW' : 'NONE';
    }

    private function formatTreatmentCell(AppointmentItem $item): string
    {
        $parts = [$this->formatCalendarServiceName($item)];

        $optionLabels = $item->optionSelections
            ->map(fn ($selection) => $selection->option_value_label)
            ->filter()
            ->values()
            ->all();

        if ($optionLabels !== []) {
            $parts[] = implode(' | ', $optionLabels);
        }

        return implode(' | ', array_filter($parts));
    }

    private function formatCalendarServiceName(AppointmentItem $item): string
    {
        $name = trim($item->displayServiceName());
        $categoryKey = (string) ($item->service_category_key_snapshot ?: $item->service?->category_key ?: '');

        if ($categoryKey === 'consultations' && ! str_starts_with(mb_strtolower($name), 'consult ')) {
            return 'Consult '.$name;
        }

        return $name;
    }
}
