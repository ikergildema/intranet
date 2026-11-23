<?php

namespace App\Filament\Widgets;

use App\Estadisticas\DistribucionPersonas;
use App\Filament\Widgets\Concerns\UsaFiltrosEstadisticas;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\HtmlString;

/**
 * Pie chart of the people attended in the period by one dimension. The legend carries the
 * figure and percentage of each group, so it can be read without telling colours apart.
 */
class DistribucionPersonasChart extends ChartWidget
{
    use UsaFiltrosEstadisticas;

    /**
     * Categorical palette in fixed order (validated for colour-blind separation). "No consta"
     * is always neutral gray, so it never looks like one more category.
     *
     * @var list<string>
     */
    private const COLORES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];

    private const COLOR_NO_CONSTA = '#a8a7a2';

    protected static bool $isDiscovered = false;

    protected ?string $maxHeight = '260px';

    public string $dimension = DistribucionPersonas::SEXO;

    /** @var array<string, int>|null */
    private ?array $totales = null;

    public function getHeading(): string
    {
        return match ($this->dimension) {
            DistribucionPersonas::SEXO => __('Sexo'),
            DistribucionPersonas::ORIGEN => __('Origen'),
            DistribucionPersonas::EDAD => __('Edad'),
            DistribucionPersonas::POBLACION => __('Población'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this->dimension) {
            DistribucionPersonas::ORIGEN => __('Abian: desde el 19/09/2025, por país de origen; antes, por DNI extranjero.'),
            DistribucionPersonas::EDAD => __('Edad en su primera participación del periodo.'),
            DistribucionPersonas::POBLACION => __('Las :n poblaciones con más personas; el resto, agrupadas.', ['n' => DistribucionPersonas::POBLACIONES_VISIBLES]),
            default => null,
        };
    }

    public function getChartAssistiveContent(): HtmlString
    {
        return new HtmlString(view('filament.widgets.tabla-distribucion', ['filas' => $this->filas()])->render());
    }

    public function isEmpty(): bool
    {
        return $this->totales() === [];
    }

    public function getEmptyStateHeading(): string
    {
        return __('Nadie atendido en el periodo.');
    }

    protected function getType(): string
    {
        return 'pie';
    }

    /**
     * Figures of the chart, also given as a table to screen readers.
     *
     * @return list<array{grupo: string, total: int, porcentaje: string}>
     */
    public function filas(): array
    {
        $totales = $this->totales();
        $suma = max(array_sum($totales), 1);

        return array_map(fn (string $grupo, int $total) => [
            'grupo' => $this->etiqueta($grupo),
            'total' => $total,
            'porcentaje' => number_format($total * 100 / $suma, 1, ',', '.').' %',
        ], array_keys($totales), $totales);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $filas = $this->filas();
        $colores = [];
        $indice = 0;

        foreach (array_keys($this->totales()) as $grupo) {
            $colores[] = $grupo === DistribucionPersonas::NO_CONSTA ? self::COLOR_NO_CONSTA : self::COLORES[$indice++ % count(self::COLORES)];
        }

        return [
            'datasets' => [[
                'data' => array_column($filas, 'total'),
                'backgroundColor' => $colores,
                'borderWidth' => 2,
            ]],
            'labels' => array_map(fn (array $fila) => "{$fila['grupo']}: ".number_format($fila['total'], 0, ',', '.')." ({$fila['porcentaje']})", $filas),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                animation: false,
                elements: { arc: { borderColor: document.documentElement.classList.contains('dark') ? '#18181b' : '#ffffff' } },
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, boxHeight: 12, padding: 10 } },
                    tooltip: { callbacks: { label: (contexto) => contexto.label } },
                },
                scales: { x: { display: false }, y: { display: false } },
            }
        JS);
    }

    /**
     * @return array<string, int>
     */
    private function totales(): array
    {
        return $this->totales ??= $this->distribucion()->por($this->dimension);
    }

    private function etiqueta(string $grupo): string
    {
        return match ($grupo) {
            DistribucionPersonas::NO_CONSTA, DistribucionPersonas::OTRAS_POBLACIONES, 'Mujeres', 'Hombres', 'Autóctonas', 'Migrantes', 'Menos de 30', 'De 30 a 45', 'Más de 45' => __($grupo),
            default => $grupo,
        };
    }
}
