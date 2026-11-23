<?php

namespace App\Filament\Resources\Empresas\RelationManagers;

use App\Filament\Resources\Participaciones\TablaParticipaciones;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * Every participation of the company in DEMA programs, with the people involved.
 */
class ParticipacionesRelationManager extends RelationManager
{
    protected static string $relationship = 'participaciones';

    protected static ?string $title = 'Participaciones en programas de DEMA';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return TablaParticipaciones::configurar($table, mostrarPersonas: true, mostrarEmpresa: false);
    }
}
