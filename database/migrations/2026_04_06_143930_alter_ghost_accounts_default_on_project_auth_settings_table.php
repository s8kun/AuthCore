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
        Schema::table('project_auth_settings', function (Blueprint $table) {
            $table->boolean('ghost_accounts_enabled')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_auth_settings', function (Blueprint $table) {
            $table->boolean('ghost_accounts_enabled')->default(true)->change();
        });
    }
};
