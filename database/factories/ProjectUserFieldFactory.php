<?php

namespace Database\Factories;

use App\Enums\ProjectUserFieldType;
use App\Models\Project;
use App\Models\ProjectUserField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectUserField>
 */
class ProjectUserFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = Str::snake(fake()->unique()->words(asText: true));

        return [
            'project_id' => Project::factory(),
            'key' => $key,
            'label' => Str::headline($key),
            'type' => ProjectUserFieldType::StringType,
            'description' => null,
            'placeholder' => null,
            'default_value' => null,
            'options' => null,
            'validation_rules' => [],
            'ui_settings' => [],
            'is_required' => false,
            'is_nullable' => true,
            'is_unique' => false,
            'is_searchable' => false,
            'is_filterable' => false,
            'is_sortable' => false,
            'show_in_admin_form' => true,
            'show_in_api' => true,
            'show_in_table' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Indicate that the field is an enum.
     *
     * @param  list<string>  $options
     */
    public function enum(array $options = ['pending', 'approved', 'cancelled']): static
    {
        return $this->state(fn (): array => [
            'type' => ProjectUserFieldType::Enum,
            'options' => $options,
            'default_value' => $options[0] ?? null,
        ]);
    }

    /**
     * Indicate that the field is unique.
     */
    public function uniqueField(): static
    {
        return $this->state(fn (): array => [
            'is_unique' => true,
        ]);
    }

    /**
     * Indicate that the field is required.
     */
    public function required(): static
    {
        return $this->state(fn (): array => [
            'is_required' => true,
            'is_nullable' => false,
        ]);
    }
}
