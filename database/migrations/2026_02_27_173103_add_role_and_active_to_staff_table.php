<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (!Schema::hasColumn('staff', 'role_key')) {
                $table->string('role_key', 50)->default('therapist');
            }
            if (!Schema::hasColumn('staff', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'role_key')) {
                $table->dropColumn('role_key');
            }
            if (Schema::hasColumn('staff', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};