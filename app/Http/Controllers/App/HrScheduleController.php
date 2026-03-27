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

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'department' => trim((string) $request->input('department', '')),
            'status' => trim((string) $request->input('status', 'active')),
        ];

        $weekStart = $request->filled('week')
            ? Carbon::parse((string) $request->input('week'))->startOfWeek()
            : now()->startOfWeek();
        $weekDays = collect(range(0, 6))
            ->map(fn (int $offset) => $weekStart->copy()->addDays($offset))
            ->values();

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
            ->orderByDesc('department = "Human Resources"')
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

        $today = now()->toDateString();
        $todayCards = $scheduleRows->map(function (array $row) use ($today) {
            $todayShift = collect($row['days'])->firstWhere('date', $today);

            return [
                'staff' => $row['staff'],
                'shift' => $todayShift,
            ];
        });

        $coverageByDay = $weekDays->map(function (Carbon $date) use ($scheduleRows) {
            $dayKey = $date->toDateString();
            $working = 0;
            $leave = 0;

            foreach ($scheduleRows as $row) {
                $shift = collect($row['days'])->firstWhere('date', $dayKey);

                if (! $shift) {
                    continue;
                }

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

        $leaveHighlights = $scheduleRows
            ->flatMap(function (array $row) {
                return collect($row['days'])
                    ->where('status', 'leave')
                    ->map(fn (array $shift) => [
                        'staff' => $row['staff'],
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
            'scheduleRows' => $scheduleRows,
            'weekDays' => $weekDays,
            'weekStart' => $weekStart,
            'weekLabel' => $weekStart->format('d M').' - '.$weekStart->copy()->addDays(6)->format('d M Y'),
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'todaySummary' => [
                'total_staff' => $staff->count(),
                'working' => $todayCards->filter(fn (array $entry) => in_array($entry['shift']['status'] ?? null, ['working', 'half_day', 'training'], true))->count(),
                'leave' => $todayCards->filter(fn (array $entry) => ($entry['shift']['status'] ?? null) === 'leave')->count(),
                'off' => $todayCards->filter(fn (array $entry) => in_array($entry['shift']['status'] ?? null, ['off', 'unavailable'], true))->count(),
            ],
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

        $status = 'working';
        $time = '09:00 - 18:00';
        $note = 'Main clinic coverage';

        if (! $staff->is_active) {
            $status = 'unavailable';
            $time = 'Inactive';
            $note = 'Not currently rostered';
        } elseif ($department === 'Human Resources') {
            if ($date->isSunday()) {
                $status = 'off';
                $time = 'Off day';
                $note = 'Weekend rest';
            } elseif ($seed % 11 === 0) {
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
