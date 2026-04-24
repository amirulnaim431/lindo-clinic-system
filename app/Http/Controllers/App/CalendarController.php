<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\AppointmentItem;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $selectedDate = $this->resolveSelectedDate($request);
        $selectedDateLabel = $selectedDate->format('l (j/n/Y)');

        $items = AppointmentItem::query()
            ->with([
                'group.customer:id,full_name,phone,membership_type,membership_code,current_package',
                'staff:id,full_name,role_key,job_title,department,operational_role',
                'optionSelections',
            ])
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

        $groupedSchedules = $items
            ->groupBy(fn (AppointmentItem $item) => (string) ($item->staff_id ?: 'unassigned'))
            ->map(function ($group, $staffId) use ($customerVisitStats, $selectedDate) {
                $staff = $group->first()?->staff;

                return [
                    'staff_id' => $staffId,
                    'staff_name' => $staff?->full_name ?: ($group->first()?->staff_name_snapshot ?: 'Unassigned'),
                    'staff_role' => $staff?->job_title ?: ($staff?->role_key ?: $group->first()?->staff_role_snapshot),
                    'sort_rank' => $staff ? Staff::appointmentGroupRankForStaff($staff) : 99,
                    'count' => $group->count(),
                    'rows' => $group->sortBy('starts_at')->values()->map(function (AppointmentItem $item) use ($customerVisitStats, $selectedDate) {
                        $customer = $item->group?->customer;
                        $customerStats = $customer ? $customerVisitStats->get($customer->id) : null;
                        $isNewCustomer = $customerStats
                            ? ((int) ($customerStats->total_visits ?? 0) <= 1 || Carbon::parse($customerStats->first_visit_at)->isSameDay($selectedDate))
                            : false;

                        return [
                            'item_id' => (string) $item->id,
                            'time' => $item->starts_at?->format('g:i A') ?: '-',
                            'client' => $customer?->full_name ?: 'Customer',
                            'membership' => $this->formatMembershipCell($customer, $isNewCustomer),
                            'treatment' => $this->formatTreatmentCell($item),
                            'pic' => $item->displayStaffName(),
                            'remarks' => $item->group?->notes ?: '',
                            'manage_url' => route('app.appointments.index', ['date' => $item->starts_at?->format('Y-m-d') ?: $selectedDate->toDateString()]),
                        ];
                    })->all(),
                ];
            })
            ->sortBy(fn (array $section) => sprintf('%02d-%s', $section['sort_rank'], mb_strtolower($section['staff_name'])))
            ->values()
            ->all();

        return view('app.calendar.index', [
            'title' => 'Daily Client Schedule',
            'subtitle' => 'Clinic board grouped by PIC for the selected date.',
            'selectedDate' => $selectedDate,
            'selectedDateIso' => $selectedDate->toDateString(),
            'selectedDateLabel' => $selectedDateLabel,
            'previousDate' => $selectedDate->copy()->subDay()->toDateString(),
            'nextDate' => $selectedDate->copy()->addDay()->toDateString(),
            'scheduleSections' => $groupedSchedules,
            'totalRows' => $items->count(),
        ]);
    }

    private function resolveSelectedDate(Request $request): Carbon
    {
        $dateInput = trim((string) $request->input('date', ''));

        return $dateInput !== ''
            ? Carbon::parse($dateInput)->startOfDay()
            : now()->startOfDay();
    }

    private function formatMembershipCell($customer, bool $isNewCustomer): string
    {
        if (! $customer) {
            return $isNewCustomer ? 'NEW' : 'NONE';
        }

        $parts = array_filter([
            $customer->membership_type,
            $customer->membership_code,
            $customer->current_package,
        ]);

        if ($parts !== []) {
            return implode(' | ', $parts);
        }

        return $isNewCustomer ? 'NEW' : 'NONE';
    }

    private function formatTreatmentCell(AppointmentItem $item): string
    {
        $parts = [$item->displayServiceName()];

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
}
