<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $legacyPlaceholderNames = [
            'Doctor A',
            'Doctor B',
            'Nurse A',
            'Nurse B',
            'Beautician A',
            'Beautician B',
        ];

        DB::table('staff')
            ->whereIn('full_name', $legacyPlaceholderNames)
            ->update([
                'is_active' => false,
                'can_login' => false,
                'notes' => 'Legacy placeholder record retained only for historical reference.',
                'updated_at' => now(),
            ]);

        $rows = [
            ['full_name' => 'Dr. Ahmad Idham Bin Ahmad Nadzri', 'job_title' => 'Chief Operating Officer', 'department' => 'Executive Office', 'operational_role' => 'operations'],
            ['full_name' => 'Dr. Amanda Binti Elli', 'job_title' => 'Medical Officer', 'department' => 'Medical', 'operational_role' => 'doctor'],
            ['full_name' => 'Dr. Syarifah Munira \'Aaqilah Binti Al Sayed Mohamad', 'job_title' => 'Medical Officer', 'department' => 'Medical', 'operational_role' => 'doctor'],
            ['full_name' => 'Jamaliyah Binti Fendi', 'job_title' => 'Tea Lady', 'department' => 'Operations Support', 'operational_role' => 'support'],
            ['full_name' => 'Musfirah Binti Nor Azmi', 'job_title' => 'Sales & Marketing Executive', 'department' => 'Sales & Marketing', 'operational_role' => 'marketing'],
            ['full_name' => 'Nur Adilla Binti Mohd Ali', 'job_title' => 'Nurse Assistant', 'department' => 'Medical', 'operational_role' => 'nurse'],
            ['full_name' => 'Nur Mastura Ali Toh', 'job_title' => 'Manager Beauty & Aesthetic', 'department' => 'Beauty & Aesthetic', 'operational_role' => 'management'],
            ['full_name' => 'Nurnazira Binti Kamarulaizam', 'job_title' => 'Human Resources Executive', 'department' => 'Human Resources', 'operational_role' => 'admin'],
            ['full_name' => 'Nurul Rizna Fatima Binti Andi', 'job_title' => 'Beautician', 'department' => 'Beauty & Aesthetic', 'operational_role' => 'beautician'],
            ['full_name' => 'Saidatul Aqilah Binti Muhamad Khushairi', 'job_title' => 'Admin and Account Assistant', 'department' => 'Finance & Accounts', 'operational_role' => 'admin'],
            ['full_name' => 'Siti Hajar Binti Nasarudin', 'job_title' => 'Multimedia & Graphic Design Executive', 'department' => 'Sales & Marketing', 'operational_role' => 'marketing'],
            ['full_name' => 'Van Ian Par', 'job_title' => 'Nail Artist', 'department' => 'Beauty & Aesthetic', 'operational_role' => 'beautician'],
            ['full_name' => 'Nur Farhanna Binti Abdul Malek', 'job_title' => 'Beautician', 'department' => 'Beauty & Aesthetic', 'operational_role' => 'beautician'],
            ['full_name' => 'Monica Tial Tin Rem', 'job_title' => 'Nail Artist', 'department' => 'Beauty & Aesthetic', 'operational_role' => 'beautician'],
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
                $staff->access_permissions = [];
            }

            $staff->save();
        }
    }
}
