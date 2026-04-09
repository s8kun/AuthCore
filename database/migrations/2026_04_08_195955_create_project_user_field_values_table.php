<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_user_field_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('project_user_id')->constrained('project_users')->cascadeOnDelete();
            $table->foreignUuid('project_user_field_id')->constrained('project_user_fields')->cascadeOnDelete();
            $table->string('value_string')->nullable();
            $table->longText('value_text')->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->decimal('value_decimal', 30, 10)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->json('value_json')->nullable();
            $table->char('value_hash', 64)->nullable();
            $table->uuid('unique_scope_key')->nullable();
            $table->timestamps();

            $table->unique(['project_user_id', 'project_user_field_id'], 'pufv_user_field_unique');
            $table->unique(['unique_scope_key', 'value_hash'], 'pufv_scope_hash_unique');
            $table->index(['project_user_field_id', 'value_string'], 'pufv_field_string_index');
            $table->index(['project_user_field_id', 'value_integer'], 'pufv_field_integer_index');
            $table->index(['project_user_field_id', 'value_decimal'], 'pufv_field_decimal_index');
            $table->index(['project_user_field_id', 'value_boolean'], 'pufv_field_boolean_index');
            $table->index(['project_user_field_id', 'value_date'], 'pufv_field_date_index');
            $table->index(['project_user_field_id', 'value_datetime'], 'pufv_field_datetime_index');
            $table->index(['project_id', 'project_user_id'], 'pufv_project_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user_field_values');
    }
};
