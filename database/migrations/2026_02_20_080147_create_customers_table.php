<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('full_name');
            $table->string('phone')->unique(); // store normalized later (e.g., +60...)

            $table->string('email')->nullable();
            $table->date('dob')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};