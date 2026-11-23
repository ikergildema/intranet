<?php

namespace App\Filament\Resources\Personas;

use App\Enums\Programa;
use App\Enums\Sexo;
use App\Filament\Resources\Personas\Pages\ListPersonas;
use App\Filament\Resources\Personas\Pages\ViewPersona;
use App\Filament\Support\TraduceEtiquetas;
use App\Models\Persona;
use App\Sincronizacion\Documento;
use BackedEnum;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Search of the people attended by DEMA and their ficha with every participation.
 */
class PersonaResource extends Resource
{
    use TraduceEtiquetas;

    protected static ?string $model = Persona::class;

    protected static ?string $modelLabel = 'persona';

    protected static ?string $pluralModelLabel = 'personas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nombre';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->nombreCompleto() ?: (string) $record->documento;
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([__('DNI / NIE') => $record->documento, __('Teléfono') => $record->telefono]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nombre', 'apellido1', 'apellido2', 'documento', 'telefono', 'email'];
    }

    /**
     * Search by name in any order, normalized document, phone (ignoring spaces) or email.
     */
    public static function buscar(Builder $query, string $busqueda): Builder
    {
        $documento = Documento::persona($busqueda);
        $digitos = preg_replace('/\D/', '', $busqueda);

        return $query->where(function (Builder $query) use ($busqueda, $documento, $digitos): void {
            $query->where(function (Builder $query) use ($busqueda): void {
                foreach (preg_split('/\s+/', trim($busqueda)) as $palabra) {
                    $query->whereRaw("CONCAT_WS(' ', nombre, apellido1, apellido2) LIKE ?", ['%'.$palabra.'%']);
                }
            })
                ->orWhere('documento', 'like', '%'.($documento ?? mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $busqueda))).'%')
                ->orWhere('email', 'like', '%'.$busqueda.'%');

            if (strlen($digitos) >= 6) {
                $query->orWhereRaw("REPLACE(REPLACE(telefono, ' ', ''), '.', '') LIKE ?", ['%'.$digitos.'%']);
            }
        });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('participaciones')->with('participaciones:id,programa'))
            ->defaultSort('apellido1')
            ->searchPlaceholder(__('Nombre, apellidos, DNI, teléfono o email'))
            ->columns([
                TextColumn::make('nombre_completo')->label(__('Nombre'))
                    ->state(fn (Persona $record) => $record->nombreCompleto())
                    ->searchable(query: fn (Builder $query, string $search) => static::buscar($query, $search))
                    ->sortable(['apellido1', 'apellido2', 'nombre']),
                TextColumn::make('documento')->label(__('DNI / NIE'))->placeholder('—'),
                TextColumn::make('telefono')->label(__('Teléfono'))->toggleable(),
                TextColumn::make('email')->label(__('Email'))->toggleable(),
                TextColumn::make('poblacion')->label(__('Población'))->toggleable(),
                TextColumn::make('programas')->label(__('Programas'))->badge()
                    ->state(fn (Persona $record) => $record->participaciones->pluck('programa')->unique()->map(fn (Programa $programa) => $programa->getLabel())->values()->all()),
                TextColumn::make('participaciones_count')->label(__('Participaciones'))->sortable()->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('programa')->label(__('Programa'))->options(Programa::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, string $programa) => $query->whereHas('participaciones', fn (Builder $query) => $query->where('programa', $programa)))),
                SelectFilter::make('sexo')->label(__('Sexo'))->options(Sexo::class),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('ficha')->view('fichas.persona')->hiddenLabel()->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonas::route('/'),
            'view' => ViewPersona::route('/{record}'),
        ];
    }
}
