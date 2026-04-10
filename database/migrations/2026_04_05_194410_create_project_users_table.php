<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('email');
            $table->string('password')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_ghost')->default(false)->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->string('ghost_source')->nullable();
            $table->boolean('must_set_password')->default(false);
            $table->boolean('must_verify_email')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'email']);
            $table->index(['project_id', 'is_active']);
            $table->index(['project_id', 'is_ghost']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_users');
    }
};
