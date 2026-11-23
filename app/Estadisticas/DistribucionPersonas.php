<?php

namespace App\Estadisticas;

use App\Enums\Programa;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Distribution of the people attended by DEMA between two dates.
 *
 * A person is counted once however many participations they had in the period. Their age is
 * the age at their first participation in the period, and they count as migrant when they
 * were migrant in any participation of the period by that program's rule. It can be limited
 * to the migrant people of the period.
 */
class DistribucionPersonas
{
    public const SEXO = 'sexo';

    public const ORIGEN = 'origen';

    public const EDAD = 'edad';

    public const POBLACION = 'poblacion';

    public const AREA_ORIGEN = 'area_origen';

    public const PAIS_ORIGEN = 'pais_origen';

    public const NO_CONSTA = 'No consta';

    /**
     * Groups shown on their own in the open dimensions (towns, countries, areas); the rest are
     * folded into one slice, so a pie never has more than six.
     */
    public const GRUPOS_VISIBLES = 4;

    /**
     * Label of the folded slice of each open dimension.
     *
     * @var array<string, string>
     */
    public const RESTO = [
        self::POBLACION => 'Otras poblaciones',
        self::AREA_ORIGEN => 'Otras zonas',
        self::PAIS_ORIGEN => 'Otros países',
    ];

    /**
     * Groups of each dimension in display order (the towns are the most frequent ones).
     *
     * @var array<string, list<string>>
     */
    private const GRUPOS = [
        self::SEXO => ['Mujeres', 'Hombres'],
        self::ORIGEN => ['Autóctonas', 'Migrantes'],
        self::EDAD => ['Menos de 30', 'De 30 a 45', 'Más de 45'],
    ];

    public function __construct(
        private Carbon $desde,
        private Carbon $hasta,
        private ?Programa $programa = null,
        private bool $soloMigrantes = false,
    ) {}

    /**
     * Number of different people attended in the period.
     */
    public function total(): int
    {
        return DB::query()->fromSub($this->personasAtendidas(), 'atendidas')->count();
    }

    /**
     * People per group, in display order, with "No consta" last. Open dimensions keep only
     * their main groups and fold the rest (see porCompleto() for every group).
     *
     * @return array<string, int>
     */
    public function por(string $dimension): array
    {
        return $this->calcular($dimension, agrupar: true);
    }

    /**
     * Every group of the dimension, most people first, with "No consta" last.
     *
     * @return array<string, int>
     */
    public function porCompleto(string $dimension): array
    {
        return $this->calcular($dimension, agrupar: false);
    }

    /**
     * @return array<string, int>
     */
    private function calcular(string $dimension, bool $agrupar): array
    {
        $grupo = match ($dimension) {
            self::SEXO => "CASE personas.sexo WHEN 'mujer' THEN 'Mujeres' WHEN 'hombre' THEN 'Hombres' ELSE 'No consta' END",
            self::ORIGEN => "CASE atendidas.migrante WHEN 1 THEN 'Migrantes' WHEN 0 THEN 'Autóctonas' ELSE 'No consta' END",
            self::EDAD => "CASE
                WHEN personas.fecha_nacimiento IS NULL THEN 'No consta'
                WHEN TIMESTAMPDIFF(YEAR, personas.fecha_nacimiento, atendidas.primera) < 30 THEN 'Menos de 30'
                WHEN TIMESTAMPDIFF(YEAR, personas.fecha_nacimiento, atendidas.primera) <= 45 THEN 'De 30 a 45'
                ELSE 'Más de 45' END",
            self::POBLACION => "CASE WHEN personas.poblacion IS NULL OR TRIM(personas.poblacion) IN ('', 'No procede') THEN 'No consta' ELSE TRIM(personas.poblacion) END",
            self::AREA_ORIGEN => "COALESCE(personas.area_origen, 'No consta')",
            self::PAIS_ORIGEN => "COALESCE(personas.pais_origen, 'No consta')",
        };

        $totales = DB::query()
            ->fromSub($this->personasAtendidas(), 'atendidas')
            ->join('personas', 'personas.id', '=', 'atendidas.persona_id')
            ->selectRaw("{$grupo} as grupo, count(*) as total")
            ->groupBy('grupo')
            ->orderByDesc('total')
            ->orderBy('grupo')
            ->pluck('total', 'grupo')
            ->map(fn ($total) => (int) $total)
            ->all();

        $noConsta = $totales[self::NO_CONSTA] ?? 0;
        unset($totales[self::NO_CONSTA]);

        $ordenados = match (true) {
            ! isset(self::RESTO[$dimension]) => array_filter(array_replace(array_fill_keys(self::GRUPOS[$dimension], 0), $totales)),
            $agrupar => $this->agruparPrincipales($totales, self::RESTO[$dimension]),
            default => $totales,
        };

        return $noConsta > 0 ? $ordenados + [self::NO_CONSTA => $noConsta] : $ordenados;
    }

    /**
     * One row per person attended in the period, with their first date and migrant flag.
     */
    private function personasAtendidas(): Builder
    {
        return DB::table('participacion_persona')
            ->join('participaciones', 'participaciones.id', '=', 'participacion_persona.participacion_id')
            ->whereBetween('participaciones.fecha_inicio', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->when($this->programa, fn (Builder $consulta, Programa $programa) => $consulta->where('participaciones.programa', $programa->value))
            ->groupBy('participacion_persona.persona_id')
            ->when($this->soloMigrantes, fn (Builder $consulta) => $consulta->havingRaw('MAX(participacion_persona.migrante) = 1'))
            ->selectRaw('participacion_persona.persona_id, MIN(participaciones.fecha_inicio) as primera, MAX(participacion_persona.migrante) as migrante');
    }

    /**
     * @param  array<string, int>  $totales  Groups sorted by number of people, most first
     * @return array<string, int>
     */
    private function agruparPrincipales(array $totales, string $resto): array
    {
        $visibles = array_slice($totales, 0, self::GRUPOS_VISIBLES, preserve_keys: true);
        $otros = array_sum(array_slice($totales, self::GRUPOS_VISIBLES));

        return $otros > 0 ? $visibles + [$resto => $otros] : $visibles;
    }
}
