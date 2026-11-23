<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Kind of participation of a person or company in a program.
 */
enum TipoParticipacion: string implements HasLabel
{
    case Recepcion = 'recepcion';
    case PlanNegocio = 'plan_negocio';
    case Jornada = 'jornada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Recepcion => __('Recepción'),
            self::PlanNegocio => __('Plan de negocio'),
            self::Jornada => __('Jornada'),
        };
    }
}
