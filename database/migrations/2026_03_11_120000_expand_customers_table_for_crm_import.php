<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('ic_passport')->nullable()->after('dob');
            $table->string('gender', 20)->nullable()->after('ic_passport');
            $table->string('marital_status', 50)->nullable()->after('gender');
            $table->string('nationality', 100)->nullable()->after('marital_status');
            $table->string('occupation')->nullable()->after('nationality');
            $table->text('address')->nullable()->after('occupation');

            $table->decimal('weight', 6, 2)->nullable()->after('address');
            $table->decimal('height', 6, 2)->nullable()->after('weight');

            $table->text('allergies')->nullable()->after('height');

            $table->string('emergency_contact_name')->nullable()->after('allergies');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');

            $table->string('membership_code')->nullable()->after('emergency_contact_phone');
            $table->string('membership_type', 100)->nullable()->after('membership_code');
            $table->string('current_package')->nullable()->after('membership_type');
            $table->date('current_package_since')->nullable()->after('current_package');

            $table->text('notes')->nullable()->after('current_package_since');
            $table->string('legacy_code')->nullable()->after('notes');

            $table->index('ic_passport');
            $table->index('membership_code');
            $table->index('legacy_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['ic_passport']);
            $table->dropIndex(['membership_code']);
            $table->dropIndex(['legacy_code']);

            $table->dropColumn([
                'ic_passport',
                'gender',
                'marital_status',
                'nationality',
                'occupation',
                'address',
                'weight',
                'height',
                'allergies',
                'emergency_contact_name',
                'emergency_contact_phone',
                'membership_code',
                'membership_type',
                'current_package',
                'current_package_since',
                'notes',
                'legacy_code',
            ]);
        });
    }
};