<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('appointment_items', function (Blueprint $table) {
            $table->string('required_role', 50)->nullable()->after('staff_id');
            $table->index(['required_role']);
        });
    }

    public function down(): void
    {
        Schema::table('appointment_items', function (Blueprint $table) {
            $table->dropIndex(['required_role']);
            $table->dropColumn('required_role');
        });
    }
};
