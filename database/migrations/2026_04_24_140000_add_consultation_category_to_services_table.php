<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'consultation_category_key')) {
                $table->string('consultation_category_key', 40)->nullable()->after('category_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'consultation_category_key')) {
                $table->dropColumn('consultation_category_key');
            }
        });
    }
};
