<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\Programa;
use App\Estadisticas\DistribucionPersonas;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

/**
 * Builds the distribution for the dates and program chosen on the statistics page.
 */
trait UsaFiltrosEstadisticas
{
    use InteractsWithPageFilters;

    protected function distribucion(): DistribucionPersonas
    {
        $filtros = $this->pageFilters ?? [];

        return new DistribucionPersonas(
            Carbon::parse($filtros['desde'] ?? now()->startOfYear()),
            Carbon::parse($filtros['hasta'] ?? now()),
            Programa::tryFrom((string) ($filtros['programa'] ?? '')),
        );
    }
}
