<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<ProjectUser>
 */
class ProjectUserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->e164PhoneNumber(),
            'email_verified_at' => now(),
            'last_login_at' => null,
            'is_active' => true,
            'is_ghost' => false,
            'claimed_at' => null,
            'invited_at' => null,
            'ghost_source' => null,
            'must_set_password' => false,
            'must_verify_email' => false,
        ];
    }

    /**
     * Indicate that the project user is a ghost account.
     */
    public function ghost(): static
    {
        return $this->state(fn () => [
            'password' => null,
            'is_ghost' => true,
            'invited_at' => now(),
            'claimed_at' => null,
            'must_set_password' => true,
        ]);
    }

    /**
     * Indicate that the project user is pending email verification.
     */
    public function pendingEmailVerification(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
            'must_verify_email' => true,
        ]);
    }
}
