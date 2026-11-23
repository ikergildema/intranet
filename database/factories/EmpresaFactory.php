<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cif' => 'B'.fake()->unique()->numerify('########'),
            'nombre' => fake()->company(),
            'sector' => fake()->word(),
            'poblacion' => fake()->city(),
            'fecha_alta' => fake()->date(),
        ];
    }
}
