<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsaFiltrosEstadisticas;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Headline of the statistics page: different people attended in the period.
 */
class TotalPersonasAtendidas extends StatsOverviewWidget
{
    use UsaFiltrosEstadisticas;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make(__('Personas atendidas'), number_format($this->distribucion()->total(), 0, ',', '.'))
                ->description(__('Cada persona cuenta una vez, aunque tenga varias participaciones en el periodo.')),
        ];
    }
}
