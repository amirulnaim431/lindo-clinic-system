<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Service;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Consultation', 'category_key' => 'consultations', 'description' => null, 'duration_minutes' => 60, 'price' => null, 'promo_price' => null, 'is_promo' => false, 'is_active' => true, 'display_order' => 1],
            ['name' => 'Weight Loss Program', 'category_key' => 'wellness', 'description' => null, 'duration_minutes' => 60, 'price' => null, 'promo_price' => null, 'is_promo' => false, 'is_active' => true, 'display_order' => 1],
            ['name' => 'Liver Detox', 'category_key' => 'wellness', 'description' => null, 'duration_minutes' => 60, 'price' => null, 'promo_price' => null, 'is_promo' => false, 'is_active' => true, 'display_order' => 2],
            ['name' => 'Facial Treatment', 'category_key' => 'aesthetics', 'description' => null, 'duration_minutes' => 60, 'price' => null, 'promo_price' => null, 'is_promo' => false, 'is_active' => true, 'display_order' => 1],
            ['name' => 'Simple Injection', 'category_key' => 'aesthetics', 'description' => null, 'duration_minutes' => 60, 'price' => null, 'promo_price' => null, 'is_promo' => false, 'is_active' => true, 'display_order' => 2],
            ['name' => 'Nails', 'category_key' => 'spa', 'description' => null, 'duration_minutes' => 60, 'price' => null, 'promo_price' => null, 'is_promo' => false, 'is_active' => true, 'display_order' => 1],
        ];

        foreach ($rows as $r) {
            Service::updateOrCreate(
                ['name' => $r['name']],
                $r
            );
        }
    }
}
