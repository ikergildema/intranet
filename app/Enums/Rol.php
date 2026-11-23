<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Roles of the intranet: root manages everything, dirección sees all information
 * and técnicos search people and companies and read their fichas.
 */
enum Rol: string implements HasLabel
{
    case Root = 'root';
    case Direccion = 'direccion';
    case Tecnico = 'tecnico';

    public function getLabel(): string
    {
        return match ($this) {
            self::Root => 'Root',
            self::Direccion => __('Dirección'),
            self::Tecnico => __('Técnico/a'),
        };
    }
}
