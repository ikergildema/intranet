{{-- Ficha of an empresa: company data and every participation with its people. --}}
@php($empresa = $getRecord())
@php($participaciones = $empresa->participaciones()->with('personas')->orderByDesc('fecha_inicio')->get())

@include('fichas.estilos')

<div class="fc-card">
    <div class="fc-cabecera">
        <div>
            <div class="fc-nombre">{{ $empresa->nombre ?: __('Empresa sin nombre') }}</div>
            <div class="fc-contacto">
                <span class="fc-dato" title="{{ __('CIF') }}"><x-filament::icon icon="heroicon-m-identification" />{{ $empresa->cif ?? __('Sin CIF fiable') }}</span>
                @if ($empresa->sector)<span class="fc-dato"><x-filament::icon icon="heroicon-m-briefcase" />{{ $empresa->sector }}</span>@endif
                @if ($empresa->forma_juridica)<span class="fc-dato"><x-filament::icon icon="heroicon-m-scale" />{{ $empresa->forma_juridica }}</span>@endif
                @if ($empresa->fecha_alta)<span class="fc-dato"><x-filament::icon icon="heroicon-m-calendar" />{{ __('Alta: :fecha', ['fecha' => $empresa->fecha_alta->format('d/m/Y')]) }}</span>@endif
                @if ($empresa->poblacion)<span class="fc-dato"><x-filament::icon icon="heroicon-m-map-pin" />{{ $empresa->poblacion }}</span>@endif
                @if ($empresa->telefono)<span class="fc-dato"><x-filament::icon icon="heroicon-m-phone" /><a href="tel:{{ preg_replace('/\s/', '', $empresa->telefono) }}">{{ $empresa->telefono }}</a></span>@endif
                @if ($empresa->email)<span class="fc-dato"><x-filament::icon icon="heroicon-m-envelope" /><a href="mailto:{{ $empresa->email }}">{{ $empresa->email }}</a></span>@endif
            </div>
        </div>
        <div class="fc-insignias">
            @if ($empresa->estado)
                <x-filament::badge :color="str_starts_with($empresa->estado, 'Baja') ? 'danger' : 'success'">{{ $empresa->estado }}</x-filament::badge>
            @endif
            <x-filament::badge color="gray">{{ trans_choice(':n participación|:n participaciones', $participaciones->count(), ['n' => $participaciones->count()]) }}</x-filament::badge>
        </div>
    </div>

    <div class="fc-cuerpo">
        @forelse ($participaciones as $participacion)
            @include('fichas.participacion', ['participacion' => $participacion, 'mostrarPersonas' => true, 'excluirPersona' => null, 'mostrarEmpresa' => false])
        @empty
            <div class="fc-vacio">{{ __('Sin participaciones.') }}</div>
        @endforelse
    </div>
</div>
