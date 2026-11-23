<?php

namespace Database\Factories;

use App\Enums\Sexo;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Persona>
 */
class PersonaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'documento' => fake()->unique()->numerify('########').fake()->randomLetter(),
            'nombre' => fake()->firstName(),
            'apellido1' => fake()->lastName(),
            'apellido2' => fake()->lastName(),
            'sexo' => fake()->randomElement(Sexo::cases()),
            'fecha_nacimiento' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'telefono' => fake()->numerify('6########'),
            'email' => fake()->unique()->safeEmail(),
            'poblacion' => fake()->city(),
        ];
    }
}
