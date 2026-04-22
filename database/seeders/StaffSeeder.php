<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['full_name' => 'Dr. Ahmad Idham Bin Ahmad Nadzri', 'job_title' => 'Chief Operating Officer', 'department' => 'Executive Office', 'operational_role' => 'operations'],
            ['full_name' => 'Jamaliyah Binti Fendi', 'job_title' => 'Tea Lady', 'department' => 'Operations Support', 'operational_role' => 'support'],
            ['full_name' => 'Musfirah Binti Nor Azmi', 'job_title' => 'Sales & Marketing Executive', 'department' => 'Sales & Marketing', 'operational_role' => 'marketing'],
            ['full_name' => 'Nur Mastura Ali Toh', 'job_title' => 'Manager Beauty & Aesthetic', 'department' => 'Beauty & Aesthetic', 'operational_role' => 'management'],
            ['full_name' => 'Nurnazira Binti Kamarulaizam', 'job_title' => 'Human Resources Executive', 'department' => 'Human Resources', 'operational_role' => 'admin'],
            ['full_name' => 'Saidatul Aqilah Binti Muhamad Khushairi', 'job_title' => 'Admin and Account Assistant', 'department' => 'Finance & Accounts', 'operational_role' => 'admin'],
            ['full_name' => 'Siti Hajar Binti Nasarudin', 'job_title' => 'Multimedia & Graphic Design Executive', 'department' => 'Sales & Marketing', 'operational_role' => 'marketing'],
        ];

        foreach ($rows as $row) {
            $staff = Staff::query()->firstOrNew(['full_name' => $row['full_name']]);

            $staff->fill([
                'job_title' => $row['job_title'],
                'department' => $row['department'],
                'operational_role' => $row['operational_role'],
                'role_key' => $row['operational_role'],
                'role' => $row['operational_role'],
                'is_active' => true,
            ]);

            if (! $staff->exists) {
                $staff->can_login = false;
                $staff->access_permissions = json_encode([]);
            }

            $staff->save();
        }

        $this->call(LindoClinicCatalogSeeder::class);
    }
}
