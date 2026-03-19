<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = Service::all()->keyBy('name');
        $staff = Staff::all()->keyBy('full_name');

        $attach = function (string $staffName, array $serviceNames) use ($staff, $services) {
            $member = $staff[$staffName] ?? null;

            if (! $member) {
                return;
            }

            foreach ($serviceNames as $serviceName) {
                $service = $services[$serviceName] ?? null;

                if (! $service) {
                    continue;
                }

                DB::table('staff_services')->updateOrInsert(
                    ['staff_id' => $member->id, 'service_id' => $service->id],
                    ['id' => (string) Str::ulid(), 'created_at' => now(), 'updated_at' => now()]
                );
            }
        };

        $attach('Dr. Amanda Binti Elli', ['Weight Loss Program', 'Facial Treatment', 'Consultation', 'Liver Detox']);
        $attach('Dr. Syarifah Munira \'Aaqilah Binti Al Sayed Mohamad', ['Weight Loss Program', 'Facial Treatment', 'Consultation', 'Liver Detox']);
        $attach('Nur Adilla Binti Mohd Ali', ['Simple Injection']);
        $attach('Nur Mastura Ali Toh', ['Nails']);
        $attach('Nurul Rizna Fatima Binti Andi', ['Nails']);
        $attach('Van Ian Par', ['Nails']);
        $attach('Nur Farhanna Binti Abdul Malek', ['Nails']);
        $attach('Monica Tial Tin Rem', ['Nails']);
    }
}
