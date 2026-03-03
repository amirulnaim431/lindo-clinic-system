<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('customer_id');
            $table->ulid('service_id');
            $table->ulid('staff_id')->nullable(); // allow "any staff" then assign later

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status')->default('booked');
            // booked, checked_in, completed, cancelled, no_show

            $table->string('source')->default('online');
            // online, frontdesk, whatsapp, admin

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('service_id')->references('id')->on('services');
            $table->foreign('staff_id')->references('id')->on('staff');

            $table->index(['staff_id', 'starts_at']);
            $table->index(['customer_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};