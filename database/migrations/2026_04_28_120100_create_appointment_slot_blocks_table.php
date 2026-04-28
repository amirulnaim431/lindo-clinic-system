<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_slot_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('staff_id');
            $table->date('slot_date');
            $table->time('start_time');
            $table->unsignedTinyInteger('slot_index');
            $table->string('reason', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('staff_id', 'asb_staff_fk')
                ->references('id')
                ->on('staff')
                ->cascadeOnDelete();

            $table->unique(['staff_id', 'slot_date', 'start_time', 'slot_index'], 'asb_staff_slot_unique');
            $table->index(['staff_id', 'slot_date', 'start_time'], 'asb_staff_slot_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_slot_blocks');
    }
};
