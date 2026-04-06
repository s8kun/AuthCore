<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_event_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('project_user_id')->nullable()->constrained('project_users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('event_type');
            $table->string('endpoint')->nullable();
            $table->string('method')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->boolean('success')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
            $table->index(['project_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_event_logs');
    }
};
