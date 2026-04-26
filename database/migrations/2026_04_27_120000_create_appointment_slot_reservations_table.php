<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_slot_reservations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('appointment_item_id');
            $table->ulid('staff_id');
            $table->date('slot_date');
            $table->time('start_time');
            $table->unsignedTinyInteger('slot_index');
            $table->timestamps();

            $table->foreign('appointment_item_id', 'asr_item_fk')
                ->references('id')
                ->on('appointment_items')
                ->cascadeOnDelete();

            $table->foreign('staff_id', 'asr_staff_fk')
                ->references('id')
                ->on('staff')
                ->cascadeOnDelete();

            $table->unique(['appointment_item_id'], 'asr_item_unique');
            $table->unique(['staff_id', 'slot_date', 'start_time', 'slot_index'], 'asr_staff_slot_unique');
            $table->index(['staff_id', 'slot_date', 'start_time'], 'asr_staff_slot_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_slot_reservations');
    }
};
