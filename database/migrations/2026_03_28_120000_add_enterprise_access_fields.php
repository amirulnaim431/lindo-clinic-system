<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'employee_code')) {
                $table->string('employee_code', 40)->nullable()->unique()->after('full_name');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'password_setup_required')) {
                $table->boolean('password_setup_required')->default(false)->after('password');
            }

            if (! Schema::hasColumn('users', 'last_password_reset_sent_at')) {
                $table->timestamp('last_password_reset_sent_at')->nullable()->after('password_setup_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'employee_code')) {
                $table->dropUnique(['employee_code']);
                $table->dropColumn('employee_code');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['last_password_reset_sent_at', 'password_setup_required'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
