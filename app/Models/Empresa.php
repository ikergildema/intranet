<?php

namespace App\Models;

use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A company related to DEMA programs, merged across programs by normalized CIF.
 */
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_alta' => 'date',
            'datos_actualizados_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Participacion, $this>
     */
    public function participaciones(): HasMany
    {
        return $this->hasMany(Participacion::class);
    }

    /**
     * @return HasMany<EmpresaOrigen, $this>
     */
    public function origenes(): HasMany
    {
        return $this->hasMany(EmpresaOrigen::class);
    }
}
