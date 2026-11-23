<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * DEMA programs whose application feeds the intranet.
 */
enum Programa: string implements HasLabel
{
    case Abian = 'abian';
    case Anezka = 'anezka';
    case Enpresari = 'enpresari';

    public function getLabel(): string
    {
        return match ($this) {
            self::Abian => 'Abian',
            self::Anezka => 'Anezka',
            self::Enpresari => 'Enpresari',
        };
    }

    /**
     * Base URL of the program application, used to link to the original records.
     */
    public function url(): string
    {
        return rtrim((string) config("intranet.programas.{$this->value}.url"), '/');
    }
}
