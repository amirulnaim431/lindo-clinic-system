<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointment_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // link to customers (ULID in your project)
            $table->ulid('customer_id');

            // one-hour block in dev phase
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status')->default('booked'); // booked, checked_in, completed, cancelled
            $table->string('source')->default('online'); // online, frontdesk, whatsapp, admin
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();

            $table->index(['starts_at', 'status']);
            $table->index(['customer_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_groups');
    }
};