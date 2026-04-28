<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        DB::table('clinic_settings')->insert([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'key' => 'appointment_schedule',
            'value' => json_encode([
                'start_time' => '10:00',
                'end_time' => '19:00',
                'slot_duration_minutes' => 45,
                'slot_step_minutes' => 60,
                'boxes_per_slot' => 2,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_settings');
    }
};
