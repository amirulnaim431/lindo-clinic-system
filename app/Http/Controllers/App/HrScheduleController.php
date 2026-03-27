<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

class HrScheduleController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->canAccessHrSchedule(), 403);

        $viewMode = $request->input('view') === 'week' ? 'week' : 'month';
        $selectedDate = $request->filled('date')
            ? Carbon::parse((string) $request->input('date'))->startOfDay()
            : now()->startOfDay();

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'department' => trim((string) $request->input('department', '')),
            'status' => trim((string) $request->input('status', 'active')),
        ];

        $weekStart = $selectedDate->copy()->startOfWeek(Carbon::TUESDAY);
        $weekDays = collect(range(0, 4))
            ->map(fn (int $offset) => $weekStart->copy()->addDays($offset))
            ->values();
        $monthStart = $selectedDate->copy()->startOfMonth();
        $monthEnd = $selectedDate->copy()->endOfMonth();
        $monthDays = collect();
        $monthCursor = $monthStart->copy();

        while ($monthCursor->month === $monthStart->month) {
            if (in_array($monthCursor->dayOfWeek, [Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $monthDays->push($monthCursor->copy());
            }

            $monthCursor->addDay();
        }

        $monthGridStart = $monthStart->copy()->startOfWeek(Carbon::TUESDAY);
        $monthGridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);
        $monthGridDays = collect();
        $gridCursor = $monthGridStart->copy();

        while ($gridCursor->lte($monthGridEnd)) {
            if (in_array($gridCursor->dayOfWeek, [Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $monthGridDays->push($gridCursor->copy());
            }

            $gridCursor->addDay();
        }

        $staff = Staff::query()
            ->with(['services' => fn ($query) => $query->orderBy('name'), 'user:id,name,email'])
            ->when($filters['search'] !== '', function ($query) use ($filters) {
                $query->where(function ($nested) use ($filters) {
                    $nested
                        ->where('full_name', 'like', '%'.$filters['search'].'%')
                        ->orWhere('job_title', 'like', '%'.$filters['search'].'%')
                        ->orWhere('department', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['department'] !== '', fn ($query) => $query->where('department', $filters['department']))
            ->when($filters['status'] === 'active', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByRaw("CASE WHEN department = 'Human Resources' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('is_active')
            ->orderBy('department')
            ->orderBy('full_name')
            ->get();

        $scheduleRows = $staff->map(function (Staff $member) use ($weekDays) {
            $days = $weekDays->map(fn (Carbon $date) => $this->buildMockShift($member, $date))->values();

            return [
                'staff' => $member,
                'days' => $days,
                'working_days' => $days->whereIn('status', ['working', 'half_day', 'training'])->count(),
                'leave_days' => $days->where('status', 'leave')->count(),
            ];
        })->values();

        $monthScheduleRows = $staff->map(function (Staff $member) use ($monthDays) {
            $days = $monthDays->map(fn (Carbon $date) => $this->buildMockShift($member, $date))->values();

            return [
                'staff' => $member,
                'leave_days' => $days->where('status', 'leave')->count(),
                'working_days' => $days->whereIn('status', ['working', 'half_day', 'training'])->count(),
                'days' => $days,
            ];
        })->values();

        $selectedDayIso = $selectedDate->toDateString();
        $selectedDateCards = $staff->map(function (Staff $member) use ($selectedDayIso) {
            $selectedShift = $this->buildMockShift($member, Carbon::parse($selectedDayIso));

            return [
                'staff' => $member,
                'shift' => $selectedShift,
            ];
        });

        $workingTodayDetails = $selectedDateCards
            ->filter(fn (array $entry) => in_array($entry['shift']['status'] ?? null, ['working', 'half_day', 'training'], true))
            ->map(fn (array $entry) => [
                'staff_name' => $entry['staff']->full_name,
                'job_title' => $entry['staff']->job_title ?: 'No title set',
                'department' => $entry['staff']->department ?: 'No department',
                'role' => $entry['staff']->operational_role_label,
                'detail_primary' => $entry['shift']['time'] ?? '-',
                'detail_secondary' => $entry['shift']['note'] ?? '-',
            ])
            ->values();

        $leaveTodayDetails = $selectedDateCards
            ->filter(fn (array $entry) => ($entry['shift']['status'] ?? null) === 'leave')
            ->map(fn (array $entry) => [
                'staff_name' => $entry['staff']->full_name,
                'job_title' => $entry['staff']->job_title ?: 'No title set',
                'department' => $entry['staff']->department ?: 'No department',
                'role' => $entry['staff']->operational_role_label,
                'detail_primary' => $entry['shift']['label'] ?? 'On Leave',
                'detail_secondary' => $entry['shift']['note'] ?? '-',
            ])
            ->values();

        $coverageSourceDays = $viewMode === 'week'
            ? $weekDays
            : $monthDays
                ->filter(fn (Carbon $date) => $date->greaterThanOrEqualTo($selectedDate))
                ->take(5)
                ->values();

        if ($viewMode === 'month' && $coverageSourceDays->count() < 5) {
            $needed = 5 - $coverageSourceDays->count();
            $coverageSourceDays = $coverageSourceDays
                ->concat($monthDays->take($needed))
                ->unique(fn (Carbon $date) => $date->toDateString())
                ->values();
        }

        $coverageByDay = $coverageSourceDays->map(function (Carbon $date) use ($staff) {
            $dayKey = $date->toDateString();
            $working = 0;
            $leave = 0;

            foreach ($staff as $member) {
                $shift = $this->buildMockShift($member, $date);

                if ($shift['status'] === 'leave') {
                    $leave++;
                    continue;
                }

                if (in_array($shift['status'], ['working', 'half_day', 'training'], true)) {
                    $working++;
                }
            }

            return [
                'date' => $dayKey,
                'label' => $date->format('D'),
                'display' => $date->format('d M'),
                'working' => $working,
                'leave' => $leave,
            ];
        })->values();

        $monthCalendarDays = $monthGridDays->map(function (Carbon $date) use ($staff, $monthStart, $selectedDate, $filters) {
            $working = 0;
            $leave = 0;
            $training = 0;
            $leaveNames = [];

            foreach ($staff as $member) {
                $shift = $this->buildMockShift($member, $date);

                if ($shift['status'] === 'leave') {
                    $leave++;
                    $leaveNames[] = $member->full_name;
                    continue;
                }

                if ($shift['status'] === 'training') {
                    $training++;
                }

                if (in_array($shift['status'], ['working', 'half_day', 'training'], true)) {
                    $working++;
                }
            }

            return [
                'date' => $date->toDateString(),
                'day_number' => $date->format('j'),
                'label' => $date->format('D'),
                'working' => $working,
                'leave' => $leave,
                'training' => $training,
                'leave_names' => collect($leaveNames)->take(3)->values(),
                'is_selected' => $date->isSameDay($selectedDate),
                'is_outside_month' => ! $date->isSameMonth($monthStart),
                'tone' => $leave > 0 ? 'leave' : ($training > 0 ? 'training' : 'working'),
                'url' => route('app.hr.schedule', array_filter([
                    'view' => 'month',
                    'date' => $date->toDateString(),
                    'search' => $filters['search'] !== '' ? $filters['search'] : null,
                    'department' => $filters['department'] !== '' ? $filters['department'] : null,
                    'status' => $filters['status'] !== 'active' ? $filters['status'] : null,
                ])),
            ];
        })->values();

        $activeDays = $viewMode === 'week' ? $weekDays : $monthDays;

        $leaveHighlights = $staff
            ->flatMap(function (Staff $member) use ($activeDays) {
                return $activeDays
                    ->map(fn (Carbon $date) => $this->buildMockShift($member, $date))
                    ->where('status', 'leave')
                    ->map(fn (array $shift) => [
                        'staff' => $member,
                        'shift' => $shift,
                    ]);
            })
            ->sortBy(fn (array $entry) => $entry['shift']['date'])
            ->take(6)
            ->values();

        $hrOwners = $staff->filter(function (Staff $member) {
            return $member->department === 'Human Resources'
                || collect($member->access_permissions ?? [])->contains('hr.schedule');
        })->values();

        $hrOwnerDetails = $hrOwners
            ->map(fn (Staff $member) => [
                'staff_name' => $member->full_name,
                'job_title' => $member->job_title ?: 'No title set',
                'department' => $member->department ?: 'No department',
                'role' => $member->operational_role_label,
                'detail_primary' => $member->user?->email ?: 'No linked login email',
                'detail_secondary' => $member->department === 'Human Resources'
                    ? 'Department-based HR access'
                    : 'Explicit HR schedule permission',
            ])
            ->values();

        $selectedDateLabel = $selectedDate->format('d M Y');
        $hrSummaryCards = collect([
            [
                'key' => 'working_today',
                'label' => 'Working Today',
                'value' => $workingTodayDetails->count(),
                'meta' => 'People scheduled for clinic or office coverage',
                'summary' => $selectedDateLabel,
                'details' => $workingTodayDetails,
            ],
            [
                'key' => 'leave_today',
                'label' => 'On Leave Today',
                'value' => $leaveTodayDetails->count(),
                'meta' => 'Easy visibility for leave and unavailable blocks',
                'summary' => $selectedDateLabel,
                'details' => $leaveTodayDetails,
            ],
            [
                'key' => 'hr_owners',
                'label' => 'HR Owners',
                'value' => $hrOwnerDetails->count(),
                'meta' => 'HR/admin users who can access this module now',
                'summary' => 'Access controllers',
                'details' => $hrOwnerDetails,
            ],
        ])->values();

        return view('app.hr.schedule', [
            'title' => 'Staff Schedule',
            'subtitle' => 'HR planning board for weekly staffing, leave visibility, and roster control.',
            'filters' => $filters,
            'departmentOptions' => Staff::query()
                ->whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department'),
            'viewMode' => $viewMode,
            'selectedDate' => $selectedDate,
            'selectedDateIso' => $selectedDate->toDateString(),
            'selectedDateLabel' => $selectedDateLabel,
            'scheduleRows' => $scheduleRows,
            'monthScheduleRows' => $monthScheduleRows,
            'monthCalendarDays' => $monthCalendarDays,
            'weekDays' => $weekDays,
            'monthDays' => $monthDays,
            'weekStart' => $weekStart,
            'weekLabel' => $weekStart->format('d M').' - '.$weekStart->copy()->addDays(4)->format('d M Y'),
            'monthLabel' => $monthStart->format('F Y'),
            'previousDate' => $viewMode === 'week'
                ? $weekStart->copy()->subWeek()->toDateString()
                : $monthStart->copy()->subMonthNoOverflow()->toDateString(),
            'nextDate' => $viewMode === 'week'
                ? $weekStart->copy()->addWeek()->toDateString()
                : $monthStart->copy()->addMonthNoOverflow()->toDateString(),
            'todaySummary' => [
                'total_staff' => $staff->count(),
                'working' => $selectedDateCards->filter(fn (array $entry) => in_array($entry['shift']['status'] ?? null, ['working', 'half_day', 'training'], true))->count(),
                'leave' => $selectedDateCards->filter(fn (array $entry) => ($entry['shift']['status'] ?? null) === 'leave')->count(),
                'off' => $selectedDateCards->filter(fn (array $entry) => in_array($entry['shift']['status'] ?? null, ['off', 'unavailable'], true))->count(),
            ],
            'hrSummaryCards' => $hrSummaryCards,
            'coverageByDay' => $coverageByDay,
            'leaveHighlights' => $leaveHighlights,
            'hrOwners' => $hrOwners,
        ]);
    }

    private function buildMockShift(Staff $staff, Carbon $date): array
    {
        $role = (string) ($staff->operational_role ?? $staff->role_key ?? '');
        $department = (string) ($staff->department ?? '');
        $seed = abs(crc32($staff->id.'|'.$date->toDateString()));
        $isWeekend = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true);
        $isClinicClosed = in_array($date->dayOfWeek, [Carbon::SUNDAY, Carbon::MONDAY], true);

        $status = 'working';
        $time = '09:00 - 18:00';
        $note = 'Main clinic coverage';

        if (! $staff->is_active) {
            $status = 'unavailable';
            $time = 'Inactive';
            $note = 'Not currently rostered';
        } elseif ($isClinicClosed) {
            $status = 'off';
            $time = 'Clinic closed';
            $note = 'Sunday and Monday are closed clinic days';
        } elseif ($department === 'Human Resources') {
            if ($seed % 11 === 0) {
                $status = 'leave';
                $time = 'On leave';
                $note = 'Annual leave';
            } elseif ($date->isSaturday()) {
                $status = 'half_day';
                $time = '10:00 - 14:00';
                $note = 'Light office support';
            } else {
                $status = 'working';
                $time = '09:00 - 17:30';
                $note = 'HR desk and roster planning';
            }
        } elseif ($isWeekend && ! in_array($role, ['doctor', 'beautician', 'management', 'admin'], true)) {
            $status = 'off';
            $time = 'Off day';
            $note = 'No clinic shift assigned';
        } elseif ($seed % 13 === 0) {
            $status = 'leave';
            $time = 'On leave';
            $note = 'Approved leave';
        } elseif ($seed % 9 === 0) {
            $status = 'training';
            $time = '11:00 - 16:00';
            $note = 'Training / workshop';
        } elseif ($seed % 5 === 0) {
            $status = 'half_day';
            $time = '09:00 - 13:00';
            $note = 'Half-day coverage';
        } elseif (in_array($role, ['doctor', 'management'], true)) {
            $status = 'working';
            $time = '10:00 - 18:00';
            $note = 'Prime consultation hours';
        } elseif ($role === 'beautician') {
            $status = 'working';
            $time = '11:00 - 19:00';
            $note = 'Treatment floor coverage';
        }

        return [
            'date' => $date->toDateString(),
            'status' => $status,
            'label' => $this->shiftLabel($status),
            'time' => $time,
            'note' => $note,
            'tone' => $this->shiftTone($status),
        ];
    }

    private function shiftLabel(string $status): string
    {
        return match ($status) {
            'leave' => 'On Leave',
            'off' => 'Off Day',
            'training' => 'Training',
            'half_day' => 'Half Day',
            'unavailable' => 'Inactive',
            default => 'Working',
        };
    }

    private function shiftTone(string $status): string
    {
        return match ($status) {
            'leave' => 'leave',
            'off' => 'off',
            'training' => 'training',
            'half_day' => 'half',
            'unavailable' => 'neutral',
            default => 'working',
        };
    }
}
