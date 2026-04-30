<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unsignedBigInteger('membership_package_value_cents')->nullable()->after('current_package_since');
            $table->unsignedBigInteger('membership_balance_cents')->nullable()->after('membership_package_value_cents');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'membership_package_value_cents',
                'membership_balance_cents',
            ]);
        });
    }
};
