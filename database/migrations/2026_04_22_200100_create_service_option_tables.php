<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_option_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code', 120)->unique();
            $table->string('name', 160);
            $table->string('selection_mode', 20)->default('single');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_option_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('service_option_group_id');
            $table->string('value_code', 120);
            $table->string('label', 160);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('service_option_group_id')
                ->references('id')
                ->on('service_option_groups')
                ->cascadeOnDelete();
            $table->unique(['service_option_group_id', 'value_code'], 'service_option_values_group_code_unique');
        });

        Schema::create('service_option_group_service', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('service_id');
            $table->ulid('service_option_group_id');
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('service_option_group_id')->references('id')->on('service_option_groups')->cascadeOnDelete();
            $table->unique(['service_id', 'service_option_group_id'], 'service_option_group_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_option_group_service');
        Schema::dropIfExists('service_option_values');
        Schema::dropIfExists('service_option_groups');
    }
};
