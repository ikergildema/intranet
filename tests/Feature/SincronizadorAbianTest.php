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
        'situaciones_laborales', 'canales_conocimiento', 'tipos_reunion',
    ];

    private const TABLAS_ORIGEN = [
        ...self::CATALOGOS_ORIGEN,
        'users', 'empresas', 'personas', 'persona_recepcion', 'recepciones', 'llamadas', 'planes', 'reuniones', 'inscripciones_jornada', 'paises', 'areas_mundo',
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

    public function test_an_expediente_is_one_participation_with_its_call_plan_meetings_and_company(): void
    {
        $this->origen('empresas')->insert(['id' => 5, 'cif' => 'b95123456', 'nombre' => 'Txokoa SL', 'updated_at' => '2026-01-01 00:00:00']);
        $this->origen('personas')->insert([
            $this->personaOrigen(1, '11111111H'),
            $this->personaOrigen(2, '22222222J'),
        ]);
        $this->origen('users')->insert(['id' => 3, 'name' => 'Itziar']);
        $this->origen('tipos_reunion')->insert(['id' => 1, 'nombre' => 'Abian']);
        $this->origen('planes')->insert(['id' => 7, 'nombre' => 'Txokoa', 'baja' => 2, 'cerrado' => false, 'finalizado' => false, 'empresa_id' => 5, 'consultor_id' => 3]);
        $this->origen('reuniones')->insert(['plan_id' => 7, 'tipo_reunion_id' => 1, 'fecha' => '2026-04-01', 'celebrada' => true, 'no_presentado' => false]);
        $this->recepcionOrigen(10, [1, 2], planId: 7);
        $this->origen('llamadas')->insert(['recepcion_id' => 10, 'fecha' => '2026-02-20 09:30:00', 'telefonica' => false]);

        $this->sincronizar();

        $expediente = Participacion::sole();
        $this->assertSame(TipoParticipacion::Expediente, $expediente->tipo);
        $this->assertSame('Baja', $expediente->estado);
        $this->assertSame('AB-2026-00000010', $expediente->referencia);
        $this->assertSame(Empresa::where('cif', 'B95123456')->value('id'), $expediente->empresa_id);
        $this->assertEqualsCanonicalizing(['11111111H', '22222222J'], $expediente->personas->pluck('documento')->all());
        $this->assertSame('Itziar', $expediente->datos['plan']['consultor']);
        $this->assertEquals([['fecha' => '2026-04-01', 'tipo' => 'Abian', 'celebrada' => true, 'no_presentado' => false]], $expediente->datos['plan']['reuniones']);
        $this->assertCount(1, $expediente->datos['llamadas']);
        $this->assertSame('http://abianv2.dema.local/recepciones/10', $expediente->urlOrigen());
    }

    public function test_two_recepciones_count_as_two_participations_even_with_the_same_plan(): void
    {
        $this->origen('personas')->insert($this->personaOrigen(1, '11111111H'));
        $this->origen('planes')->insert(['id' => 7, 'nombre' => 'Txokoa']);
        $this->recepcionOrigen(10, [1], planId: 7);
        $this->recepcionOrigen(11, [1], planId: 7);

        $this->sincronizar();

        $this->assertSame(2, Persona::sole()->participaciones()->count());
        $this->assertSame(0, Participacion::where('tipo', TipoParticipacion::PlanNegocio)->count());
    }

    public function test_an_old_plan_without_recepcion_is_a_participation_of_its_own(): void
    {
        $this->origen('planes')->insert(['id' => 7, 'nombre' => 'Antiguo', 'cerrado' => true, 'fecha_final' => '2015-06-30']);

        $this->sincronizar();

        $plan = Participacion::sole();
        $this->assertSame(TipoParticipacion::PlanNegocio, $plan->tipo);
        $this->assertSame('Cerrado', $plan->estado);
        $this->assertSame('2015-06-30', $plan->fecha_fin->toDateString());
    }

    public function test_migrant_status_follows_the_abianv2_rule_by_recepcion_date(): void
    {
        config(['intranet.abian_migrante_por_pais_desde' => '2025-09-19']);
        $this->origen('areas_mundo')->insert(['id' => 3, 'nombre' => 'América del Sur']);
        $this->origen('paises')->insert(['id' => 33, 'nombre' => 'Colombia', 'area_mundo_id' => 3]);
        $this->origen('personas')->insert([
            $this->personaOrigen(1, 'X1234567L'),
            ['pais_id' => 33] + $this->personaOrigen(2, '11111111H'),
            $this->personaOrigen(3, 'X7654321A'),
        ]);
        $this->recepcionOrigen(10, [1, 2], fecha: '2025-09-18');
        $this->recepcionOrigen(11, [2, 3], fecha: '2025-09-19');

        $this->sincronizar();

        $migrante = fn (int $recepcion, string $documento) => DB::table('participacion_persona')
            ->join('participaciones', 'participaciones.id', '=', 'participacion_persona.participacion_id')
            ->join('personas', 'personas.id', '=', 'participacion_persona.persona_id')
            ->where('participaciones.origen_id', $recepcion)
            ->where('personas.documento', $documento)
            ->value('migrante');

        $this->assertSame(['Colombia', 'América del Sur'], array_values(Persona::where('documento', '11111111H')->first(['pais_origen', 'area_origen'])->only(['pais_origen', 'area_origen'])));
        $this->assertSame(1, $migrante(10, 'X1234567L'), 'Before the cut-off, a foreign document counts.');
        $this->assertSame(0, $migrante(10, '11111111H'), 'Before the cut-off, the country of origin does not count.');
        $this->assertSame(1, $migrante(11, '11111111H'), 'From the cut-off, the country of origin counts.');
        $this->assertSame(0, $migrante(11, 'X7654321A'), 'From the cut-off, a foreign document alone does not count.');
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
        return ['id' => $id, 'dni' => $dni, 'nombre' => $nombre, 'apellido1' => 'Etxeberria', 'sexo' => 2, 'pais_id' => null, 'updated_at' => $actualizada];
    }

    /**
     * @param  list<int>  $personaIds
     */
    private function recepcionOrigen(int $id, array $personaIds, ?int $planId = null, string $fecha = '2026-03-01'): void
    {
        $this->origen('recepciones')->insert([
            'id' => $id,
            'ano' => 2026,
            'nombre' => 'Idea '.$id,
            'fecha_solicitud' => $fecha,
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

        $esquema->create('areas_mundo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });

        $esquema->create('paises', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('area_mundo_id')->nullable();
        });

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
            $table->unsignedBigInteger('pais_id')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });

        $esquema->create('persona_recepcion', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('orden')->default(1);
            $table->unsignedBigInteger('recepcion_id');
            $table->unsignedBigInteger('persona_id');
        });

        $esquema->create('recepciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('ano')->nullable();
            $table->string('nombre')->nullable();
            $table->date('fecha_solicitud')->nullable();
            $table->time('hora_solicitud')->nullable();
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

        $esquema->create('llamadas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recepcion_id')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->boolean('telefonica')->default(false);
            $table->timestamp('deleted_at')->nullable();
        });

        $esquema->create('reuniones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('tipo_reunion_id')->nullable();
            $table->date('fecha')->nullable();
            $table->boolean('celebrada')->default(false);
            $table->boolean('no_presentado')->default(false);
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
