<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectUser;
use App\Models\ProjectUserField;
use App\Models\ProjectUserFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectUserFieldValue>
 */
class ProjectUserFieldValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = Project::factory();

        return [
            'project_id' => $project,
            'project_user_id' => ProjectUser::factory()->for($project, 'project'),
            'project_user_field_id' => ProjectUserField::factory()->for($project, 'project'),
            'value_string' => fake()->word(),
            'value_text' => null,
            'value_integer' => null,
            'value_decimal' => null,
            'value_boolean' => null,
            'value_date' => null,
            'value_datetime' => null,
            'value_json' => null,
            'value_hash' => null,
            'unique_scope_key' => null,
        ];
    }
}
