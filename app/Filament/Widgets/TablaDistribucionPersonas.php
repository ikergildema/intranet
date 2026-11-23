<?php

namespace App\Filament\Widgets;

use App\Estadisticas\DistribucionPersonas;
use App\Filament\Widgets\Concerns\UsaFiltrosEstadisticas;
use Filament\Widgets\Widget;

/**
 * Every group of an open dimension (towns, countries), most people first: the pie above it
 * only shows the main ones.
 */
class TablaDistribucionPersonas extends Widget
{
    use UsaFiltrosEstadisticas;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.tabla-distribucion-personas';

    protected int|string|array $columnSpan = 'full';

    public string $dimension = DistribucionPersonas::POBLACION;

    public function titulo(): string
    {
        return match ($this->dimension) {
            DistribucionPersonas::POBLACION => __('Todas las poblaciones'),
            DistribucionPersonas::AREA_ORIGEN => __('Todas las zonas del mundo'),
            DistribucionPersonas::PAIS_ORIGEN => __('Todos los países de origen'),
        };
    }

    public function columnaGrupo(): string
    {
        return match ($this->dimension) {
            DistribucionPersonas::POBLACION => __('Población'),
            DistribucionPersonas::AREA_ORIGEN => __('Zona del mundo'),
            DistribucionPersonas::PAIS_ORIGEN => __('País'),
        };
    }

    /**
     * @return list<array{grupo: string, total: int, porcentaje: string}>
     */
    public function filas(): array
    {
        $totales = $this->distribucion()->porCompleto($this->dimension);
        $suma = max(array_sum($totales), 1);

        return array_map(fn (string $grupo, int $total) => [
            'grupo' => $grupo === DistribucionPersonas::NO_CONSTA ? __($grupo) : $grupo,
            'total' => $total,
            'porcentaje' => number_format($total * 100 / $suma, 1, ',', '.').' %',
        ], array_keys($totales), $totales);
    }
}
