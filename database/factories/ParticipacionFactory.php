<?php

namespace Database\Factories;

use App\Enums\Programa;
use App\Enums\TipoParticipacion;
use App\Models\Participacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Participacion>
 */
class ParticipacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'programa' => Programa::Abian,
            'tipo' => TipoParticipacion::Expediente,
            'origen_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'titulo' => fake()->sentence(3),
            'fecha_inicio' => fake()->dateTimeBetween('-3 years'),
            'estado' => 'Abian',
            'sincronizado_at' => now(),
        ];
    }
}
