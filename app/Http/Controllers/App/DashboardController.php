<?php

namespace App\Http\Controllers\App;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AppointmentGroup;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date') ?: now()->format('Y-m-d');
        $period = $request->input('period', 'day');
        $staffId = $request->input('staff_id');
        $anchorDate = Carbon::parse($date);

        [$periodStart, $periodEnd, $periodLabel] = $this->resolvePeriodWindow($anchorDate, $period);

        $staffList = $this->buildPicList(
            Staff::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'role_key', 'job_title', 'operational_role'])
        );

        $baseQuery = AppointmentGroup::query()
            ->with([
                'customer',
                'items.staff',
                'items.service',
            ])
            ->whereBetween('starts_at', [$periodStart, $periodEnd]);

        if (! empty($staffId)) {
            $baseQuery->whereHas('items', function ($query) use ($staffId) {
                $query->where('staff_id', $staffId);
            });
        }

        $reportGroups = (clone $baseQuery)
            ->orderBy('starts_at')
            ->get();

        $appointments = $reportGroups->take(50)->values();
        $statusCases = AppointmentStatus::cases();
        $customerFirstVisits = $this->firstVisitLookup($reportGroups);

        $statusBreakdown = [];
        foreach ($statusCases as $case) {
            $statusBreakdown[$case->value] = $reportGroups->filter(function ($group) use ($case) {
                $statusValue = $group->status instanceof AppointmentStatus ? $group->status->value : (string) $group->status;
                return $statusValue === $case->value;
            })->count();
        }

        $customerSegments = $this->buildCustomerSegments($reportGroups, $customerFirstVisits, $periodStart, $periodEnd);
        $serviceFocus = $this->buildServiceFocus($reportGroups);
        $sourceBreakdown = $this->buildSourceBreakdown($reportGroups);
        $staffReview = $this->buildStaffReview($reportGroups);
        $reportRows = $this->buildReportRows($reportGroups, $customerFirstVisits, $periodStart, $periodEnd);
        $membershipSummary = $this->buildMembershipSummary($reportGroups);
        $revenueBreakdown = $this->buildRevenueBreakdown($reportGroups);

        $kpi = [
            'total' => $reportGroups->count(),
            'by_status' => $statusBreakdown,
            'total_sales' => $reportRows->sum('sales_amount'),
            'new_customers' => $customerSegments['new'],
            'existing_customers' => $customerSegments['existing'],
        ];

        if ($request->input('export') === 'csv') {
            return $this->streamCsv($reportRows, $periodLabel);
        }

        return view('app.dashboard', [
            'date' => $date,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'staffId' => $staffId,
            'staffList' => $staffList,
            'appointments' => $appointments,
            'kpi' => $kpi,
            'statusCases' => $statusCases,
            'serviceFocus' => $serviceFocus,
            'sourceBreakdown' => $sourceBreakdown,
            'staffReview' => $staffReview,
            'reportRows' => $reportRows,
            'membershipSummary' => $membershipSummary,
            'revenueBreakdown' => $revenueBreakdown,
        ]);
    }

    protected function resolvePeriodWindow(Carbon $anchorDate, string $period): array
    {
        return match ($period) {
            'week' => [
                $anchorDate->copy()->startOfWeek(),
                $anchorDate->copy()->endOfWeek(),
                $this->formatRangeLabel($anchorDate->copy()->startOfWeek(), $anchorDate->copy()->endOfWeek()),
            ],
            'month' => [
                $anchorDate->copy()->startOfMonth(),
                $anchorDate->copy()->endOfMonth(),
                $this->formatRangeLabel($anchorDate->copy()->startOfMonth(), $anchorDate->copy()->endOfMonth()),
            ],
            'year' => [
                $anchorDate->copy()->startOfYear(),
                $anchorDate->copy()->endOfYear(),
                $this->formatRangeLabel($anchorDate->copy()->startOfYear(), $anchorDate->copy()->endOfYear()),
            ],
            default => [
                $anchorDate->copy()->startOfDay(),
                $anchorDate->copy()->endOfDay(),
                $anchorDate->format('d M Y'),
            ],
        };
    }

    private function firstVisitLookup($groups)
    {
        $customerIds = $groups->pluck('customer_id')->filter()->unique()->values();

        if ($customerIds->isEmpty()) {
            return collect();
        }

        return AppointmentGroup::query()
            ->selectRaw('customer_id, MIN(starts_at) as first_visit_at')
            ->whereIn('customer_id', $customerIds)
            ->groupBy('customer_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->customer_id => Carbon::parse($row->first_visit_at)]);
    }

    private function buildCustomerSegments($groups, $customerFirstVisits, Carbon $periodStart, Carbon $periodEnd): array
    {
        $new = 0;
        $existing = 0;

        $groups->pluck('customer_id')->filter()->unique()->each(function ($customerId) use (&$new, &$existing, $customerFirstVisits, $periodStart, $periodEnd) {
            $firstVisit = $customerFirstVisits->get((string) $customerId);

            if ($firstVisit && $firstVisit->betweenIncluded($periodStart, $periodEnd)) {
                $new++;
                return;
            }

            $existing++;
        });

        return [
            'new' => $new,
            'existing' => $existing,
        ];
    }

    private function buildServiceFocus($groups)
    {
        return $groups
            ->flatMap(function ($group) {
                return $group->items->map(function ($item) {
                    return [
                        'service_name' => $item->service?->name ?: 'Unassigned service',
                        'sales_amount' => (int) ($item->service?->price ?? 0),
                    ];
                });
            })
            ->groupBy('service_name')
            ->map(function ($rows, $serviceName) {
                return [
                    'service_name' => $serviceName,
                    'appointments' => $rows->count(),
                    'sales_amount' => $rows->sum('sales_amount'),
                ];
            })
            ->sortByDesc('appointments')
            ->values();
    }

    private function buildSourceBreakdown($groups)
    {
        return $groups
            ->groupBy(fn ($group) => $group->source ?: 'not_recorded')
            ->map(function ($rows, $source) {
                return [
                    'source' => $this->formatSourceLabel($source),
                    'appointments' => $rows->count(),
                ];
            })
            ->sortByDesc('appointments')
            ->values();
    }

    private function buildStaffReview($groups)
    {
        return $groups
            ->flatMap(function ($group) {
                return $group->items->map(function ($item) use ($group) {
                    $minutes = ($item->starts_at && $item->ends_at)
                        ? max(30, $item->starts_at->diffInMinutes($item->ends_at))
                        : 0;

                    return [
                        'staff_id' => (string) ($item->staff_id ?: 'unassigned'),
                        'staff_name' => $item->staff?->full_name ?: 'Unassigned',
                        'job_title' => $item->staff?->job_title ?: $item->staff?->role_key ?: 'Operational staff',
                        'appointment_group_id' => (string) $group->id,
                        'date_label' => optional($group->starts_at)?->format('d M Y'),
                        'time_label' => trim((optional($item->starts_at)?->format('h:i A') ?: '-').' - '.(optional($item->ends_at)?->format('h:i A') ?: '-')),
                        'customer_name' => $group->customer?->full_name ?: 'Unknown customer',
                        'service_name' => $item->service?->name ?: 'Service',
                        'status_label' => $group->status instanceof AppointmentStatus
                            ? $group->status->label()
                            : str((string) $group->status)->replace('_', ' ')->title()->toString(),
                        'sales_amount' => (int) ($item->service?->price ?? 0),
                        'minutes' => $minutes,
                    ];
                });
            })
            ->groupBy('staff_id')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'staff_id' => $first['staff_id'],
                    'staff_name' => $first['staff_name'],
                    'job_title' => $first['job_title'],
                    'appointments' => $rows->pluck('appointment_group_id')->unique()->count(),
                    'service_items' => $rows->count(),
                    'sales_amount' => $rows->sum('sales_amount'),
                    'minutes' => $rows->sum('minutes'),
                    'hours_label' => number_format($rows->sum('minutes') / 60, 1).'h booked',
                    'details' => $rows->map(fn ($row) => [
                        'customer_name' => $row['customer_name'],
                        'service_name' => $row['service_name'],
                        'time_label' => $row['date_label'].' | '.$row['time_label'],
                        'status_label' => $row['status_label'],
                        'sales_label' => $this->money($row['sales_amount']),
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('minutes')
            ->values();
    }

    private function buildReportRows($groups, $customerFirstVisits, Carbon $periodStart, Carbon $periodEnd)
    {
        return $groups->map(function ($group) use ($customerFirstVisits, $periodStart, $periodEnd) {
            $customerId = (string) $group->customer_id;
            $firstVisit = $customerFirstVisits->get($customerId);
            $customerType = ($firstVisit && $firstVisit->betweenIncluded($periodStart, $periodEnd)) ? 'New' : 'Existing';
            $salesAmount = (int) $group->items->sum(fn ($item) => (int) ($item->service?->price ?? 0));
            $statusLabel = $group->status instanceof AppointmentStatus
                ? $group->status->label()
                : str((string) $group->status)->replace('_', ' ')->title()->toString();

            return collect([
                'date_label' => optional($group->starts_at)?->format('d M Y'),
                'time_label' => optional($group->starts_at)?->format('h:i A'),
                'customer_name' => $group->customer?->full_name ?: 'Unknown customer',
                'customer_type' => $customerType,
                'services' => $group->items->map(fn ($item) => $item->service?->name)->filter()->unique()->implode(', '),
                'staff' => $group->items->map(fn ($item) => $item->staff?->full_name)->filter()->unique()->implode(', '),
                'status_label' => $statusLabel,
                'source_label' => $this->formatSourceLabel($group->source ?: 'not_recorded'),
                'sales_amount' => $salesAmount,
                'sales_label' => $this->money($salesAmount),
            ]);
        });
    }

    private function buildMembershipSummary(Collection $groups): array
    {
        $tiers = [
            'bronze' => 0,
            'silver' => 0,
            'black' => 0,
        ];

        $groups
            ->pluck('customer.membership_type')
            ->filter()
            ->map(fn ($membershipType) => mb_strtolower(trim((string) $membershipType)))
            ->each(function (string $membershipType) use (&$tiers): void {
                if (array_key_exists($membershipType, $tiers)) {
                    $tiers[$membershipType]++;
                }
            });

        return $tiers;
    }

    private function buildRevenueBreakdown(Collection $groups): array
    {
        $rows = $groups->flatMap(function ($group) {
            return $group->items->map(function ($item) {
                $serviceName = $item->service?->name ?: 'Service';
                $categoryKey = $this->resolveRevenueCategory($serviceName);

                return [
                    'category_key' => $categoryKey,
                    'category_label' => $this->revenueCategoryLabel($categoryKey),
                    'amount' => (int) ($item->service?->price ?? 0),
                ];
            });
        });

        return [
            'total' => (int) $rows->sum('amount'),
            'groups' => $rows
                ->groupBy('category_key')
                ->map(function (Collection $categoryRows, string $categoryKey) {
                    return [
                        'key' => $categoryKey,
                        'label' => $this->revenueCategoryLabel($categoryKey),
                        'amount' => (int) $categoryRows->sum('amount'),
                    ];
                })
                ->sortByDesc('amount')
                ->values(),
        ];
    }

    private function buildPicList(Collection $staffList): Collection
    {
        return $staffList
            ->sort(function ($left, $right) {
                $leftRole = $this->normalizePicRole($left->operational_role ?: $left->role_key);
                $rightRole = $this->normalizePicRole($right->operational_role ?: $right->role_key);
                $leftRank = $this->picRoleRank($leftRole);
                $rightRank = $this->picRoleRank($rightRole);

                if ($leftRank === $rightRank) {
                    return strcasecmp($left->full_name, $right->full_name);
                }

                return $leftRank <=> $rightRank;
            })
            ->values();
    }

    private function normalizePicRole(?string $role): string
    {
        $normalized = mb_strtolower(trim((string) $role));

        return match ($normalized) {
            'doctor' => 'doctor',
            'aesthetic', 'aestatic', 'nurse' => 'aesthetic',
            'beautician' => 'beautician',
            'management' => 'management',
            default => 'others',
        };
    }

    private function picRoleRank(string $role): int
    {
        return match ($role) {
            'doctor' => 1,
            'aesthetic' => 2,
            'beautician' => 3,
            'management' => 4,
            default => 5,
        };
    }

    private function formatRangeLabel(Carbon $start, Carbon $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('d M Y');
        }

        if ($start->isSameMonth($end)) {
            return $start->format('j').' - '.$end->format('j F Y');
        }

        if ($start->isSameYear($end)) {
            return $start->format('j M').' - '.$end->format('j M Y');
        }

        return $start->format('j M Y').' - '.$end->format('j M Y');
    }

    private function resolveRevenueCategory(string $serviceName): string
    {
        $normalized = mb_strtolower(trim($serviceName));

        return match (true) {
            str_contains($normalized, 'wellness'),
            str_contains($normalized, 'weight'),
            str_contains($normalized, 'slim'),
            str_contains($normalized, 'nutrition') => 'wellness',
            str_contains($normalized, 'aesthetic'),
            str_contains($normalized, 'laser'),
            str_contains($normalized, 'inject'),
            str_contains($normalized, 'filler'),
            str_contains($normalized, 'botox') => 'aesthetic',
            str_contains($normalized, 'spa'),
            str_contains($normalized, 'beauty'),
            str_contains($normalized, 'facial') => 'spa_beauty',
            default => 'others',
        };
    }

    private function revenueCategoryLabel(string $categoryKey): string
    {
        return match ($categoryKey) {
            'wellness' => 'Wellness',
            'aesthetic' => 'Aesthetic',
            'spa_beauty' => 'Spa & Beauty',
            default => 'Others',
        };
    }

    private function formatSourceLabel(?string $source): string
    {
        return match ((string) $source) {
            '', 'not_recorded' => 'Not recorded',
            'walk_in' => 'Walk-in',
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'referral' => 'Referral',
            default => str((string) $source)->replace('_', ' ')->title()->toString(),
        };
    }

    private function streamCsv($reportRows, string $periodLabel): StreamedResponse
    {
        $filename = 'lindo-dashboard-report-'.str($periodLabel)->slug()->toString().'.csv';

        return response()->streamDownload(function () use ($reportRows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Time',
                'Customer',
                'Customer Type',
                'Services',
                'Staff',
                'Status',
                'Source',
                'Sales',
            ]);

            foreach ($reportRows as $row) {
                fputcsv($handle, [
                    $row['date_label'],
                    $row['time_label'],
                    $row['customer_name'],
                    $row['customer_type'],
                    $row['services'],
                    $row['staff'],
                    $row['status_label'],
                    $row['source_label'],
                    $row['sales_label'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function money(int $amount): string
    {
        return 'RM '.number_format($amount, 0);
    }
}
