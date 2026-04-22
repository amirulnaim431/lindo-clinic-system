<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_items', function (Blueprint $table) {
            $table->string('service_name_snapshot', 160)->nullable()->after('service_id');
            $table->string('service_category_key_snapshot', 40)->nullable()->after('service_name_snapshot');
            $table->string('service_category_label_snapshot', 80)->nullable()->after('service_category_key_snapshot');
            $table->string('staff_name_snapshot', 160)->nullable()->after('staff_id');
            $table->string('staff_role_snapshot', 50)->nullable()->after('staff_name_snapshot');
        });

        Schema::create('appointment_item_option_selections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('appointment_item_id');
            $table->ulid('service_option_group_id')->nullable();
            $table->ulid('service_option_value_id')->nullable();
            $table->string('option_group_name', 160);
            $table->string('option_value_label', 160);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('appointment_item_id')
                ->references('id')
                ->on('appointment_items')
                ->cascadeOnDelete();
            $table->foreign('service_option_group_id')
                ->references('id')
                ->on('service_option_groups')
                ->nullOnDelete();
            $table->foreign('service_option_value_id')
                ->references('id')
                ->on('service_option_values')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_item_option_selections');

        Schema::table('appointment_items', function (Blueprint $table) {
            $table->dropColumn([
                'service_name_snapshot',
                'service_category_key_snapshot',
                'service_category_label_snapshot',
                'staff_name_snapshot',
                'staff_role_snapshot',
            ]);
        });
    }
};
