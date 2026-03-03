<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StaffServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::all()->keyBy('name');
        $staff = Staff::all()->keyBy('full_name');

        // Helper
        $attach = function (string $staffName, array $serviceNames) use ($staff, $services) {
            $st = $staff[$staffName] ?? null;
            if (!$st) return;

            foreach ($serviceNames as $svcName) {
                $svc = $services[$svcName] ?? null;
                if (!$svc) continue;

                DB::table('staff_services')->updateOrInsert(
                    ['staff_id' => $st->id, 'service_id' => $svc->id],
                    ['id' => (string)\Illuminate\Support\Str::ulid(), 'created_at' => now(), 'updated_at' => now()]
                );
            }
        };

        // Doctors
        $attach('Doctor A', ['Weight Loss Program', 'Facial Treatment', 'Consultation']);
        $attach('Doctor B', ['Liver Detox', 'Consultation']);

        // Nurses
        $attach('Nurse A', ['Simple Injection']);
        $attach('Nurse B', ['Simple Injection']);

        // Beauticians
        $attach('Beautician A', ['Nails']);
        $attach('Beautician B', ['Nails']);
    }
}
