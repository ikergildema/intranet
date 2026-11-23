{{--
    One participation in a ficha. An Abian expediente shows its recepción, the call that
    originated it, the business plan with its meetings and the company, as in abianv2.

    $participacion, $mostrarPersonas (bool), $excluirPersona (?int), $mostrarEmpresa (bool)
--}}
@php($fecha = fn (?string $valor, string $formato = 'd/m/Y') => $valor ? \Illuminate\Support\Carbon::parse($valor)->format($formato) : null)
@php($datos = $participacion->datos ?? [])
@php($plan = $datos['plan'] ?? null)
@php($url = $participacion->urlOrigen())
@php($personas = $participacion->personas->reject(fn ($persona) => $persona->id === $excluirPersona))

<div class="fc-part">
    <div class="fc-fila">
        <x-filament::badge size="sm" color="primary">{{ $participacion->programa->getLabel() }}</x-filament::badge>
        @if ($participacion->tipo !== \App\Enums\TipoParticipacion::Expediente)
            <span class="fc-tenue">{{ $participacion->tipo->getLabel() }}</span>
        @endif
        @if ($participacion->referencia)<span class="cod">{{ $participacion->referencia }}</span>@endif
        <span>{{ $participacion->fecha_inicio?->format('d/m/Y') ?? '—' }}@if (! empty($datos['hora'])) · {{ $datos['hora'] }}@endif</span>
        @if ($participacion->tipo === \App\Enums\TipoParticipacion::Expediente && $participacion->responsable)
            <span class="fc-tenue">{{ __('Atendida por :nombre', ['nombre' => $participacion->responsable]) }}</span>
        @endif
        @if (! empty($datos['resultado_etiqueta']))
            <x-filament::badge size="sm" :color="$participacion->colorResultado()">{{ __($datos['resultado_etiqueta']) }}</x-filament::badge>
        @endif
        @if ($participacion->estado)
            <x-filament::badge size="sm" :color="$participacion->colorEstado()">{{ __($participacion->estado) }}</x-filament::badge>
        @endif
        @if ($participacion->titulo && $participacion->tipo !== \App\Enums\TipoParticipacion::PlanNegocio)
            <span class="fc-tenue">«{{ \Illuminate\Support\Str::limit($participacion->titulo, 60) }}»</span>
        @endif
        @if ($url)
            <a class="fc-boton" href="{{ $url }}" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" />{{ __('Abrir en :programa', ['programa' => $participacion->programa->getLabel()]) }}
            </a>
        @endif
    </div>

    @if ($mostrarPersonas && $personas->isNotEmpty())
        <div class="fc-fila fc-tenue" style="margin-top:.25rem; font-size:.8125rem;">
            <x-filament::icon icon="heroicon-m-users" style="width:.875rem;height:.875rem;" />
            {{ $excluirPersona ? __('Con') : __('Personas') }}:
            @foreach ($personas as $persona)
                <a class="fc-enlace" href="{{ \App\Filament\Resources\Personas\PersonaResource::getUrl('view', ['record' => $persona]) }}">{{ $persona->nombreCompleto() ?: __('Sin nombre') }}</a>@if (! $loop->last),@endif
            @endforeach
        </div>
    @endif

    @foreach ($datos['llamadas'] ?? [] as $llamada)
        <div class="fc-fila fc-tenue" style="margin-top:.25rem; font-size:.8125rem;">
            <x-filament::icon icon="heroicon-m-phone" style="width:.875rem;height:.875rem;" />
            {{ __('Llamada del :fecha', ['fecha' => $fecha($llamada['fecha'], 'd/m/Y H:i') ?? '—']) }}
            @if ($llamada['telefonica'])· {{ __('Recepción telefónica') }}@endif
        </div>
    @endforeach

    @if ($plan)
        <div class="fc-sub">
            <div class="fc-sub-tit">
                <strong>{{ __('Plan de negocio') }}</strong>
                <span>{{ $plan['consultor'] ?? __('Sin consultor') }}</span>
                @if ($plan['tipo_plan'])<span class="fc-tenue">{{ $plan['tipo_plan'] }}</span>@endif
                @if ($plan['fecha_asignacion'])<span class="fc-tenue">{{ __('Asignado el :fecha', ['fecha' => $fecha($plan['fecha_asignacion'])]) }}</span>@endif
                @if ($plan['fecha_final'] && $plan['cerrado'])<span class="fc-tenue">· {{ __('Cerrado el :fecha', ['fecha' => $fecha($plan['fecha_final'])]) }}</span>@endif
                @if ($plan['baja'] && $plan['motivo_baja'])<span class="fc-tenue">· {{ $plan['motivo_baja'] }}</span>@endif
                @if ($participacion->tipo === \App\Enums\TipoParticipacion::Expediente)
                    <a class="fc-boton" href="{{ \App\Enums\Programa::Abian->url() }}/planes/{{ $plan['id'] }}" target="_blank" rel="noopener">
                        <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" />{{ __('Ver plan') }}
                    </a>
                @endif
            </div>
            @if ($plan['nombre'] && $participacion->tipo === \App\Enums\TipoParticipacion::PlanNegocio)
                <div class="fc-tenue" style="margin-top:.25rem;">«{{ $plan['nombre'] }}»</div>
            @endif

            @if (! empty($plan['reuniones']))
                <div class="fc-reuniones">
                    @foreach ($plan['reuniones'] as $reunion)
                        <span class="fc-reunion" title="{{ $reunion['celebrada'] ? __('Celebrada') : ($reunion['no_presentado'] ? __('No se presentó') : '') }}">
                            @if ($reunion['celebrada'])
                                <x-filament::icon icon="heroicon-m-check-circle" style="width:.875rem;height:.875rem;color:var(--success-600);" />
                            @elseif ($reunion['no_presentado'])
                                <x-filament::icon icon="heroicon-m-x-circle" style="width:.875rem;height:.875rem;color:var(--danger-600);" />
                            @else
                                <x-filament::icon icon="heroicon-m-calendar" style="width:.875rem;height:.875rem;color:var(--gray-400);" />
                            @endif
                            {{ $fecha($reunion['fecha']) ?? '—' }}@if ($reunion['tipo']) · {{ $reunion['tipo'] }}@endif
                        </span>
                    @endforeach
                </div>
            @else
                <div class="fc-tenue" style="margin-top:.375rem; font-size:.8125rem;">{{ __('Sin reuniones.') }}</div>
            @endif

            @if ($mostrarEmpresa && $participacion->empresa)
                @php($empresa = $participacion->empresa)
                <div class="fc-sub-tit" style="margin-top:.625rem; padding-top:.5rem; border-top:1px dashed var(--gray-200);">
                    <strong>{{ __('Empresa') }}</strong>
                    <a class="fc-enlace" href="{{ \App\Filament\Resources\Empresas\EmpresaResource::getUrl('view', ['record' => $empresa]) }}">{{ $empresa->nombre }}</a>
                    @if ($empresa->cif)<span class="fc-tenue">{{ $empresa->cif }}</span>@endif
                    @if ($empresa->estado)
                        <x-filament::badge size="sm" :color="str_starts_with($empresa->estado, 'Baja') ? 'danger' : 'success'">{{ $empresa->estado }}</x-filament::badge>
                    @endif
                </div>
            @endif
        </div>
    @elseif ($mostrarEmpresa && $participacion->empresa)
        <div class="fc-fila" style="margin-top:.25rem;">
            <strong class="fc-tenue" style="font-size:.75rem; text-transform:uppercase;">{{ __('Empresa') }}</strong>
            <a class="fc-enlace" href="{{ \App\Filament\Resources\Empresas\EmpresaResource::getUrl('view', ['record' => $participacion->empresa]) }}">{{ $participacion->empresa->nombre }}</a>
        </div>
    @endif
</div>
