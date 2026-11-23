<?php

namespace App\Filament\Resources\Personas\Pages;

use App\Filament\Resources\Personas\PersonaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPersona extends ViewRecord
{
    protected static string $resource = PersonaResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->nombreCompleto() ?: __('Persona sin nombre');
    }
}
