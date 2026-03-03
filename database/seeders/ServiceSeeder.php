<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Weight Loss Program', 'duration_minutes' => 60, 'price' => null, 'is_active' => true],
            ['name' => 'Nails',              'duration_minutes' => 60, 'price' => null, 'is_active' => true],
            ['name' => 'Facial Treatment',   'duration_minutes' => 60, 'price' => null, 'is_active' => true],
            ['name' => 'Consultation',       'duration_minutes' => 60, 'price' => null, 'is_active' => true],
            ['name' => 'Liver Detox',        'duration_minutes' => 60, 'price' => null, 'is_active' => true],
            ['name' => 'Simple Injection',   'duration_minutes' => 60, 'price' => null, 'is_active' => true],
        ];

        foreach ($rows as $r) {
            Service::updateOrCreate(
                ['name' => $r['name']],
                $r
            );
        }
    }
}