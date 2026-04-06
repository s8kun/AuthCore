<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('project_users')) {
            return;
        }

        $columnsToDrop = [];

        if (Schema::hasColumn('project_users', 'role')) {
            $columnsToDrop[] = 'role';
        }

        if (Schema::hasColumn('project_users', 'device_name')) {
            $columnsToDrop[] = 'device_name';
        }

        if ($columnsToDrop === []) {
            return;
        }

        Schema::table('project_users', function (Blueprint $table) use ($columnsToDrop): void {
            $table->dropColumn($columnsToDrop);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('project_users')) {
            return;
        }

        Schema::table('project_users', function (Blueprint $table) {
            if (! Schema::hasColumn('project_users', 'role')) {
                $table->string('role')->nullable()->default('user')->after('phone');
            }

            if (! Schema::hasColumn('project_users', 'device_name')) {
                $table->string('device_name')->nullable()->after('role');
            }
        });
    }
};
