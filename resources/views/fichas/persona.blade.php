{{-- Ficha of a persona: contact data and every participation, newest first. --}}
@php($persona = $getRecord())
@php($participaciones = $persona->participaciones()->with(['empresa', 'personas'])->orderByDesc('fecha_inicio')->get())

@include('fichas.estilos')

<div class="fc-card">
    <div class="fc-cabecera">
        <div>
            <div class="fc-nombre">{{ $persona->nombreCompleto() ?: __('Sin nombre') }}</div>
            <div class="fc-contacto">
                <span class="fc-dato" title="{{ __('DNI / NIE') }}"><x-filament::icon icon="heroicon-m-identification" />{{ $persona->documento ?? __('Sin documento fiable') }}</span>
                @if ($persona->documento_original && $persona->documento_original !== $persona->documento)
                    <span class="fc-tenue">({{ __('tal como se escribió: :documento', ['documento' => $persona->documento_original]) }})</span>
                @endif
                @if ($persona->telefono)<span class="fc-dato"><x-filament::icon icon="heroicon-m-phone" /><a href="tel:{{ preg_replace('/\s/', '', $persona->telefono) }}">{{ $persona->telefono }}</a></span>@endif
                @if ($persona->email)<span class="fc-dato"><x-filament::icon icon="heroicon-m-envelope" /><a href="mailto:{{ $persona->email }}">{{ $persona->email }}</a></span>@endif
                @if ($persona->poblacion)<span class="fc-dato"><x-filament::icon icon="heroicon-m-map-pin" />{{ $persona->poblacion }}</span>@endif
                @if ($persona->fecha_nacimiento)<span class="fc-dato"><x-filament::icon icon="heroicon-m-cake" />{{ $persona->fecha_nacimiento->format('d/m/Y') }} · {{ __(':n años', ['n' => $persona->fecha_nacimiento->age]) }}</span>@endif
                @if ($persona->sexo)<span class="fc-dato"><x-filament::icon icon="heroicon-m-user" />{{ $persona->sexo->getLabel() }}</span>@endif
            </div>
        </div>
        <div class="fc-insignias">
            @foreach ($participaciones->groupBy(fn ($participacion) => $participacion->programa->value) as $deUnPrograma)
                <x-filament::badge color="primary">{{ $deUnPrograma->first()->programa->getLabel() }} · {{ trans_choice(':n participación|:n participaciones', $deUnPrograma->count(), ['n' => $deUnPrograma->count()]) }}</x-filament::badge>
            @endforeach
        </div>
    </div>

    <div class="fc-cuerpo">
        @forelse ($participaciones as $participacion)
            @include('fichas.participacion', ['participacion' => $participacion, 'mostrarPersonas' => $participacion->personas->count() > 1, 'excluirPersona' => $persona->id, 'mostrarEmpresa' => true])
        @empty
            <div class="fc-vacio">{{ __('Sin participaciones.') }}</div>
        @endforelse
    </div>
</div>
