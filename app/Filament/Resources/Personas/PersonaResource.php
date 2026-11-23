<?php

namespace App\Filament\Resources\Personas;

use App\Enums\Programa;
use App\Enums\Sexo;
use App\Filament\Resources\Personas\Pages\ListPersonas;
use App\Filament\Resources\Personas\Pages\ViewPersona;
use App\Filament\Resources\Personas\RelationManagers\ParticipacionesRelationManager;
use App\Models\Persona;
use App\Sincronizacion\Documento;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
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
        return array_filter(['DNI' => $record->documento, 'Teléfono' => $record->telefono]);
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
            ->searchPlaceholder('Nombre, apellidos, DNI, teléfono o email')
            ->columns([
                TextColumn::make('nombre_completo')->label('Nombre')
                    ->state(fn (Persona $record) => $record->nombreCompleto())
                    ->searchable(query: fn (Builder $query, string $search) => static::buscar($query, $search))
                    ->sortable(['apellido1', 'apellido2', 'nombre']),
                TextColumn::make('documento')->label('DNI / NIE')->placeholder('—'),
                TextColumn::make('telefono')->label('Teléfono')->toggleable(),
                TextColumn::make('email')->toggleable(),
                TextColumn::make('poblacion')->label('Población')->toggleable(),
                TextColumn::make('programas')->label('Programas')->badge()
                    ->state(fn (Persona $record) => $record->participaciones->pluck('programa')->unique()->map(fn (Programa $programa) => $programa->getLabel())->values()->all()),
                TextColumn::make('participaciones_count')->label('Participaciones')->sortable()->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('programa')->options(Programa::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, string $programa) => $query->whereHas('participaciones', fn (Builder $query) => $query->where('programa', $programa)))),
                SelectFilter::make('sexo')->options(Sexo::class),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos personales')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('nombre')->placeholder('—'),
                TextEntry::make('apellido1')->label('Primer apellido')->placeholder('—'),
                TextEntry::make('apellido2')->label('Segundo apellido')->placeholder('—'),
                TextEntry::make('documento')->label('DNI / NIE')->placeholder('Sin documento fiable')
                    ->helperText(fn (Persona $record) => $record->documento_original !== null && $record->documento_original !== $record->documento ? 'Tal como se escribió: '.$record->documento_original : null),
                TextEntry::make('sexo')->placeholder('—'),
                TextEntry::make('fecha_nacimiento')->label('Fecha de nacimiento')->date('d/m/Y')->placeholder('—')
                    ->helperText(fn (Persona $record) => $record->fecha_nacimiento ? $record->fecha_nacimiento->age.' años' : null),
                TextEntry::make('telefono')->label('Teléfono')->placeholder('—'),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('poblacion')->label('Población')->placeholder('—'),
                TextEntry::make('datos_actualizados_at')->label('Datos actualizados en origen')->dateTime('d/m/Y H:i')->placeholder('—'),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [ParticipacionesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonas::route('/'),
            'view' => ViewPersona::route('/{record}'),
        ];
    }
}
