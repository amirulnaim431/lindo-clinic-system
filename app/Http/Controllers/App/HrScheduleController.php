<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffLeave;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

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
            if ($this->isClinicOpenDay($monthCursor)) {
                $monthDays->push($monthCursor->copy());
            }

            $monthCursor->addDay();
        }

        $monthGridStart = $monthStart->copy()->startOfWeek(Carbon::TUESDAY);
        $monthGridEnd = $monthEnd->copy()->endOfWeek(Carbon::SATURDAY);
        $monthGridDays = collect();
        $gridCursor = $monthGridStart->copy();

        while ($gridCursor->lte($monthGridEnd)) {
            if ($this->isClinicOpenDay($gridCursor)) {
                $monthGridDays->push($gridCursor->copy());
            }

            $gridCursor->addDay();
        }

        $rangeStart = $viewMode === 'week' ? $weekStart->copy() : $monthGridStart->copy();
        $rangeEnd = $viewMode === 'week' ? $weekStart->copy()->addDays(4) : $monthGridEnd->copy();

        $staff = Staff::query()
            ->with([
                'services' => fn ($query) => $query->orderBy('name'),
                'user:id,name,email',
                'leaves' => fn ($query) => $query
                    ->whereDate('start_date', '<=', $rangeEnd->toDateString())
                    ->whereDate('end_date', '>=', $rangeStart->toDateString())
                    ->with(['requestedBy:id,name', 'reviewedBy:id,name'])
                    ->orderBy('start_date')
                    ->orderBy('created_at'),
            ])
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

        $requestableStaff = $staff
            ->filter(fn (Staff $member) => $member->is_active)
            ->map(fn (Staff $member) => [
                'id' => $member->id,
                'name' => $member->full_name,
                'job_title' => $member->job_title ?: 'No title set',
                'department' => $member->department ?: 'No department',
            ])
            ->values();

        $scheduleRows = $staff->map(function (Staff $member) use ($weekDays) {
            $days = $weekDays->map(function (Carbon $date) use ($member) {
                $shift = $this->buildShift($member, $date);
                $shift['modal'] = [
                    'scope' => 'staff_day',
                    'title' => $member->full_name.' | '.$date->format('D, d M Y'),
                    'subtitle' => $shift['label'].' | '.$shift['time'],
                    'summary' => $shift['note'],
                    'records' => $shift['leave_records'],
                    'staff_id' => $member->id,
                    'staff_name' => $member->full_name,
                    'request_defaults' => [
                        'staff_id' => $member->id,
                        'start_date' => $date->toDateString(),
                        'end_date' => $date->toDateString(),
                        'reason' => '',
                    ],
                ];

                return $shift;
            })->values();

            return [
                'staff' => $member,
                'days' => $days,
                'working_days' => $days->whereIn('status', ['working', 'half_day', 'training'])->count(),
                'leave_days' => $days->whereIn('status', ['leave', 'pending_leave'])->count(),
            ];
        })->values();

        $selectedDateCards = $staff->map(function (Staff $member) use ($selectedDate) {
            return [
                'staff' => $member,
                'shift' => $this->buildShift($member, $selectedDate),
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
            ->filter(fn (array $entry) => in_array($entry['shift']['status'] ?? null, ['leave', 'pending_leave'], true))
            ->map(function (array $entry) {
                $firstRecord = collect($entry['shift']['leave_records'] ?? [])->first();

                return [
                    'staff_name' => $entry['staff']->full_name,
                    'job_title' => $entry['staff']->job_title ?: 'No title set',
                    'department' => $entry['staff']->department ?: 'No department',
                    'role' => $entry['staff']->operational_role_label,
                    'detail_primary' => $firstRecord['status_label'] ?? ($entry['shift']['label'] ?? 'Leave'),
                    'detail_secondary' => $firstRecord['reason'] ?? ($entry['shift']['note'] ?? '-'),
                ];
            })
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
            $working = 0;
            $leave = 0;
            $pending = 0;

            foreach ($staff as $member) {
                $shift = $this->buildShift($member, $date);

                if ($shift['status'] === 'leave') {
                    $leave++;
                    continue;
                }

                if ($shift['status'] === 'pending_leave') {
                    $pending++;
                    continue;
                }

                if (in_array($shift['status'], ['working', 'half_day', 'training'], true)) {
                    $working++;
                }
            }

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'display' => $date->format('d M'),
                'working' => $working,
                'leave' => $leave,
                'pending' => $pending,
            ];
        })->values();

        $monthCalendarDays = $monthGridDays->map(function (Carbon $date) use ($staff, $monthStart, $selectedDate) {
            $working = 0;
            $leave = 0;
            $pending = 0;
            $training = 0;
            $leaveNames = [];
            $records = collect();

            foreach ($staff as $member) {
                $shift = $this->buildShift($member, $date);

                if ($shift['status'] === 'leave') {
                    $leave++;
                    $leaveNames[] = $member->full_name;
                } elseif ($shift['status'] === 'pending_leave') {
                    $pending++;
                    $leaveNames[] = $member->full_name;
                } elseif ($shift['status'] === 'training') {
                    $training++;
                }

                if (in_array($shift['status'], ['working', 'half_day', 'training'], true)) {
                    $working++;
                }

                foreach ($shift['leave_records'] as $record) {
                    $records->push($record);
                }
            }

            $records = $records
                ->sortBy([
                    ['status_sort', 'asc'],
                    ['staff_name', 'asc'],
                ])
                ->values()
                ->map(function (array $record) {
                    unset($record['status_sort']);

                    return $record;
                })
                ->values();

            return [
                'date' => $date->toDateString(),
                'day_number' => $date->format('j'),
                'label' => $date->format('D'),
                'working' => $working,
                'leave' => $leave,
                'pending' => $pending,
                'training' => $training,
                'leave_names' => collect($leaveNames)->unique()->take(3)->values(),
                'is_selected' => $date->isSameDay($selectedDate),
                'is_outside_month' => ! $date->isSameMonth($monthStart),
                'tone' => $leave > 0 ? 'leave' : ($pending > 0 ? 'pending' : ($training > 0 ? 'training' : 'working')),
                'modal' => [
                    'scope' => 'day',
                    'title' => 'Leave detail | '.$date->format('D, d M Y'),
                    'subtitle' => $leave.' approved | '.$pending.' pending',
                    'summary' => $working.' working | '.$training.' training',
                    'records' => $records,
                    'request_defaults' => [
                        'staff_id' => '',
                        'start_date' => $date->toDateString(),
                        'end_date' => $date->toDateString(),
                        'reason' => '',
                    ],
                ],
            ];
        })->values();

        $activeDays = $viewMode === 'week' ? $weekDays : $monthDays;

        $leaveHighlights = $staff
            ->flatMap(function (Staff $member) use ($activeDays) {
                return $member->leaves
                    ->filter(function (StaffLeave $leave) use ($activeDays) {
                        return $activeDays->contains(function (Carbon $date) use ($leave) {
                            return $this->leaveMatchesDate($leave, $date);
                        });
                    })
                    ->map(fn (StaffLeave $leave) => [
                        'staff' => $member,
                        'leave' => $leave,
                    ]);
            })
            ->sortBy([
                [fn (array $entry) => $entry['leave']->start_date?->toDateString() ?? '9999-12-31', 'asc'],
                [fn (array $entry) => $this->statusSortValue((string) $entry['leave']->status), 'asc'],
            ])
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
                'meta' => 'Approved and pending leave visibility for today',
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
                'pending' => $selectedDateCards->filter(fn (array $entry) => ($entry['shift']['status'] ?? null) === 'pending_leave')->count(),
                'off' => $selectedDateCards->filter(fn (array $entry) => in_array($entry['shift']['status'] ?? null, ['off', 'unavailable'], true))->count(),
            ],
            'hrSummaryCards' => $hrSummaryCards,
            'coverageByDay' => $coverageByDay,
            'leaveHighlights' => $leaveHighlights,
            'hrOwners' => $hrOwners,
            'requestableStaff' => $requestableStaff,
            'leaveStatusOptions' => StaffLeave::statusOptions(),
        ]);
    }

    public function storeLeave(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->canAccessHrSchedule(), 403);

        $data = $request->validate([
            'staff_id' => ['required', 'string', Rule::exists('staff', 'id')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        StaffLeave::query()->create([
            'staff_id' => $data['staff_id'],
            'requested_by_user_id' => $request->user()?->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => trim($data['reason']),
            'status' => StaffLeave::STATUS_PENDING,
        ]);

        return redirect()->back()->with('success', 'Leave request submitted.');
    }

    public function reviewLeave(Request $request, StaffLeave $staffLeave): RedirectResponse
    {
        abort_unless($request->user()?->canAccessHrSchedule(), 403);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in([StaffLeave::STATUS_APPROVED, StaffLeave::STATUS_REJECTED])],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $staffLeave->update([
            'status' => $data['status'],
            'review_notes' => filled($data['review_notes']) ? trim($data['review_notes']) : null,
            'reviewed_by_user_id' => $request->user()?->id,
        ]);

        return redirect()->back()->with('success', 'Leave request updated.');
    }

    private function buildShift(Staff $staff, Carbon $date): array
    {
        $role = (string) ($staff->operational_role ?? $staff->role_key ?? '');
        $department = (string) ($staff->department ?? '');
        $seed = abs(crc32($staff->id.'|'.$date->toDateString()));
        $isWeekend = in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true);
        $isClinicClosed = ! $this->isClinicOpenDay($date);
        $matchingLeaves = $this->leaveRecordsForDate($staff, $date);
        $approvedLeave = $matchingLeaves->first(fn (StaffLeave $leave) => $leave->status === StaffLeave::STATUS_APPROVED);
        $pendingLeave = $matchingLeaves->first(fn (StaffLeave $leave) => $leave->status === StaffLeave::STATUS_PENDING);

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
        } elseif ($approvedLeave) {
            $status = 'leave';
            $time = 'On leave';
            $note = $approvedLeave->reason ?: 'Approved leave';
        } elseif ($pendingLeave) {
            $status = 'pending_leave';
            $time = 'Pending leave';
            $note = $pendingLeave->reason ?: 'Waiting for approval';
        } elseif ($department === 'Human Resources') {
            if ($date->isSaturday()) {
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
            'leave_records' => $this->serializeLeaveRecords($matchingLeaves, $staff),
        ];
    }

    private function isClinicOpenDay(Carbon $date): bool
    {
        return in_array($date->dayOfWeek, [Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY, Carbon::SATURDAY], true);
    }

    private function leaveRecordsForDate(Staff $staff, Carbon $date): Collection
    {
        return $staff->leaves
            ->filter(fn (StaffLeave $leave) => $this->leaveMatchesDate($leave, $date))
            ->sortBy([
                [fn (StaffLeave $leave) => $this->statusSortValue((string) $leave->status), 'asc'],
                [fn (StaffLeave $leave) => $leave->start_date?->toDateString() ?? '9999-12-31', 'asc'],
            ])
            ->values();
    }

    private function leaveMatchesDate(StaffLeave $leave, Carbon $date): bool
    {
        return $leave->start_date !== null
            && $leave->end_date !== null
            && $leave->start_date->lte($date)
            && $leave->end_date->gte($date);
    }

    private function serializeLeaveRecords(Collection $leaves, ?Staff $staff = null): array
    {
        return $leaves
            ->map(function (StaffLeave $leave) use ($staff) {
                $leaveStaff = $staff ?? $leave->staff;

                return [
                    'id' => $leave->id,
                    'staff_id' => $leaveStaff?->id,
                    'staff_name' => $leaveStaff?->full_name ?? 'Unknown staff',
                    'job_title' => $leaveStaff?->job_title ?: 'No title set',
                    'department' => $leaveStaff?->department ?: 'No department',
                    'reason' => $leave->reason,
                    'status' => $leave->status,
                    'status_label' => StaffLeave::statusOptions()[$leave->status] ?? str($leave->status)->title()->toString(),
                    'status_sort' => $this->statusSortValue((string) $leave->status),
                    'date_range' => $leave->start_date && $leave->end_date
                        ? $leave->start_date->format('d M Y').' - '.$leave->end_date->format('d M Y')
                        : '-',
                    'review_notes' => $leave->review_notes ?: '',
                    'requested_by' => $leave->requestedBy?->name ?: 'System',
                    'reviewed_by' => $leave->reviewedBy?->name ?: '',
                    'created_at' => optional($leave->created_at)?->format('d M Y, H:i') ?: '',
                ];
            })
            ->values()
            ->all();
    }

    private function statusSortValue(string $status): int
    {
        return match ($status) {
            StaffLeave::STATUS_PENDING => 0,
            StaffLeave::STATUS_APPROVED => 1,
            StaffLeave::STATUS_REJECTED => 2,
            default => 3,
        };
    }

    private function shiftLabel(string $status): string
    {
        return match ($status) {
            'leave' => 'On Leave',
            'pending_leave' => 'Pending Leave',
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
            'pending_leave' => 'pending',
            'off' => 'off',
            'training' => 'training',
            'half_day' => 'half',
            'unavailable' => 'neutral',
            default => 'working',
        };
    }
}
