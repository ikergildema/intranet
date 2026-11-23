<?php

namespace App\Filament\Pages;

use App\Enums\Programa;
use App\Enums\Rol;
use App\Estadisticas\DistribucionPersonas;
use App\Filament\Widgets\DistribucionPersonasChart;
use App\Filament\Widgets\TablaDistribucionPersonas;
use App\Filament\Widgets\TotalPersonasAtendidas;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Statistics for management: distribution of the people attended between two dates.
 */
class Estadisticas extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'estadisticas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([Rol::Root->value, Rol::Direccion->value]) ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('Estadísticas');
    }

    public function getTitle(): string
    {
        return __('Personas atendidas');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                DatePicker::make('desde')->label(__('Desde'))->default(now()->startOfYear())->required()->native(false)->displayFormat('d/m/Y'),
                DatePicker::make('hasta')->label(__('Hasta'))->default(now())->required()->native(false)->displayFormat('d/m/Y'),
                Select::make('programa')->label(__('Programa'))->options(Programa::class)->placeholder(__('Todos los programas')),
            ]),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            TotalPersonasAtendidas::class,
            ...array_map(
                fn (string $dimension) => DistribucionPersonasChart::make(['dimension' => $dimension]),
                [DistribucionPersonas::SEXO, DistribucionPersonas::ORIGEN, DistribucionPersonas::EDAD, DistribucionPersonas::POBLACION],
            ),
            TablaDistribucionPersonas::make(['dimension' => DistribucionPersonas::POBLACION]),
            TotalPersonasAtendidas::make(['soloMigrantes' => true]),
            ...array_map(
                fn (string $dimension) => DistribucionPersonasChart::make(['dimension' => $dimension, 'soloMigrantes' => true]),
                [DistribucionPersonas::AREA_ORIGEN, DistribucionPersonas::PAIS_ORIGEN],
            ),
            TablaDistribucionPersonas::make(['dimension' => DistribucionPersonas::PAIS_ORIGEN, 'soloMigrantes' => true]),
        ];
    }
}
