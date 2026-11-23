{{-- Every group of a distribution, most people first, with a bar of its share. --}}
@php($filas = $this->filas())

<x-filament-widgets::widget>
    <x-filament::section :heading="$this->titulo()" :description="trans_choice(':n grupo|:n grupos', count($filas), ['n' => count($filas)])" collapsible>
        @if ($filas === [])
            <p style="font-size:.875rem; color:var(--gray-500);">{{ __('Nadie atendido en el periodo.') }}</p>
        @else
            <div style="max-height:24rem; overflow-y:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:.875rem;">
                    <thead style="position:sticky; top:0; background:var(--gray-50);">
                        <tr style="text-align:left; color:var(--gray-500);">
                            <th style="padding:.375rem .5rem; font-weight:600;">{{ $this->columnaGrupo() }}</th>
                            <th style="padding:.375rem .5rem; font-weight:600; text-align:right;">{{ __('Personas') }}</th>
                            <th style="padding:.375rem .5rem; font-weight:600; text-align:right;">%</th>
                            <th style="padding:.375rem .5rem; width:30%;" aria-hidden="true"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($maximo = max(array_column($filas, 'total')))
                        @foreach ($filas as $fila)
                            <tr style="border-top:1px solid var(--gray-100);">
                                <td style="padding:.3rem .5rem;">{{ $fila['grupo'] }}</td>
                                <td style="padding:.3rem .5rem; text-align:right; font-variant-numeric:tabular-nums;">{{ number_format($fila['total'], 0, ',', '.') }}</td>
                                <td style="padding:.3rem .5rem; text-align:right; font-variant-numeric:tabular-nums; color:var(--gray-500);">{{ $fila['porcentaje'] }}</td>
                                <td style="padding:.3rem .5rem;" aria-hidden="true">
                                    <div style="height:.5rem; border-radius:0 4px 4px 0; background:{{ $fila['grupo'] === __('No consta') ? '#a8a7a2' : '#2a78d6' }}; width:{{ round($fila['total'] * 100 / $maximo, 1) }}%;"></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
