<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerBenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        $records = require database_path('seeders/data/customer_benchmark.php');

        if (empty($records)) {
            $this->command?->info('Customer benchmark seed is empty. Run php artisan lindo:export-customer-benchmark on the source system first.');

            return;
        }

        $overwrite = filter_var(env('LINDO_SEED_OVERWRITE_CUSTOMERS', false), FILTER_VALIDATE_BOOLEAN);
        $created = 0;
        $updated = 0;

        Customer::unguarded(function () use ($records, $overwrite, &$created, &$updated): void {
            foreach ($records as $record) {
                $customer = $this->findExistingCustomer($record);
                $payload = $this->cleanPayload($record);

                if ($customer) {
                    if (method_exists($customer, 'trashed') && $customer->trashed()) {
                        $customer->restore();
                    }

                    $customer->fill($overwrite ? $payload : $this->onlyMissingValues($customer, $payload));

                    if ($customer->isDirty()) {
                        $customer->save();
                        $updated++;
                    }

                    continue;
                }

                Customer::create($payload);
                $created++;
            }
        });

        $this->command?->info("Customer benchmark seed complete. Created {$created}, updated {$updated}.");
    }

    private function findExistingCustomer(array $record): ?Customer
    {
        if (! empty($record['id'])) {
            $customer = Customer::withTrashed()->find($record['id']);

            if ($customer) {
                return $customer;
            }
        }

        foreach (['phone', 'membership_code', 'ic_passport'] as $column) {
            $value = trim((string) ($record[$column] ?? ''));

            if ($value !== '') {
                $customer = Customer::withTrashed()->where($column, $value)->first();

                if ($customer) {
                    return $customer;
                }
            }
        }

        $name = trim((string) ($record['full_name'] ?? ''));

        return $name === ''
            ? null
            : Customer::withTrashed()->where('full_name', $name)->first();
    }

    private function cleanPayload(array $record): array
    {
        $allowed = [
            'id',
            'full_name',
            'phone',
            'email',
            'dob',
            'ic_passport',
            'gender',
            'marital_status',
            'nationality',
            'occupation',
            'address',
            'emergency_contact_name',
            'emergency_contact_phone',
            'membership_code',
            'membership_type',
            'current_package',
            'current_package_since',
            'membership_package_value_cents',
            'membership_balance_cents',
            'weight',
            'height',
            'allergies',
            'notes',
            'created_at',
            'updated_at',
        ];

        return collect($record)
            ->only($allowed)
            ->reject(fn ($value) => $value === '')
            ->all();
    }

    private function onlyMissingValues(Customer $customer, array $payload): array
    {
        return collect($payload)
            ->filter(function ($value, string $key) use ($customer): bool {
                if ($key === 'id') {
                    return false;
                }

                $current = $customer->getAttribute($key);

                return ($current === null || $current === '') && $value !== null && $value !== '';
            })
            ->all();
    }
}
