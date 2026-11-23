<?php

namespace App\Models;

use App\Enums\Sexo;
use Database\Factories\PersonaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person attended by DEMA, merged across programs by normalized document.
 */
class Persona extends Model
{
    /** @use HasFactory<PersonaFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sexo' => Sexo::class,
            'fecha_nacimiento' => 'date',
            'datos_actualizados_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Participacion, $this>
     */
    public function participaciones(): BelongsToMany
    {
        return $this->belongsToMany(Participacion::class, 'participacion_persona');
    }

    /**
     * @return HasMany<PersonaOrigen, $this>
     */
    public function origenes(): HasMany
    {
        return $this->hasMany(PersonaOrigen::class);
    }

    public function nombreCompleto(): string
    {
        return trim(implode(' ', array_filter([$this->nombre, $this->apellido1, $this->apellido2])));
    }
}
