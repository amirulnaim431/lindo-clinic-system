<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'job_title')) {
                $table->string('job_title')->nullable()->after('full_name');
            }

            if (! Schema::hasColumn('staff', 'department')) {
                $table->string('department', 120)->nullable()->after('job_title');
            }

            if (! Schema::hasColumn('staff', 'phone')) {
                $table->string('phone', 50)->nullable()->after('department');
            }

            if (! Schema::hasColumn('staff', 'email')) {
                $table->string('email', 120)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('staff', 'operational_role')) {
                $table->string('operational_role', 50)->nullable()->after('email');
            }

            if (! Schema::hasColumn('staff', 'can_login')) {
                $table->boolean('can_login')->default(false)->after('is_active');
            }

            if (! Schema::hasColumn('staff', 'user_id')) {
                $table->foreignId('user_id')->nullable()->unique()->after('can_login')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('staff', 'notes')) {
                $table->text('notes')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('staff', 'access_permissions')) {
                $table->json('access_permissions')->nullable()->after('notes');
            }
        });

        $staffRows = DB::table('staff')->select('id', 'role', 'role_key', 'job_title', 'operational_role')->get();

        foreach ($staffRows as $row) {
            $normalizedRole = $row->operational_role ?: $row->role_key ?: $row->role ?: 'support';

            DB::table('staff')
                ->where('id', $row->id)
                ->update([
                    'job_title' => $row->job_title ?: str($normalizedRole)->replace('_', ' ')->title()->toString(),
                    'operational_role' => $normalizedRole,
                    'role_key' => $normalizedRole,
                    'role' => $normalizedRole,
                    'access_permissions' => json_encode([]),
                    'updated_at' => now(),
                ]);
        }

        DB::table('staff')
            ->whereNull('department')
            ->update(['department' => 'Administration']);
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }

            foreach (['access_permissions', 'notes', 'can_login', 'operational_role', 'email', 'phone', 'department', 'job_title'] as $column) {
                if (Schema::hasColumn('staff', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
