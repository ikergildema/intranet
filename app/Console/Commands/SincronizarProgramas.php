<?php

namespace App\Console\Commands;

use App\Enums\Programa;
use App\Sincronizacion\Sincronizador;
use App\Sincronizacion\SincronizadorAbian;
use App\Sincronizacion\SincronizadorAnezka;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('intranet:sincronizar {programa?* : abian, anezka or enpresari; all of them when omitted}')]
#[Description('Copy people, companies and participations from the program applications into the intranet')]
class SincronizarProgramas extends Command
{
    /**
     * Sync implementations available so far, by program.
     *
     * @var array<string, class-string<Sincronizador>>
     */
    public const SINCRONIZADORES = [
        'abian' => SincronizadorAbian::class,
        'anezka' => SincronizadorAnezka::class,
    ];

    public function handle(): int
    {
        $programas = $this->argument('programa') ?: array_keys(self::SINCRONIZADORES);
        $resultado = self::SUCCESS;

        foreach ($programas as $programa) {
            if (Programa::tryFrom($programa) === null || ! isset(self::SINCRONIZADORES[$programa])) {
                $this->components->error("No hay sincronización para «{$programa}».");
                $resultado = self::FAILURE;

                continue;
            }

            $registro = null;

            try {
                $this->components->task(
                    "Sincronizando {$programa}",
                    function () use ($programa, &$registro): bool {
                        $registro = app(self::SINCRONIZADORES[$programa])->ejecutar();

                        return true;
                    },
                );
            } catch (Throwable $error) {
                $this->components->error("{$programa}: {$error->getMessage()}");
                report($error);
                $resultado = self::FAILURE;

                continue;
            }

            foreach ($registro->resumen ?? [] as $concepto => $cantidad) {
                $this->components->twoColumnDetail($concepto, (string) $cantidad);
            }
        }

        return $resultado;
    }
}
