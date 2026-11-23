<?php

namespace App\Models;

use App\Enums\Programa;
use App\Enums\TipoParticipacion;
use Database\Factories\ParticipacionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One pass of one or more people (and optionally a company) through a DEMA program.
 */
class Participacion extends Model
{
    /** @use HasFactory<ParticipacionFactory> */
    use HasFactory;

    protected $table = 'participaciones';

    protected $guarded = ['id'];

    /**
     * Path of the original record in its program application, by type.
     *
     * @var array<string, string>
     */
    private const RUTAS_ORIGEN = [
        'abian.recepcion' => '/recepciones/%d',
        'abian.plan_negocio' => '/planes/%d',
        'abian.jornada' => '/jornada/inscripciones',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'programa' => Programa::class,
            'tipo' => TipoParticipacion::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'datos' => 'array',
            'sincronizado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Persona, $this>
     */
    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(Persona::class, 'participacion_persona');
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Link to the original record in the program application, when its path is known.
     */
    public function urlOrigen(): ?string
    {
        $ruta = self::RUTAS_ORIGEN[$this->programa->value.'.'.$this->tipo->value] ?? null;

        return $ruta === null ? null : $this->programa->url().sprintf($ruta, $this->origen_id);
    }
}
