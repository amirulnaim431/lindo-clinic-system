<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_services', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('staff_id');
            $table->ulid('service_id');

            $table->timestamps();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();

            $table->unique(['staff_id', 'service_id']);
            $table->index(['service_id', 'staff_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_services');
    }
};