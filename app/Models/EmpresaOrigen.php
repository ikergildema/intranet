<?php

namespace App\Models;

use App\Enums\Programa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Source record of a program application that was merged into an empresa.
 */
class EmpresaOrigen extends Model
{
    public $timestamps = false;

    protected $table = 'empresa_origenes';

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
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
