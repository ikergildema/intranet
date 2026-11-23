<?php

namespace App\Enums;

/**
 * Interface languages. Data are not translated.
 */
enum Idioma: string
{
    case Castellano = 'es';
    case Euskara = 'eu';

    /**
     * Name of the language in that language, for the selector.
     */
    public function nombre(): string
    {
        return match ($this) {
            self::Castellano => 'Castellano',
            self::Euskara => 'Euskara',
        };
    }

    /**
     * @return list<string>
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
