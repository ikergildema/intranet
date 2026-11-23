<?php

namespace Tests\Feature;

use App\Enums\Programa;
use App\Enums\TipoParticipacion;
use App\Models\Empresa;
use App\Models\Participacion;
use App\Models\Persona;
use App\Models\Sincronizacion;
use App\Sincronizacion\SincronizadorAbian;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SincronizadorAbianTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Source tables are created in the test database with this prefix, so they don't clash
     * with the intranet's own `personas` and `empresas`.
     */
    private const PREFIJO_ORIGEN = 'origen_';

    private const CATALOGOS_ORIGEN = [
        'poblaciones', 'actividades', 'formas_juridicas', 'estados_empresa', 'tipos_plan', 'motivos_baja',
        'situaciones_laborales', 'canales_conocimiento',
    ];

    private const TABLAS_ORIGEN = [
        ...self::CATALOGOS_ORIGEN,
        'users', 'empresas', 'personas', 'persona_recepcion', 'recepciones', 'planes', 'reuniones', 'inscripciones_jornada',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.abian' => [
            ...config('database.connections.'.config('database.default')),
            'prefix' => self::PREFIJO_ORIGEN,
        ]]);
        DB::purge('abian');

        $this->crearTablasOrigen();
        $this->beforeApplicationDestroyed(fn () => $this->eliminarTablasOrigen());
    }

    public function test_records_with_the_same_document_become_one_persona_with_the_newest_data(): void
    {
        $this->origen('personas')->insert([
            $this->personaOrigen(1, '1234567-l', 'Ane', '2025-01-01 10:00:00'),
            $this->personaOrigen(2, '01234567L', 'Ane María', '2026-05-01 10:00:00'),
        ]);
        $this->recepcionOrigen(10, [1]);
        $this->recepcionOrigen(11, [2]);

        $this->sincronizar();

        $persona = Persona::where('documento', '01234567L')->sole();
        $this->assertSame('Ane María', $persona->nombre);
        $this->assertCount(2, $persona->origenes);
        $this->assertEqualsCanonicalizing([10, 11], $persona->participaciones->pluck('origen_id')->all());
    }

    public function test_records_without_a_usable_document_keep_their_own_persona(): void
    {
        $this->origen('personas')->insert([
            $this->personaOrigen(1, null, 'Sin DNI'),
            $this->personaOrigen(2, '', 'Tampoco'),
        ]);

        $this->sincronizar();

        $this->assertSame(2, Persona::whereNull('documento')->count());
    }

    public function test_a_plan_is_linked_to_its_promoters_through_the_reception_and_to_its_company(): void
    {
        $this->origen('empresas')->insert(['id' => 5, 'cif' => 'b95123456', 'nombre' => 'Txokoa SL', 'updated_at' => '2026-01-01 00:00:00']);
        $this->origen('personas')->insert([
            $this->personaOrigen(1, '11111111H'),
            $this->personaOrigen(2, '22222222J'),
        ]);
        $this->origen('planes')->insert(['id' => 7, 'nombre' => 'Txokoa', 'baja' => 2, 'cerrado' => false, 'finalizado' => false, 'empresa_id' => 5]);
        $this->recepcionOrigen(10, [1, 2], planId: 7);

        $this->sincronizar();

        $plan = Participacion::where('tipo', TipoParticipacion::PlanNegocio)->where('origen_id', 7)->sole();
        $this->assertSame('Baja', $plan->estado);
        $this->assertSame('AB-2026-00000010', $plan->referencia);
        $this->assertSame(Empresa::where('cif', 'B95123456')->value('id'), $plan->empresa_id);
        $this->assertEqualsCanonicalizing(['11111111H', '22222222J'], $plan->personas->pluck('documento')->all());
        $this->assertSame('http://abianv2.dema.local/planes/7', $plan->urlOrigen());
    }

    public function test_running_again_is_idempotent_and_removes_what_disappeared_in_the_source(): void
    {
        $this->origen('personas')->insert([
            $this->personaOrigen(1, '11111111H'),
            $this->personaOrigen(2, '22222222J'),
        ]);
        $this->recepcionOrigen(10, [1]);
        $this->recepcionOrigen(11, [2]);

        $this->sincronizar();
        $this->origen('recepciones')->where('id', 11)->update(['deleted_at' => now()]);
        $this->origen('personas')->where('id', 2)->update(['deleted_at' => now()]);
        $this->travel(1)->seconds();
        $this->sincronizar();

        $this->assertSame([10], Participacion::pluck('origen_id')->all());
        $this->assertSame(['11111111H'], Persona::pluck('documento')->all());
        $this->assertSame(1, DB::table('participacion_persona')->count());
    }

    public function test_a_failed_run_is_logged_and_keeps_the_previous_data(): void
    {
        $this->origen('personas')->insert($this->personaOrigen(1, '11111111H'));
        $this->recepcionOrigen(10, [1]);
        $this->sincronizar();

        Schema::connection('abian')->drop('planes');
        $this->travel(1)->seconds();

        try {
            $this->sincronizar();
            $this->fail('The sync should have failed without the planes table.');
        } catch (QueryException) {
            // Expected: the source table is missing.
        }

        $this->assertSame(Sincronizacion::ESTADO_FALLIDA, Sincronizacion::latest('id')->value('estado'));
        $this->assertSame(1, Participacion::where('programa', Programa::Abian)->count());
    }

    private function sincronizar(): void
    {
        app(SincronizadorAbian::class)->ejecutar();
    }

    private function origen(string $tabla): Builder
    {
        return DB::connection('abian')->table($tabla);
    }

    /**
     * @return array<string, mixed>
     */
    private function personaOrigen(int $id, ?string $dni, string $nombre = 'Persona', string $actualizada = '2026-01-01 00:00:00'): array
    {
        return ['id' => $id, 'dni' => $dni, 'nombre' => $nombre, 'apellido1' => 'Etxeberria', 'sexo' => 2, 'updated_at' => $actualizada];
    }

    /**
     * @param  list<int>  $personaIds
     */
    private function recepcionOrigen(int $id, array $personaIds, ?int $planId = null): void
    {
        $this->origen('recepciones')->insert([
            'id' => $id,
            'nexpediente' => sprintf('AB-2026-%08d', $id),
            'nombre' => 'Idea '.$id,
            'fecha_solicitud' => '2026-03-01',
            'resultado' => 2,
            'plan_id' => $planId,
        ]);

        foreach ($personaIds as $personaId) {
            $this->origen('persona_recepcion')->insert(['recepcion_id' => $id, 'persona_id' => $personaId]);
        }
    }

    /**
     * Minimal copy of the abianv2 tables, with only the columns the sync reads.
     */
    private function crearTablasOrigen(): void
    {
        $this->eliminarTablasOrigen();
        $esquema = Schema::connection('abian');

        foreach (self::CATALOGOS_ORIGEN as $catalogo) {
            $esquema->create($catalogo, function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
            });
        }

        $esquema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $esquema->create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('cif')->nullable();
            $table->string('nombre')->nullable();
            $table->unsignedBigInteger('actividad_id')->nullable();
            $table->string('actividad_texto')->nullable();
            $table->unsignedBigInteger('forma_juridica_id')->nullable();
            $table->unsignedBigInteger('poblacion_id')->nullable();
            $table->date('fecha_alta')->nullable();
            $table->unsignedBigInteger('estado_empresa_id')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        $esquema->create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('dni')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido1')->nullable();
            $table->string('apellido2')->nullable();
            $table->unsignedTinyInteger('sexo')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('poblacion_id')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        $esquema->create('persona_recepcion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recepcion_id');
            $table->unsignedBigInteger('persona_id');
        });

        $esquema->create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->string('nexpediente')->nullable();
            $table->string('nombre')->nullable();
            $table->date('fecha_solicitud')->nullable();
            $table->unsignedSmallInteger('resultado')->default(0);
            $table->unsignedBigInteger('consultor_id')->nullable();
            $table->unsignedBigInteger('actividad_id')->nullable();
            $table->unsignedBigInteger('situacion_laboral_id')->nullable();
            $table->unsignedBigInteger('canal_conocimiento_id')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        $esquema->create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_asignacion')->nullable();
            $table->date('fecha_final')->nullable();
            $table->unsignedTinyInteger('baja')->default(0);
            $table->boolean('cerrado')->default(false);
            $table->boolean('finalizado')->default(false);
            $table->unsignedBigInteger('consultor_id')->nullable();
            $table->unsignedBigInteger('tipo_plan_id')->nullable();
            $table->unsignedBigInteger('actividad_id')->nullable();
            $table->unsignedBigInteger('motivo_baja_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
        });

        $esquema->create('reuniones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->boolean('celebrada')->default(false);
            $table->timestamp('deleted_at')->nullable();
        });

        $esquema->create('inscripciones_jornada', function (Blueprint $table) {
            $table->id();
            $table->string('dni')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido1')->nullable();
            $table->string('apellido2')->nullable();
            $table->unsignedTinyInteger('sexo')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('localidad')->nullable();
            $table->string('jornada')->nullable();
            $table->date('fecha_jornada')->nullable();
            $table->text('actividad')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    private function eliminarTablasOrigen(): void
    {
        foreach (self::TABLAS_ORIGEN as $tabla) {
            Schema::connection('abian')->dropIfExists($tabla);
        }
    }
}
