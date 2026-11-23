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

    /** Spanish NIF, the same pattern as abianv2 (App\Models\Persona::PATRON_NIF). */
    private const PATRON_NIF = '/^[0-9]{8}[TRWAGMYFPDXBNJZSQVHLCKE]$/';

    /** @var array<string, array<int, string>> */
    private array $catalogos = [];

    /** @var array<int, array{pais_id: ?int, dni: ?string}> Abian persona id => origin data */
    private array $origenPersonas = [];

    public function programa(): Programa
    {
        return Programa::Abian;
    }

    protected function leer(): void
    {
        $this->cargarCatalogos();
        $this->leerEmpresas();
        $this->leerPersonas();
        $this->leerExpedientes();
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
            'tipos_reunion' => 'nombre',
            'users' => 'name',
        ];

        foreach ($tablas as $tabla => $columna) {
            $this->catalogos[$tabla] = $this->origen()->table($tabla)->pluck($columna, 'id')->all();
        }

        $this->catalogos['paises'] = $this->origen()->table('paises')->pluck('nombre', 'id')->all();
        $this->catalogos['area_de_pais'] = $this->origen()->table('paises')
            ->join('areas_mundo', 'areas_mundo.id', '=', 'paises.area_mundo_id')
            ->pluck('areas_mundo.nombre', 'paises.id')
            ->all();
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
                $this->origenPersonas[$persona->id] = ['pais_id' => $persona->pais_id, 'dni' => $persona->dni];

                $this->registrarPersona('personas', $persona->id, $persona->dni, [
                    'nombre' => $persona->nombre,
                    'apellido1' => $persona->apellido1,
                    'apellido2' => $persona->apellido2,
                    'sexo' => $this->sexo($persona->sexo),
                    'fecha_nacimiento' => $persona->fecha_nacimiento,
                    'telefono' => $persona->telefono,
                    'email' => $persona->email,
                    'poblacion' => $this->catalogo('poblaciones', $persona->poblacion_id),
                    'pais_origen' => $this->catalogo('paises', $persona->pais_id),
                    'area_origen' => $this->catalogo('area_de_pais', $persona->pais_id),
                ], $persona->updated_at);
            }
        });
    }

    /**
     * One participation per recepción (an Abian expediente): the call that originated it, its
     * business plan with the meetings, and the company. Two recepciones count as two, even
     * when they share a plan. Plans without any recepción (old ones) are a participation of
     * their own.
     */
    private function leerExpedientes(): void
    {
        /** @var array<int, array<int, int>> $personasPorRecepcion recepción id => [intranet persona id => Abian persona id] */
        $personasPorRecepcion = [];

        foreach ($this->origen()->table('persona_recepcion')->orderBy('orden')->get(['recepcion_id', 'persona_id']) as $vinculo) {
            $personaId = $this->personaDe('personas', $vinculo->persona_id);

            if ($personaId !== null) {
                $personasPorRecepcion[$vinculo->recepcion_id][$personaId] = $vinculo->persona_id;
            }
        }

        $llamadasPorRecepcion = $this->origen()->table('llamadas')
            ->whereNull('deleted_at')
            ->whereNotNull('recepcion_id')
            ->orderBy('fecha')
            ->get(['recepcion_id', 'fecha', 'telefonica'])
            ->groupBy('recepcion_id');

        $reunionesPorPlan = $this->origen()->table('reuniones')
            ->whereNull('deleted_at')
            ->orderBy('fecha')
            ->get(['plan_id', 'tipo_reunion_id', 'fecha', 'celebrada', 'no_presentado'])
            ->groupBy('plan_id');

        $planes = $this->origen()->table('planes')->get()->keyBy('id');

        /** @var array<int, true> $planesConRecepcion */
        $planesConRecepcion = [];

        $this->origen()->table('recepciones')->whereNull('deleted_at')->orderBy('id')->chunk(1000, function (Collection $recepciones) use ($personasPorRecepcion, $llamadasPorRecepcion, $reunionesPorPlan, $planes, &$planesConRecepcion): void {
            foreach ($recepciones as $recepcion) {
                $plan = $recepcion->plan_id === null ? null : $planes->get($recepcion->plan_id);

                if ($plan !== null) {
                    $planesConRecepcion[$plan->id] = true;
                }

                $this->registrarParticipacion(TipoParticipacion::Expediente, $recepcion->id, [
                    'referencia' => $this->codigoExpediente($recepcion),
                    'titulo' => $recepcion->nombre,
                    'fecha_inicio' => $recepcion->fecha_solicitud,
                    'fecha_fin' => $plan !== null && $plan->cerrado ? $plan->fecha_final : null,
                    'estado' => $this->estadoExpediente($recepcion, $plan),
                    'responsable' => $this->catalogo('users', $recepcion->consultor_id),
                    'datos' => [
                        'hora' => $recepcion->hora_solicitud === null ? null : substr($recepcion->hora_solicitud, 0, 5),
                        'resultado' => $recepcion->resultado,
                        'resultado_etiqueta' => self::RESULTADOS_RECEPCION[$recepcion->resultado] ?? null,
                        'actividad' => $this->catalogo('actividades', $recepcion->actividad_id),
                        'situacion_laboral' => $this->catalogo('situaciones_laborales', $recepcion->situacion_laboral_id),
                        'canal_conocimiento' => $this->catalogo('canales_conocimiento', $recepcion->canal_conocimiento_id),
                        'llamadas' => ($llamadasPorRecepcion->get($recepcion->id) ?? collect())
                            ->map(fn (object $llamada) => ['fecha' => $llamada->fecha, 'telefonica' => (bool) $llamada->telefonica])
                            ->values()->all(),
                        'plan' => $plan === null ? null : $this->datosPlan($plan, $reunionesPorPlan),
                    ],
                ],
                    array_keys($personasPorRecepcion[$recepcion->id] ?? []),
                    $this->empresaDe('empresas', $plan?->empresa_id),
                    array_map(fn (int $personaAbian) => $this->esMigrante($personaAbian, $recepcion->fecha_solicitud), $personasPorRecepcion[$recepcion->id] ?? []),
                );
            }
        });

        foreach ($planes as $plan) {
            if (isset($planesConRecepcion[$plan->id])) {
                continue;
            }

            $this->registrarParticipacion(TipoParticipacion::PlanNegocio, $plan->id, [
                'titulo' => $plan->nombre,
                'fecha_inicio' => $plan->fecha_inicio ?? $plan->fecha_asignacion,
                'fecha_fin' => $plan->cerrado ? $plan->fecha_final : null,
                'estado' => $this->estadoPlan($plan),
                'responsable' => $this->catalogo('users', $plan->consultor_id),
                'datos' => ['plan' => $this->datosPlan($plan, $reunionesPorPlan)],
            ], [], $this->empresaDe('empresas', $plan->empresa_id));
        }
    }

    /**
     * Business plan as shown in the ficha. The content of the meetings is not copied: in
     * abianv2 only the plan's consultant and management may read it.
     *
     * @param  Collection<int|string, Collection<int, object>>  $reunionesPorPlan
     * @return array<string, mixed>
     */
    private function datosPlan(object $plan, Collection $reunionesPorPlan): array
    {
        return [
            'id' => $plan->id,
            'nombre' => $plan->nombre,
            'consultor' => $this->catalogo('users', $plan->consultor_id),
            'tipo_plan' => $this->catalogo('tipos_plan', $plan->tipo_plan_id),
            'actividad' => $this->catalogo('actividades', $plan->actividad_id),
            'fecha_asignacion' => $plan->fecha_asignacion,
            'fecha_inicio' => $plan->fecha_inicio,
            'fecha_final' => $plan->fecha_final,
            'cerrado' => (bool) $plan->cerrado,
            'baja' => in_array((int) $plan->baja, [1, 2], true),
            'finalizado' => (bool) $plan->finalizado,
            'motivo_baja' => $this->catalogo('motivos_baja', $plan->motivo_baja_id),
            'reuniones' => ($reunionesPorPlan->get($plan->id) ?? collect())
                ->map(fn (object $reunion) => [
                    'fecha' => $reunion->fecha,
                    'tipo' => $this->catalogo('tipos_reunion', $reunion->tipo_reunion_id),
                    'celebrada' => (bool) $reunion->celebrada,
                    'no_presentado' => (bool) $reunion->no_presentado,
                ])->values()->all(),
        ];
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
     * Migrant by the fixed abianv2 rule: from the cut-off date, having a country of origin;
     * before it, having a document that is not a Spanish NIF.
     */
    private function esMigrante(int $personaAbian, ?string $fechaRecepcion): ?bool
    {
        $origen = $this->origenPersonas[$personaAbian] ?? null;

        if ($origen === null || $fechaRecepcion === null) {
            return null;
        }

        if ($fechaRecepcion >= config('intranet.abian_migrante_por_pais_desde')) {
            return $origen['pais_id'] !== null;
        }

        return filled($origen['dni']) && preg_match(self::PATRON_NIF, $origen['dni']) !== 1;
    }

    /**
     * Expediente number as abianv2 shows it: AB-2026-00012345 (NG- unless the result is Abian).
     */
    private function codigoExpediente(object $recepcion): string
    {
        return sprintf('%s-%04d-%08d', (int) $recepcion->resultado === 2 ? 'AB' : 'NG', $recepcion->ano, $recepcion->id);
    }

    /**
     * Status of the expediente, with the same rules as the quick search of abianv2.
     */
    private function estadoExpediente(object $recepcion, ?object $plan): string
    {
        if ($plan !== null) {
            return $this->estadoPlan($plan);
        }

        return match ((int) $recepcion->resultado) {
            0 => 'No valorado',
            1 => 'Baja en recepción',
            666 => 'No acude',
            default => 'No asignado a plan de negocio',
        };
    }

    /**
     * Baja 1 and 2 both mean BAJA (abianv2 decision).
     */
    private function estadoPlan(object $plan): string
    {
        return match (true) {
            in_array((int) $plan->baja, [1, 2], true) => 'Baja',
            (bool) $plan->cerrado => 'Cerrado',
            (bool) $plan->finalizado => 'Finalizado sin cerrar',
            default => 'En curso',
        };
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
