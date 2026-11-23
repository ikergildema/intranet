<?php

namespace App\Sincronizacion;

use App\Enums\Programa;
use App\Enums\Sexo;
use App\Enums\TipoParticipacion;
use Illuminate\Support\Collection;

/**
 * Abian (abianv2): business-idea reception, business plans with their consultant, and the
 * companies created, plus the registrations for the entrepreneurship day.
 */
class SincronizadorAbian extends Sincronizador
{
    /**
     * Labels of `recepciones.resultado` (App\Enums\ResultadoRecepcion in abianv2).
     *
     * @var array<int, string>
     */
    private const RESULTADOS_RECEPCION = [
        0 => 'Sin valorar',
        1 => 'Baja',
        2 => 'Abian',
        3 => 'Reactivación comarcal',
        4 => 'Negozion',
        666 => 'No acude',
    ];

    /** @var array<string, array<int, string>> */
    private array $catalogos = [];

    public function programa(): Programa
    {
        return Programa::Abian;
    }

    protected function leer(): void
    {
        $this->cargarCatalogos();
        $this->leerEmpresas();
        $this->leerPersonas();
        $this->leerRecepcionesYPlanes();
        $this->leerInscripcionesJornada();
    }

    private function cargarCatalogos(): void
    {
        $tablas = [
            'poblaciones' => 'nombre',
            'actividades' => 'nombre',
            'formas_juridicas' => 'nombre',
            'estados_empresa' => 'nombre',
            'tipos_plan' => 'nombre',
            'motivos_baja' => 'nombre',
            'situaciones_laborales' => 'nombre',
            'canales_conocimiento' => 'nombre',
            'users' => 'name',
        ];

        foreach ($tablas as $tabla => $columna) {
            $this->catalogos[$tabla] = $this->origen()->table($tabla)->pluck($columna, 'id')->all();
        }
    }

    private function catalogo(string $tabla, int|string|null $id): ?string
    {
        return $id === null ? null : ($this->catalogos[$tabla][$id] ?? null);
    }

    private function leerEmpresas(): void
    {
        $this->origen()->table('empresas')->orderBy('id')->chunk(1000, function (Collection $empresas): void {
            foreach ($empresas as $empresa) {
                $this->registrarEmpresa('empresas', $empresa->id, $empresa->cif, [
                    'nombre' => $empresa->nombre,
                    'sector' => $this->catalogo('actividades', $empresa->actividad_id) ?? $empresa->actividad_texto,
                    'forma_juridica' => $this->catalogo('formas_juridicas', $empresa->forma_juridica_id),
                    'poblacion' => $this->catalogo('poblaciones', $empresa->poblacion_id),
                    'fecha_alta' => $empresa->fecha_alta,
                    'estado' => $this->catalogo('estados_empresa', $empresa->estado_empresa_id),
                    'telefono' => $empresa->telefono,
                    'email' => $empresa->email,
                ], $empresa->updated_at);
            }
        });
    }

    private function leerPersonas(): void
    {
        $this->origen()->table('personas')->whereNull('deleted_at')->orderBy('id')->chunk(1000, function (Collection $personas): void {
            foreach ($personas as $persona) {
                $this->registrarPersona('personas', $persona->id, $persona->dni, [
                    'nombre' => $persona->nombre,
                    'apellido1' => $persona->apellido1,
                    'apellido2' => $persona->apellido2,
                    'sexo' => $this->sexo($persona->sexo),
                    'fecha_nacimiento' => $persona->fecha_nacimiento,
                    'telefono' => $persona->telefono,
                    'email' => $persona->email,
                    'poblacion' => $this->catalogo('poblaciones', $persona->poblacion_id),
                ], $persona->updated_at);
            }
        });
    }

    private function leerRecepcionesYPlanes(): void
    {
        /** @var array<int, list<int>> $personasPorRecepcion */
        $personasPorRecepcion = [];

        foreach ($this->origen()->table('persona_recepcion')->get(['recepcion_id', 'persona_id']) as $vinculo) {
            $personaId = $this->personaDe('personas', $vinculo->persona_id);

            if ($personaId !== null) {
                $personasPorRecepcion[$vinculo->recepcion_id][] = $personaId;
            }
        }

        /** @var array<int, list<int>> $personasPorPlan */
        $personasPorPlan = [];

        /** @var array<int, string> $expedientePorPlan */
        $expedientePorPlan = [];

        $this->origen()->table('recepciones')->whereNull('deleted_at')->orderBy('id')->chunk(1000, function (Collection $recepciones) use ($personasPorRecepcion, &$personasPorPlan, &$expedientePorPlan): void {
            foreach ($recepciones as $recepcion) {
                $personas = $personasPorRecepcion[$recepcion->id] ?? [];

                $this->registrarParticipacion(TipoParticipacion::Recepcion, $recepcion->id, [
                    'referencia' => $recepcion->nexpediente,
                    'titulo' => $recepcion->nombre,
                    'fecha_inicio' => $recepcion->fecha_solicitud,
                    'estado' => self::RESULTADOS_RECEPCION[$recepcion->resultado] ?? null,
                    'responsable' => $this->catalogo('users', $recepcion->consultor_id),
                    'datos' => [
                        'resultado' => $recepcion->resultado,
                        'actividad' => $this->catalogo('actividades', $recepcion->actividad_id),
                        'situacion_laboral' => $this->catalogo('situaciones_laborales', $recepcion->situacion_laboral_id),
                        'canal_conocimiento' => $this->catalogo('canales_conocimiento', $recepcion->canal_conocimiento_id),
                    ],
                ], $personas);

                if ($recepcion->plan_id !== null) {
                    $personasPorPlan[$recepcion->plan_id] = [...($personasPorPlan[$recepcion->plan_id] ?? []), ...$personas];
                    $expedientePorPlan[$recepcion->plan_id] ??= $recepcion->nexpediente;
                }
            }
        });

        $reunionesCelebradas = $this->origen()->table('reuniones')
            ->whereNull('deleted_at')
            ->where('celebrada', true)
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id')
            ->all();

        $this->origen()->table('planes')->orderBy('id')->chunk(1000, function (Collection $planes) use ($personasPorPlan, $expedientePorPlan, $reunionesCelebradas): void {
            foreach ($planes as $plan) {
                $this->registrarParticipacion(TipoParticipacion::PlanNegocio, $plan->id, [
                    'referencia' => $expedientePorPlan[$plan->id] ?? null,
                    'titulo' => $plan->nombre,
                    'fecha_inicio' => $plan->fecha_inicio ?? $plan->fecha_asignacion,
                    'fecha_fin' => $plan->fecha_final,
                    'estado' => $this->estadoPlan($plan),
                    'responsable' => $this->catalogo('users', $plan->consultor_id),
                    'datos' => [
                        'tipo_plan' => $this->catalogo('tipos_plan', $plan->tipo_plan_id),
                        'actividad' => $this->catalogo('actividades', $plan->actividad_id),
                        'motivo_baja' => $this->catalogo('motivos_baja', $plan->motivo_baja_id),
                        'finalizado' => (bool) $plan->finalizado,
                        'reuniones_celebradas' => (int) ($reunionesCelebradas[$plan->id] ?? 0),
                    ],
                ], $personasPorPlan[$plan->id] ?? [], $this->empresaDe('empresas', $plan->empresa_id));
            }
        });
    }

    private function leerInscripcionesJornada(): void
    {
        $this->origen()->table('inscripciones_jornada')->orderBy('id')->chunk(1000, function (Collection $inscripciones): void {
            foreach ($inscripciones as $inscripcion) {
                $personaId = $this->registrarPersona('inscripciones_jornada', $inscripcion->id, $inscripcion->dni, [
                    'nombre' => $inscripcion->nombre,
                    'apellido1' => $inscripcion->apellido1,
                    'apellido2' => $inscripcion->apellido2,
                    'sexo' => $this->sexo($inscripcion->sexo),
                    'fecha_nacimiento' => $inscripcion->fecha_nacimiento,
                    'telefono' => $inscripcion->telefono,
                    'email' => $inscripcion->email,
                    'poblacion' => $inscripcion->localidad,
                ], $inscripcion->updated_at);

                $this->registrarParticipacion(TipoParticipacion::Jornada, $inscripcion->id, [
                    'titulo' => $inscripcion->jornada,
                    'fecha_inicio' => $inscripcion->fecha_jornada,
                    'estado' => 'Inscrito/a',
                    'datos' => ['actividad' => $inscripcion->actividad],
                ], [$personaId]);
            }
        });
    }

    /**
     * Same rules as abianv2: baja 1 and 2 both mean BAJA; otherwise closed or in progress.
     */
    private function estadoPlan(object $plan): string
    {
        if (in_array((int) $plan->baja, [1, 2], true)) {
            return 'Baja';
        }

        return $plan->cerrado ? 'Cerrado' : 'En curso';
    }

    private function sexo(?int $codigo): ?string
    {
        return match ($codigo) {
            1 => Sexo::Hombre->value,
            2 => Sexo::Mujer->value,
            default => null,
        };
    }
}
