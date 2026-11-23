<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\UsaFiltrosEstadisticas;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Headline of a statistics block: different people (or migrant people) attended in the period.
 */
class TotalPersonasAtendidas extends StatsOverviewWidget
{
    use UsaFiltrosEstadisticas;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * No polling: the figures only change with the sync, every four hours.
     */
    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $total = number_format($this->distribucion()->total(), 0, ',', '.');

        if ($this->soloMigrantes) {
            return [
                Stat::make(__('Personas migrantes atendidas'), $total)
                    ->description(__('El país de origen se recoge desde el 19/09/2025: antes, las personas migrantes aparecen como «No consta».')),
            ];
        }

        return [
            Stat::make(__('Personas atendidas'), $total)
                ->description(__('Cada persona cuenta una vez, aunque tenga varias participaciones en el periodo.')),
        ];
    }
}
