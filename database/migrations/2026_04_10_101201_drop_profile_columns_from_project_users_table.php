<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_users')) {
            return;
        }

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('project_users', 'first_name') ? 'first_name' : null,
            Schema::hasColumn('project_users', 'last_name') ? 'last_name' : null,
            Schema::hasColumn('project_users', 'phone') ? 'phone' : null,
        ]));

        if ($columnsToDrop === []) {
            return;
        }

        Schema::table('project_users', function (Blueprint $table) use ($columnsToDrop): void {
            $table->dropColumn($columnsToDrop);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('project_users')) {
            return;
        }

        $missingColumns = array_values(array_filter([
            Schema::hasColumn('project_users', 'first_name') ? null : 'first_name',
            Schema::hasColumn('project_users', 'last_name') ? null : 'last_name',
            Schema::hasColumn('project_users', 'phone') ? null : 'phone',
        ]));

        if ($missingColumns === []) {
            return;
        }

        Schema::table('project_users', function (Blueprint $table) use ($missingColumns): void {
            $table->after('password', function (Blueprint $table) use ($missingColumns): void {
                if (in_array('first_name', $missingColumns, true)) {
                    $table->string('first_name')->nullable();
                }

                if (in_array('last_name', $missingColumns, true)) {
                    $table->string('last_name')->nullable();
                }

                if (in_array('phone', $missingColumns, true)) {
                    $table->string('phone')->nullable();
                }
            });
        });
    }
};
