<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_password_resets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('project_user_id')->constrained('project_users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->ipAddress('requested_ip')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_password_resets');
    }
};
