<?php

namespace App\Models;

use App\Enums\Programa;
use Illuminate\Database\Eloquent\Model;

/**
 * Log of one sync run of a program.
 */
class Sincronizacion extends Model
{
    public const ESTADO_EN_CURSO = 'en_curso';

    public const ESTADO_CORRECTA = 'correcta';

    public const ESTADO_FALLIDA = 'fallida';

    protected $table = 'sincronizaciones';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'programa' => Programa::class,
            'iniciada_at' => 'datetime',
            'finalizada_at' => 'datetime',
            'resumen' => 'array',
        ];
    }
}
