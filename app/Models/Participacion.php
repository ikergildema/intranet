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
        'abian.expediente' => '/recepciones/%d',
        'abian.plan_negocio' => '/planes/%d',
        'abian.jornada' => '/jornada/inscripciones',
        'anezka.inscripcion' => '/participants/%d',
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

    /**
     * Badge color of the status, with the same meaning as in abianv2.
     */
    public function colorEstado(): string
    {
        return match ($this->estado) {
            'En curso', 'Inscrito/a', 'Seleccionado' => 'success',
            'Finalizado sin cerrar', 'No asignado a plan de negocio', 'Reserva', 'Rellamar', 'No contesta' => 'warning',
            'Baja', 'Baja en recepción', 'No valorado', 'No seleccionado', 'No seleccionable', 'No acude' => 'danger',
            'Citado a charla', 'Acude a charla', 'Citado a selección', 'Acude a selección' => 'info',
            default => 'gray',
        };
    }

    /**
     * Badge color of the result of an Abian recepción.
     */
    public function colorResultado(): string
    {
        return match ($this->datos['resultado'] ?? null) {
            0 => 'warning',
            1, 666 => 'danger',
            2, 4 => 'success',
            3 => 'info',
            default => 'gray',
        };
    }
}
