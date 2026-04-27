<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_option_group_service') || ! Schema::hasTable('service_option_groups')) {
            return;
        }

        $optionGroupIds = DB::table('service_option_groups')
            ->whereIn('code', [
                'tirze_dosage',
                'tirze_session',
                'tirze_maintenance',
                'session_1_to_4',
                'session_1_to_6',
            ])
            ->pluck('id')
            ->all();

        if ($optionGroupIds === []) {
            return;
        }

        DB::table('service_option_group_service')
            ->whereIn('service_option_group_id', $optionGroupIds)
            ->update(['is_required' => false]);
    }

    public function down(): void
    {
        // Intentionally no-op: admins can control each service option requirement in /app/services.
    }
};
