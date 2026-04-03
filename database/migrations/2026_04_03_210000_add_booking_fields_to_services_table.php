<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('category_key', 40)->default('consultations')->after('name');
            $table->text('description')->nullable()->after('category_key');
            $table->unsignedInteger('promo_price')->nullable()->after('price');
            $table->boolean('is_promo')->default(false)->after('promo_price');
            $table->unsignedSmallInteger('display_order')->default(0)->after('is_active');
        });

        DB::table('services')
            ->where('name', 'Consultation')
            ->update(['category_key' => 'consultations', 'display_order' => 1]);

        DB::table('services')
            ->whereIn('name', ['Weight Loss Program', 'Liver Detox'])
            ->update(['category_key' => 'wellness']);

        DB::table('services')
            ->whereIn('name', ['Facial Treatment', 'Simple Injection'])
            ->update(['category_key' => 'aesthetics']);

        DB::table('services')
            ->whereIn('name', ['Nails'])
            ->update(['category_key' => 'spa']);
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'category_key',
                'description',
                'promo_price',
                'is_promo',
                'display_order',
            ]);
        });
    }
};
