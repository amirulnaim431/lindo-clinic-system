<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_items', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('appointment_group_id'); // points to the group/table you use
            $table->ulid('service_id');
            $table->ulid('staff_id')->nullable();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['appointment_group_id']);
            $table->index(['staff_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_items');
    }
};