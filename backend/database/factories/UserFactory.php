<?php

namespace Database\Factories;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('SenhaTeste123'),
            'remember_token' => Str::random(10),
            'setor_id' => fn () => Setor::query()->create(['nome' => 'Setor '.Str::random(8)])->id,
            'perfil' => User::PERFIL_COLABORADOR,
            'ativo' => true,
        ];
    }

    public function perfil(string $perfil): static
    {
        return $this->state(['perfil' => $perfil]);
    }

    public function noSetor(Setor $setor): static
    {
        return $this->state(['setor_id' => $setor->id]);
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }
}
