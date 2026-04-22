<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\App\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('search'));

        $customers = Customer::query()
            ->select([
                'id',
                'full_name',
                'phone',
                'ic_passport',
                'gender',
                'membership_code',
                'membership_type',
                'current_package',
                'current_package_since',
                'created_at',
            ])
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%' . $search . '%';
                $normalizedDigits = preg_replace('/\D+/', '', $search);

                $query->where(function (Builder $inner) use ($like, $normalizedDigits) {
                    $inner
                        ->where('full_name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('ic_passport', 'like', $like)
                        ->orWhere('membership_code', 'like', $like);

                    if (! empty($normalizedDigits)) {
                        $inner
                            ->orWhereRaw(
                                'REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") like ?',
                                ['%' . $normalizedDigits . '%']
                            )
                            ->orWhereRaw(
                                'REPLACE(REPLACE(ic_passport, "-", ""), " ", "") like ?',
                                ['%' . $normalizedDigits . '%']
                            );
                    }
                });
            })
            ->orderByRaw("CASE WHEN full_name IS NULL OR full_name = '' THEN 1 ELSE 0 END")
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString();

        return view('app.customers.index', [
            'customers' => $customers,
            'search' => $search,
        ]);
    }

    public function show(Customer $customer): View
    {
        $customer->loadMissing([
            'appointmentGroups.items.service',
            'appointmentGroups.items.staff',
            'appointmentGroups.items.optionSelections',
        ]);

        [$upcomingAppointments, $appointmentHistory] = $this->resolveAppointmentBuckets($customer);

        return view('app.customers.show', [
            'customer' => $customer,
            'upcomingAppointments' => $upcomingAppointments,
            'appointmentHistory' => $appointmentHistory,
        ]);
    }

    public function edit(Customer $customer): View
    {
        $this->ensureAdmin();

        return view('app.customers.edit', [
            'customer' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->ensureAdmin();

        $customer->update($request->validated());

        return redirect()
            ->route('app.customers.show', $customer)
            ->with('success', 'Customer profile updated successfully.');
    }

    protected function ensureAdmin(): void
    {
        $user = auth()->user();

        abort_unless($user && method_exists($user, 'isAdmin') && $user->isAdmin(), 403, 'Unauthorized.');
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    protected function resolveAppointmentBuckets(Customer $customer): array
    {
        $groups = $customer->appointmentGroups instanceof Collection
            ? $customer->appointmentGroups
            : collect($customer->appointmentGroups);

        $groups = $groups
            ->filter(fn ($group) => ! empty($group->starts_at))
            ->values();

        $now = now();

        $upcoming = $groups
            ->filter(function ($group) use ($now) {
                return $group->starts_at !== null && $group->starts_at->greaterThanOrEqualTo($now);
            })
            ->sortBy(fn ($group) => $group->starts_at?->timestamp ?? PHP_INT_MAX)
            ->values();

        $history = $groups
            ->filter(function ($group) use ($now) {
                return $group->starts_at === null || $group->starts_at->lessThan($now);
            })
            ->sortByDesc(fn ($group) => $group->starts_at?->timestamp ?? 0)
            ->values();

        return [$upcoming, $history];
    }
}
