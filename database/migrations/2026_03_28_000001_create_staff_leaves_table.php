<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('staff_id');
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->foreign('staff_id')->references('id')->on('staff')->cascadeOnDelete();
            $table->index(['staff_id', 'start_date', 'end_date']);
            $table->index(['status', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leaves');
    }
};
