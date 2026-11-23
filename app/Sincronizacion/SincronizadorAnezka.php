<?php

namespace App\Sincronizacion;

use App\Enums\Programa;
use App\Enums\Sexo;
use App\Enums\TipoParticipacion;
use Illuminate\Support\Collection;

/**
 * Anezka: registrations for the employment shuttles (lanzaderas). Each registration is one
 * participation, in the convocatoria of its year and, once assigned by hand, in a lanzadera.
 */
class SincronizadorAnezka extends Sincronizador
{
    /**
     * Document types that are not Spanish: the person counts as migrant, the same rule Abian
     * applied before it recorded the country of origin.
     *
     * @var list<string>
     */
    private const DOCUMENTOS_EXTRANJEROS = ['nie', 'other'];

    /** @var array<string, array<int, string>> */
    private array $catalogos = [];

    /** @var Collection<int, object> */
    private Collection $lanzaderas;

    public function programa(): Programa
    {
        return Programa::Anezka;
    }

    protected function leer(): void
    {
        foreach (['municipalities', 'genders', 'participant_statuses', 'employment_situations', 'education_levels', 'convocatorias'] as $tabla) {
            $columna = $tabla === 'convocatorias' ? 'name' : 'name_es';
            $this->catalogos[$tabla] = $this->origen()->table($tabla)->pluck($columna, 'id')->all();
        }

        $this->catalogos['users'] = $this->origen()->table('users')->pluck('name', 'id')->all();
        $this->lanzaderas = $this->origen()->table('lanzaderas')->get()->keyBy('id');

        $this->origen()->table('participants')->whereNull('deleted_at')->orderBy('id')->chunk(1000, function (Collection $inscripciones): void {
            foreach ($inscripciones as $inscripcion) {
                $personaId = $this->registrarPersona('participants', $inscripcion->id, $inscripcion->document_number, [
                    'nombre' => $inscripcion->first_name,
                    'apellido1' => $inscripcion->last_name_1,
                    'apellido2' => $inscripcion->last_name_2,
                    'sexo' => $this->sexo($inscripcion->gender_id),
                    'fecha_nacimiento' => $inscripcion->birth_date,
                    'telefono' => $inscripcion->phone,
                    'email' => $inscripcion->email,
                    'poblacion' => $this->catalogo('municipalities', $inscripcion->municipality_id),
                ], $inscripcion->updated_at);

                $lanzadera = $inscripcion->lanzadera_id === null ? null : $this->lanzaderas->get($inscripcion->lanzadera_id);

                $this->registrarParticipacion(TipoParticipacion::Inscripcion, $inscripcion->id, [
                    'referencia' => $this->catalogo('convocatorias', $inscripcion->convocatoria_id),
                    'titulo' => $lanzadera?->name,
                    'fecha_inicio' => $inscripcion->registered_at === null ? null : substr($inscripcion->registered_at, 0, 10),
                    'fecha_fin' => $lanzadera?->ends_on,
                    'estado' => $this->catalogo('participant_statuses', $inscripcion->participant_status_id),
                    'responsable' => $lanzadera === null ? null : $this->catalogo('users', $lanzadera->coordinator_id),
                    'datos' => [
                        'lanzadera' => $lanzadera === null ? null : [
                            'nombre' => $lanzadera->name,
                            'lugar' => $lanzadera->location,
                            'inicio' => $lanzadera->starts_on,
                            'fin' => $lanzadera->ends_on,
                        ],
                        'charla' => $inscripcion->talk_at,
                        'entrevista' => $inscripcion->interview_at,
                        'situacion_laboral' => $this->catalogo('employment_situations', $inscripcion->employment_situation_id),
                        'nivel_estudios' => $this->catalogo('education_levels', $inscripcion->education_level_id),
                    ],
                ], [$personaId], migrantes: [$personaId => $this->esMigrante($inscripcion->document_type)]);
            }
        });
    }

    private function catalogo(string $tabla, int|string|null $id): ?string
    {
        return $id === null ? null : ($this->catalogos[$tabla][$id] ?? null);
    }

    private function esMigrante(?string $tipoDocumento): ?bool
    {
        return match (true) {
            $tipoDocumento === 'dni' => false,
            in_array($tipoDocumento, self::DOCUMENTOS_EXTRANJEROS, true) => true,
            default => null,
        };
    }

    /**
     * Anezka genders: 1 Hombre, 2 Mujer, 3 No binario.
     */
    private function sexo(?int $genero): ?string
    {
        return match ($genero) {
            1 => Sexo::Hombre->value,
            2 => Sexo::Mujer->value,
            3 => Sexo::NoBinario->value,
            default => null,
        };
    }
}
