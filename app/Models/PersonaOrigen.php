<?php

namespace App\Models;

use App\Enums\Programa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source record of a program application that was merged into a persona.
 */
class PersonaOrigen extends Model
{
    public $timestamps = false;

    protected $table = 'persona_origenes';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'programa' => Programa::class,
            'sincronizado_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Persona, $this>
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
