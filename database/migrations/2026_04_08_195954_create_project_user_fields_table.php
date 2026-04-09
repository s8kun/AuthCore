<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_user_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('label', 120);
            $table->string('type', 50);
            $table->string('description', 255)->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->json('default_value')->nullable();
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('ui_settings')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_nullable')->default(true);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_sortable')->default(false);
            $table->boolean('show_in_admin_form')->default(true);
            $table->boolean('show_in_api')->default(true);
            $table->boolean('show_in_table')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'key']);
            $table->index(['project_id', 'is_active', 'sort_order']);
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user_fields');
    }
};
