<?php

namespace App\Filament\Support;

use Illuminate\Support\Str;

/**
 * For Filament resources: translates the labels set in static properties
 * ($modelLabel, $pluralModelLabel, $navigationLabel), which cannot call __().
 */
trait TraduceEtiquetas
{
    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    /**
     * Plural computed on the Spanish text (otherwise it would pluralize the Basque word).
     */
    public static function getPluralModelLabel(): string
    {
        $plural = static::$pluralModelLabel
            ?? (static::$modelLabel !== null ? Str::plural(static::$modelLabel) : parent::getPluralModelLabel());

        return __($plural);
    }

    /**
     * Without $navigationLabel, the model plural with only its first letter capitalized.
     */
    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel !== null
            ? __(static::$navigationLabel)
            : Str::ucfirst(static::getPluralModelLabel());
    }
}
