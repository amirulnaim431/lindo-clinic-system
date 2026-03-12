<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
                    $inner->where('full_name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('ic_passport', 'like', $like)
                        ->orWhere('membership_code', 'like', $like);

                    if (!empty($normalizedDigits)) {
                        $inner->orWhereRaw('REPLACE(REPLACE(REPLACE(phone, "+", ""), "-", ""), " ", "") like ?', ['%' . $normalizedDigits . '%'])
                              ->orWhereRaw('REPLACE(REPLACE(ic_passport, "-", ""), " ", "") like ?', ['%' . $normalizedDigits . '%']);
                    }
                });
            })
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
        $loadableRelations = [];

        if (method_exists($customer, 'appointments')) {
            $loadableRelations[] = 'appointments';
        }

        $customer->loadMissing($loadableRelations);

        [$upcomingAppointments, $appointmentHistory] = $this->resolveAppointmentBuckets($customer);

        return view('app.customers.show', [
            'customer' => $customer,
            'upcomingAppointments' => $upcomingAppointments,
            'appointmentHistory' => $appointmentHistory,
            'hasAppointmentsRelation' => method_exists($customer, 'appointments'),
        ]);
    }

    /**
     * @return array{0: Collection, 1: Collection}
     */
    protected function resolveAppointmentBuckets(Customer $customer): array
    {
        if (!method_exists($customer, 'appointments') || ! $customer->relationLoaded('appointments')) {
            return [collect(), collect()];
        }

        /** @var \Illuminate\Support\Collection $appointments */
        $appointments = $customer->appointments instanceof Collection
            ? $customer->appointments
            : collect($customer->appointments);

        $now = now();

        $upcoming = $appointments
            ->filter(function ($appointment) use ($now) {
                $date = $this->extractAppointmentDate($appointment);

                return $date !== null && $date->greaterThanOrEqualTo($now);
            })
            ->sortBy(fn ($appointment) => $this->extractAppointmentDate($appointment)?->timestamp ?? PHP_INT_MAX)
            ->values();

        $history = $appointments
            ->filter(function ($appointment) use ($now) {
                $date = $this->extractAppointmentDate($appointment);

                return $date === null || $date->lessThan($now);
            })
            ->sortByDesc(fn ($appointment) => $this->extractAppointmentDate($appointment)?->timestamp ?? 0)
            ->values();

        return [$upcoming, $history];
    }

    protected function extractAppointmentDate(mixed $appointment): ?\Illuminate\Support\Carbon
    {
        if (!is_object($appointment)) {
            return null;
        }

        foreach ([
            'start_at',
            'scheduled_at',
            'appointment_at',
            'appointment_date',
            'date',
            'starts_at',
        ] as $field) {
            if (isset($appointment->{$field}) && !empty($appointment->{$field})) {
                try {
                    return \Illuminate\Support\Carbon::parse($appointment->{$field});
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        return null;
    }
}

//DOASOLUTIONS