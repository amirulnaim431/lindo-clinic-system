<?php

use App\Models\Customer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('lindo:export-customer-benchmark', function () {
    $path = database_path('seeders/data/customer_benchmark.php');
    $columns = [
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

    $rows = Customer::withTrashed()
        ->orderByRaw("CASE WHEN full_name IS NULL OR full_name = '' THEN 1 ELSE 0 END")
        ->orderBy('full_name')
        ->get()
        ->map(function (Customer $customer) use ($columns): array {
            return collect($customer->only($columns))
                ->map(function ($value) {
                    if ($value instanceof DateTimeInterface) {
                        return $value->format('Y-m-d H:i:s');
                    }

                    return $value;
                })
                ->all();
        })
        ->all();

    File::ensureDirectoryExists(dirname($path));
    File::put($path, "<?php\n\nreturn ".var_export($rows, true).";\n");

    $this->info('Exported '.count($rows).' customers to '.$path);
})->purpose('Export the current customer list into the benchmark seeder data file');
