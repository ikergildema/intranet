<?php

namespace App\Filament\Resources\Participaciones;

use App\Enums\Programa;
use App\Enums\TipoParticipacion;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Filament\Resources\Personas\PersonaResource;
use App\Models\Participacion;
use App\Models\Persona;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Read-only table of participations shared by the persona and empresa fichas.
 */
class TablaParticipaciones
{
    public static function configurar(Table $table, bool $mostrarPersonas, bool $mostrarEmpresa): Table
    {
        $columnas = [
            TextColumn::make('programa')->badge()->sortable(),
            TextColumn::make('tipo')->sortable(),
            TextColumn::make('fecha_inicio')->label('Inicio')->date('d/m/Y')->sortable(),
            TextColumn::make('fecha_fin')->label('Fin')->date('d/m/Y')->sortable()->toggleable(),
            TextColumn::make('referencia')->label('Expediente / referencia')->searchable(),
            TextColumn::make('titulo')->label('Título')->wrap()->searchable(),
            TextColumn::make('estado')->badge()->color('gray'),
            TextColumn::make('responsable')->toggleable(),
        ];

        if ($mostrarPersonas) {
            $columnas[] = TextColumn::make('personas')->label('Personas')
                ->state(fn (Participacion $record) => new HtmlString($record->personas
                    ->map(fn (Persona $persona) => '<a class="text-primary-600 underline" href="'.e(PersonaResource::getUrl('view', ['record' => $persona])).'">'.e($persona->nombreCompleto() ?: __('(sin nombre)')).'</a>')
                    ->implode('<br>')));
        }

        if ($mostrarEmpresa) {
            $columnas[] = TextColumn::make('empresa.nombre')->label('Empresa')
                ->url(fn (Participacion $record) => $record->empresa_id === null ? null : EmpresaResource::getUrl('view', ['record' => $record->empresa_id]));
        }

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(array_filter([
                $mostrarPersonas ? 'personas' : null,
                $mostrarEmpresa ? 'empresa' : null,
            ])))
            ->defaultSort('fecha_inicio', 'desc')
            ->columns($columnas)
            ->filters([
                SelectFilter::make('programa')->options(Programa::class),
                SelectFilter::make('tipo')->options(TipoParticipacion::class),
            ])
            ->recordActions([
                Action::make('origen')
                    ->label(fn (Participacion $record) => __('Abrir en :programa', ['programa' => $record->programa->getLabel()]))
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Participacion $record) => $record->urlOrigen(), shouldOpenInNewTab: true)
                    ->visible(fn (Participacion $record) => $record->urlOrigen() !== null),
            ])
            ->emptyStateHeading(__('Sin participaciones'));
    }
}
