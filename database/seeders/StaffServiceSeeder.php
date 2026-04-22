<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StaffServiceSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LindoClinicCatalogSeeder::class);
    }
}
