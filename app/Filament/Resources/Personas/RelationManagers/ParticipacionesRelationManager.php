<?php

namespace App\Filament\Resources\Personas\RelationManagers;

use App\Filament\Resources\Participaciones\TablaParticipaciones;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * Every participation of the persona in DEMA programs.
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
        return TablaParticipaciones::configurar($table, mostrarPersonas: false, mostrarEmpresa: true);
    }
}
