<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Sexo: string implements HasLabel
{
    case Hombre = 'hombre';
    case Mujer = 'mujer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hombre => __('Hombre'),
            self::Mujer => __('Mujer'),
        };
    }
}
