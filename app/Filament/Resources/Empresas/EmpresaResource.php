<?php

namespace App\Filament\Resources\Empresas;

use App\Enums\Programa;
use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\Empresas\Pages\ViewEmpresa;
use App\Filament\Resources\Empresas\RelationManagers\ParticipacionesRelationManager;
use App\Models\Empresa;
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
 * Search of the companies related to DEMA programs and their ficha with every participation.
 */
class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $pluralModelLabel = 'empresas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 2;

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

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter(['CIF' => $record->cif, 'Población' => $record->poblacion]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['nombre', 'cif', 'email'];
    }

    /**
     * Search by name, normalized CIF or email.
     */
    public static function buscar(Builder $query, string $busqueda): Builder
    {
        $cif = Documento::empresa($busqueda) ?? mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $busqueda));

        return $query->where(fn (Builder $query) => $query
            ->where('nombre', 'like', '%'.$busqueda.'%')
            ->orWhere('cif', 'like', '%'.$cif.'%')
            ->orWhere('email', 'like', '%'.$busqueda.'%'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('participaciones'))
            ->defaultSort('nombre')
            ->searchPlaceholder('Nombre, CIF o email')
            ->columns([
                TextColumn::make('nombre')->searchable(query: fn (Builder $query, string $search) => static::buscar($query, $search))->sortable()->wrap(),
                TextColumn::make('cif')->label('CIF')->placeholder('—'),
                TextColumn::make('sector')->label('Sector de actividad')->toggleable()->sortable(),
                TextColumn::make('poblacion')->label('Población')->toggleable()->sortable(),
                TextColumn::make('estado')->badge()->color('gray')->toggleable(),
                TextColumn::make('fecha_alta')->label('Alta')->date('d/m/Y')->sortable()->toggleable(),
                TextColumn::make('participaciones_count')->label('Participaciones')->sortable()->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('programa')->options(Programa::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, string $programa) => $query->whereHas('participaciones', fn (Builder $query) => $query->where('programa', $programa)))),
                SelectFilter::make('estado')->options(fn () => Empresa::query()->whereNotNull('estado')->distinct()->orderBy('estado')->pluck('estado', 'estado')->all()),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos de la empresa')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('nombre')->columnSpan(2),
                TextEntry::make('cif')->label('CIF')->placeholder('Sin CIF fiable'),
                TextEntry::make('estado')->badge()->color('gray')->placeholder('—'),
                TextEntry::make('sector')->label('Sector de actividad')->placeholder('—'),
                TextEntry::make('forma_juridica')->label('Forma jurídica')->placeholder('—'),
                TextEntry::make('fecha_alta')->label('Fecha de alta')->date('d/m/Y')->placeholder('—'),
                TextEntry::make('poblacion')->label('Población')->placeholder('—'),
                TextEntry::make('telefono')->label('Teléfono')->placeholder('—'),
                TextEntry::make('email')->placeholder('—'),
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
            'index' => ListEmpresas::route('/'),
            'view' => ViewEmpresa::route('/{record}'),
        ];
    }
}
