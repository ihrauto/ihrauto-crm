<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Override newModel so factories can populate protected fields
     * (`tenant_id`, `role`, `is_active`, `email_verified_at`) that are
     * excluded from the User model's $fillable for security. Tests should
     * still be able to set these via `create(['tenant_id' => X, ...])`.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newModel(array $attributes = []): User
    {
        $user = new User;
        if (! empty($attributes)) {
            $user->forceFill($attributes);
        }

        return $user;
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
