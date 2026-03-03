<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Staff;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['full_name' => 'Doctor A',      'role' => 'doctor',     'is_active' => true],
            ['full_name' => 'Doctor B',      'role' => 'doctor',     'is_active' => true],
            ['full_name' => 'Nurse A',       'role' => 'nurse',      'is_active' => true],
            ['full_name' => 'Nurse B',       'role' => 'nurse',      'is_active' => true],
            ['full_name' => 'Beautician A',  'role' => 'beautician', 'is_active' => true],
            ['full_name' => 'Beautician B',  'role' => 'beautician', 'is_active' => true],
        ];

        foreach ($rows as $r) {
            Staff::updateOrCreate(
                ['full_name' => $r['full_name']],
                $r
            );
        }
    }
}