<?php

namespace App\Sincronizacion;

use App\Enums\Programa;
use App\Enums\TipoParticipacion;
use App\Models\Sincronizacion;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Copies the people, companies and participations of one program application into the intranet.
 *
 * Every run marks what it finds with the same timestamp and, at the end, removes whatever of that
 * program it did not find again, so deletions in the source disappear too. The whole run is one
 * transaction: if it fails, the intranet keeps the data of the previous run.
 */
abstract class Sincronizador
{
    private const TAMANO_LOTE = 500;

    protected Carbon $marca;

    /** @var array<string, int> normalized document => persona id */
    private array $personasPorDocumento = [];

    /** @var array<int, array{documento: ?string, actualizado: ?string}> */
    private array $personas = [];

    /** @var array<string, int> "tabla:id" => persona id */
    private array $origenesPersona = [];

    /** @var array<string, int> normalized CIF => empresa id */
    private array $empresasPorCif = [];

    /** @var array<int, array{cif: ?string, actualizado: ?string}> */
    private array $empresas = [];

    /** @var array<string, int> "tabla:id" => empresa id */
    private array $origenesEmpresa = [];

    /** @var list<array<string, mixed>> */
    private array $filasOrigenPersona = [];

    /** @var list<array<string, mixed>> */
    private array $filasOrigenEmpresa = [];

    /** @var list<array{fila: array<string, mixed>, personas: list<int>, migrantes: array<int, ?bool>}> */
    private array $participacionesPendientes = [];

    /** @var array<string, int> */
    private array $resumen = [];

    abstract public function programa(): Programa;

    /**
     * Read the program application and register its people, companies and participations.
     */
    abstract protected function leer(): void;

    /**
     * Run the sync and log it.
     */
    public function ejecutar(): Sincronizacion
    {
        $this->marca = Carbon::now()->startOfSecond();

        $registro = Sincronizacion::create([
            'programa' => $this->programa(),
            'iniciada_at' => $this->marca,
            'estado' => Sincronizacion::ESTADO_EN_CURSO,
        ]);

        try {
            DB::transaction(function (): void {
                $this->cargarExistentes();
                $this->leer();
                $this->guardarOrigenes();
                $this->guardarParticipaciones();
                $this->eliminarDesaparecidos();
            });
        } catch (Throwable $error) {
            $registro->update([
                'estado' => Sincronizacion::ESTADO_FALLIDA,
                'finalizada_at' => Carbon::now(),
                'error' => mb_substr($error->getMessage(), 0, 5000),
            ]);

            throw $error;
        }

        $registro->update([
            'estado' => Sincronizacion::ESTADO_CORRECTA,
            'finalizada_at' => Carbon::now(),
            'resumen' => $this->resumen,
        ]);

        return $registro;
    }

    /**
     * Read-only connection to the program application.
     */
    protected function origen(): ConnectionInterface
    {
        return DB::connection($this->programa()->value);
    }

    /**
     * Register a source person and return the id of the intranet persona it belongs to.
     *
     * Records with the same normalized document share one persona; records without a usable
     * document keep a persona of their own. The personal data come from the most recently
     * updated source record.
     *
     * @param  array<string, mixed>  $datos  Persona columns (nombre, apellido1, sexo...)
     */
    protected function registrarPersona(string $tabla, int $origenId, ?string $documento, array $datos, ?string $actualizadoAt): int
    {
        $normalizado = Documento::persona($documento);
        $clave = $tabla.':'.$origenId;
        $personaId = $normalizado === null ? null : ($this->personasPorDocumento[$normalizado] ?? null);

        if ($personaId === null && isset($this->origenesPersona[$clave])) {
            $anterior = $this->origenesPersona[$clave];

            if ($normalizado === null || $this->personas[$anterior]['documento'] === null) {
                $personaId = $anterior;
            }
        }

        $valores = $datos + ['documento_original' => $documento];

        if ($personaId === null) {
            $personaId = DB::table('personas')->insertGetId($valores + [
                'documento' => $normalizado,
                'datos_actualizados_at' => $actualizadoAt,
                'created_at' => $this->marca,
                'updated_at' => $this->marca,
            ]);
            $this->personas[$personaId] = ['documento' => $normalizado, 'actualizado' => $actualizadoAt];
            $this->contar('personas_nuevas');
        } elseif ($this->esMasReciente($actualizadoAt, $this->personas[$personaId]['actualizado'])) {
            $documentoFinal = $this->personas[$personaId]['documento'] ?? $normalizado;

            DB::table('personas')->where('id', $personaId)->update($valores + [
                'documento' => $documentoFinal,
                'datos_actualizados_at' => $actualizadoAt,
                'updated_at' => $this->marca,
            ]);
            $this->personas[$personaId] = ['documento' => $documentoFinal, 'actualizado' => $actualizadoAt];
        }

        if ($this->personas[$personaId]['documento'] === null && $normalizado !== null) {
            DB::table('personas')->where('id', $personaId)->update(['documento' => $normalizado, 'updated_at' => $this->marca]);
            $this->personas[$personaId]['documento'] = $normalizado;
        }

        if ($this->personas[$personaId]['documento'] !== null) {
            $this->personasPorDocumento[$this->personas[$personaId]['documento']] = $personaId;
        }

        $this->origenesPersona[$clave] = $personaId;
        $this->filasOrigenPersona[] = [
            'persona_id' => $personaId,
            'programa' => $this->programa()->value,
            'origen_tabla' => $tabla,
            'origen_id' => $origenId,
            'sincronizado_at' => $this->marca,
        ];
        $this->contar('personas_leidas');

        return $personaId;
    }

    /**
     * Intranet persona of a source record already registered in this run.
     */
    protected function personaDe(string $tabla, int $origenId): ?int
    {
        return $this->origenesPersona[$tabla.':'.$origenId] ?? null;
    }

    /**
     * Register a source company and return the id of the intranet empresa it belongs to.
     *
     * @param  array<string, mixed>  $datos  Empresa columns (nombre, sector...)
     */
    protected function registrarEmpresa(string $tabla, int $origenId, ?string $cif, array $datos, ?string $actualizadoAt): int
    {
        $normalizado = Documento::empresa($cif);
        $clave = $tabla.':'.$origenId;
        $empresaId = $normalizado === null ? null : ($this->empresasPorCif[$normalizado] ?? null);

        if ($empresaId === null && isset($this->origenesEmpresa[$clave])) {
            $anterior = $this->origenesEmpresa[$clave];

            if ($normalizado === null || $this->empresas[$anterior]['cif'] === null) {
                $empresaId = $anterior;
            }
        }

        if ($empresaId === null) {
            $empresaId = DB::table('empresas')->insertGetId($datos + [
                'cif' => $normalizado,
                'datos_actualizados_at' => $actualizadoAt,
                'created_at' => $this->marca,
                'updated_at' => $this->marca,
            ]);
            $this->empresas[$empresaId] = ['cif' => $normalizado, 'actualizado' => $actualizadoAt];
            $this->contar('empresas_nuevas');
        } elseif ($this->esMasReciente($actualizadoAt, $this->empresas[$empresaId]['actualizado'])) {
            $cifFinal = $this->empresas[$empresaId]['cif'] ?? $normalizado;

            DB::table('empresas')->where('id', $empresaId)->update($datos + [
                'cif' => $cifFinal,
                'datos_actualizados_at' => $actualizadoAt,
                'updated_at' => $this->marca,
            ]);
            $this->empresas[$empresaId] = ['cif' => $cifFinal, 'actualizado' => $actualizadoAt];
        }

        if ($this->empresas[$empresaId]['cif'] === null && $normalizado !== null) {
            DB::table('empresas')->where('id', $empresaId)->update(['cif' => $normalizado, 'updated_at' => $this->marca]);
            $this->empresas[$empresaId]['cif'] = $normalizado;
        }

        if ($this->empresas[$empresaId]['cif'] !== null) {
            $this->empresasPorCif[$this->empresas[$empresaId]['cif']] = $empresaId;
        }

        $this->origenesEmpresa[$clave] = $empresaId;
        $this->filasOrigenEmpresa[] = [
            'empresa_id' => $empresaId,
            'programa' => $this->programa()->value,
            'origen_tabla' => $tabla,
            'origen_id' => $origenId,
            'sincronizado_at' => $this->marca,
        ];
        $this->contar('empresas_leidas');

        return $empresaId;
    }

    /**
     * Intranet empresa of a source record already registered in this run.
     */
    protected function empresaDe(string $tabla, ?int $origenId): ?int
    {
        return $origenId === null ? null : ($this->origenesEmpresa[$tabla.':'.$origenId] ?? null);
    }

    /**
     * Register a participation; it is written in batches at the end of the run.
     *
     * @param  array{referencia?: ?string, titulo?: ?string, fecha_inicio?: ?string, fecha_fin?: ?string, estado?: ?string, responsable?: ?string, datos?: array<string, mixed>}  $atributos
     * @param  list<int>  $personaIds
     * @param  array<int, ?bool>  $migrantes  Whether each persona (by id) counted as migrant in it
     */
    protected function registrarParticipacion(TipoParticipacion $tipo, int $origenId, array $atributos, array $personaIds, ?int $empresaId = null, array $migrantes = []): void
    {
        $this->participacionesPendientes[] = [
            'fila' => [
                'programa' => $this->programa()->value,
                'tipo' => $tipo->value,
                'origen_id' => $origenId,
                'empresa_id' => $empresaId,
                'referencia' => $atributos['referencia'] ?? null,
                'titulo' => isset($atributos['titulo']) ? mb_substr($atributos['titulo'], 0, 255) : null,
                'fecha_inicio' => $atributos['fecha_inicio'] ?? null,
                'fecha_fin' => $atributos['fecha_fin'] ?? null,
                'estado' => $atributos['estado'] ?? null,
                'responsable' => $atributos['responsable'] ?? null,
                'datos' => json_encode($atributos['datos'] ?? [], JSON_UNESCAPED_UNICODE),
                'sincronizado_at' => $this->marca,
                'created_at' => $this->marca,
                'updated_at' => $this->marca,
            ],
            'personas' => array_values(array_unique($personaIds)),
            'migrantes' => $migrantes,
        ];
        $this->contar('participaciones_'.$tipo->value);
    }

    protected function contar(string $concepto, int $cantidad = 1): void
    {
        $this->resumen[$concepto] = ($this->resumen[$concepto] ?? 0) + $cantidad;
    }

    private function cargarExistentes(): void
    {
        foreach (DB::table('personas')->get(['id', 'documento', 'datos_actualizados_at']) as $persona) {
            $this->personas[$persona->id] = ['documento' => $persona->documento, 'actualizado' => $persona->datos_actualizados_at];

            if ($persona->documento !== null) {
                $this->personasPorDocumento[$persona->documento] = $persona->id;
            }
        }

        foreach (DB::table('empresas')->get(['id', 'cif', 'datos_actualizados_at']) as $empresa) {
            $this->empresas[$empresa->id] = ['cif' => $empresa->cif, 'actualizado' => $empresa->datos_actualizados_at];

            if ($empresa->cif !== null) {
                $this->empresasPorCif[$empresa->cif] = $empresa->id;
            }
        }

        $programa = $this->programa()->value;

        foreach (DB::table('persona_origenes')->where('programa', $programa)->get() as $origen) {
            $this->origenesPersona[$origen->origen_tabla.':'.$origen->origen_id] = $origen->persona_id;
        }

        foreach (DB::table('empresa_origenes')->where('programa', $programa)->get() as $origen) {
            $this->origenesEmpresa[$origen->origen_tabla.':'.$origen->origen_id] = $origen->empresa_id;
        }
    }

    private function guardarOrigenes(): void
    {
        foreach (array_chunk($this->filasOrigenPersona, self::TAMANO_LOTE) as $lote) {
            DB::table('persona_origenes')->upsert($lote, ['programa', 'origen_tabla', 'origen_id'], ['persona_id', 'sincronizado_at']);
        }

        foreach (array_chunk($this->filasOrigenEmpresa, self::TAMANO_LOTE) as $lote) {
            DB::table('empresa_origenes')->upsert($lote, ['programa', 'origen_tabla', 'origen_id'], ['empresa_id', 'sincronizado_at']);
        }
    }

    private function guardarParticipaciones(): void
    {
        $actualizables = ['empresa_id', 'referencia', 'titulo', 'fecha_inicio', 'fecha_fin', 'estado', 'responsable', 'datos', 'sincronizado_at', 'updated_at'];

        foreach (array_chunk($this->participacionesPendientes, self::TAMANO_LOTE) as $lote) {
            DB::table('participaciones')->upsert(array_column($lote, 'fila'), ['programa', 'tipo', 'origen_id'], $actualizables);

            $ids = [];

            foreach ($lote as $pendiente) {
                $ids[$pendiente['fila']['tipo']][] = $pendiente['fila']['origen_id'];
            }

            $participacionIds = [];

            foreach ($ids as $tipo => $origenIds) {
                DB::table('participaciones')
                    ->where('programa', $this->programa()->value)
                    ->where('tipo', $tipo)
                    ->whereIn('origen_id', $origenIds)
                    ->get(['id', 'tipo', 'origen_id'])
                    ->each(function (object $fila) use (&$participacionIds): void {
                        $participacionIds[$fila->tipo.':'.$fila->origen_id] = $fila->id;
                    });
            }

            DB::table('participacion_persona')->whereIn('participacion_id', array_values($participacionIds))->delete();

            $vinculos = [];

            foreach ($lote as $pendiente) {
                $participacionId = $participacionIds[$pendiente['fila']['tipo'].':'.$pendiente['fila']['origen_id']];

                foreach ($pendiente['personas'] as $personaId) {
                    $vinculos[] = ['participacion_id' => $participacionId, 'persona_id' => $personaId, 'migrante' => $pendiente['migrantes'][$personaId] ?? null];
                }
            }

            foreach (array_chunk($vinculos, self::TAMANO_LOTE) as $loteVinculos) {
                DB::table('participacion_persona')->insert($loteVinculos);
            }
        }
    }

    /**
     * Remove what this program no longer has, and the fichas left without any source.
     */
    private function eliminarDesaparecidos(): void
    {
        $programa = $this->programa()->value;

        $this->contar('participaciones_eliminadas', DB::table('participaciones')->where('programa', $programa)->where('sincronizado_at', '<', $this->marca)->delete());
        DB::table('persona_origenes')->where('programa', $programa)->where('sincronizado_at', '<', $this->marca)->delete();
        DB::table('empresa_origenes')->where('programa', $programa)->where('sincronizado_at', '<', $this->marca)->delete();

        $this->contar('personas_eliminadas', DB::table('personas')->whereNotExists(
            fn ($consulta) => $consulta->from('persona_origenes')->whereColumn('persona_origenes.persona_id', 'personas.id')
        )->delete());

        $this->contar('empresas_eliminadas', DB::table('empresas')->whereNotExists(
            fn ($consulta) => $consulta->from('empresa_origenes')->whereColumn('empresa_origenes.empresa_id', 'empresas.id')
        )->delete());
    }

    private function esMasReciente(?string $nuevo, ?string $actual): bool
    {
        if ($actual === null) {
            return true;
        }

        return $nuevo !== null && strtotime($nuevo) > strtotime($actual);
    }
}
