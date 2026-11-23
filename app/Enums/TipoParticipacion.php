<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Kind of participation of a person or company in a program.
 */
enum TipoParticipacion: string implements HasLabel
{
    /** Abian: a recepción with its call, business plan, meetings and company. */
    case Expediente = 'expediente';

    /** Abian: an old business plan without recepción. */
    case PlanNegocio = 'plan_negocio';
    case Jornada = 'jornada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Expediente => __('Expediente'),
            self::PlanNegocio => __('Plan de negocio'),
            self::Jornada => __('Jornada'),
        };
    }
}
