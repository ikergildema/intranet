<?php

namespace Tests\Feature;

use App\Enums\Programa;
use App\Enums\Sexo;
use App\Enums\TipoParticipacion;
use App\Models\Participacion;
use App\Models\Persona;
use App\Sincronizacion\SincronizadorAnezka;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SincronizadorAnezkaTest extends TestCase
{
    use RefreshDatabase;

    private const CATALOGOS = ['municipalities', 'genders', 'participant_statuses', 'employment_situations', 'education_levels'];

    private const TABLAS = [...self::CATALOGOS, 'convocatorias', 'users', 'lanzaderas', 'participants'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.anezka' => [
            ...config('database.connections.'.config('database.default')),
            'prefix' => 'origen_anezka_',
        ]]);
        DB::purge('anezka');

        $this->crearTablasOrigen();
        $this->beforeApplicationDestroyed(fn () => $this->eliminarTablasOrigen());
    }

    public function test_each_registration_is_a_participation_with_its_convocatoria_and_lanzadera(): void
    {
        $this->origen('convocatorias')->insert(['id' => 1, 'name' => 'Año 2026']);
        $this->origen('participant_statuses')->insert(['id' => 3, 'name_es' => 'Seleccionado']);
        $this->origen('municipalities')->insert(['id' => 9, 'name_es' => 'Barakaldo']);
        $this->origen('users')->insert(['id' => 4, 'name' => 'Nerea']);
        $this->origen('lanzaderas')->insert(['id' => 70, 'name' => 'ANEZKA 70', 'location' => 'Barakaldo', 'starts_on' => '2026-03-09', 'ends_on' => '2026-06-12', 'coordinator_id' => 4]);
        $this->inscripcion(1, '1234567-l', ['lanzadera_id' => 70, 'convocatoria_id' => 1, 'participant_status_id' => 3, 'municipality_id' => 9, 'gender_id' => 3, 'talk_at' => '2026-02-10 10:00:00']);
        $this->inscripcion(2, '01234567L', ['registered_at' => '2025-05-02 09:00:00']);

        app(SincronizadorAnezka::class)->ejecutar();

        $persona = Persona::sole();
        $this->assertSame('01234567L', $persona->documento);
        $this->assertSame(Sexo::NoBinario, $persona->sexo);
        $this->assertSame(2, $persona->participaciones()->count());

        $inscripcion = Participacion::where('origen_id', 1)->sole();
        $this->assertSame(TipoParticipacion::Inscripcion, $inscripcion->tipo);
        $this->assertSame('Año 2026', $inscripcion->referencia);
        $this->assertSame('Seleccionado', $inscripcion->estado);
        $this->assertSame('Nerea', $inscripcion->responsable);
        $this->assertSame('ANEZKA 70', $inscripcion->datos['lanzadera']['nombre']);
        $this->assertSame('http://anezka.dema.local/participants/1', $inscripcion->urlOrigen());
    }

    public function test_a_person_already_in_abian_gets_the_anezka_registration_in_the_same_ficha(): void
    {
        $persona = Persona::factory()->create(['documento' => '11111111H']);
        $persona->origenes()->create(['programa' => Programa::Abian, 'origen_tabla' => 'personas', 'origen_id' => 5, 'sincronizado_at' => now()]);
        $persona->participaciones()->attach(Participacion::factory()->create(['programa' => Programa::Abian]));
        $this->inscripcion(1, '11111111-h');

        app(SincronizadorAnezka::class)->ejecutar();

        $this->assertSame(1, Persona::count());
        $this->assertEqualsCanonicalizing([Programa::Abian, Programa::Anezka], $persona->participaciones()->get()->pluck('programa')->all());
        $this->assertSame(2, $persona->origenes()->count());
    }

    public function test_a_foreign_document_counts_as_migrant(): void
    {
        $this->inscripcion(1, '11111111H', ['document_type' => 'dni']);
        $this->inscripcion(2, 'X1234567L', ['document_type' => 'nie']);
        $this->inscripcion(3, 'PA1234567', ['document_type' => 'other']);
        $this->inscripcion(4, '22222222J', ['document_type' => null]);

        app(SincronizadorAnezka::class)->ejecutar();

        $this->assertSame(
            [1 => 0, 2 => 1, 3 => 1, 4 => null],
            DB::table('participacion_persona')
                ->join('participaciones', 'participaciones.id', '=', 'participacion_persona.participacion_id')
                ->orderBy('participaciones.origen_id')
                ->pluck('participacion_persona.migrante', 'participaciones.origen_id')
                ->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function inscripcion(int $id, ?string $documento, array $datos = []): void
    {
        $this->origen('participants')->insert($datos + [
            'id' => $id,
            'document_number' => $documento,
            'document_type' => 'dni',
            'first_name' => 'Ane',
            'last_name_1' => 'Etxeberria',
            'registered_at' => '2026-01-15 10:00:00',
            'updated_at' => '2026-01-15 10:00:00',
        ]);
    }

    private function origen(string $tabla): Builder
    {
        return DB::connection('anezka')->table($tabla);
    }

    /**
     * Minimal copy of the Anezka tables, with only the columns the sync reads.
     */
    private function crearTablasOrigen(): void
    {
        $this->eliminarTablasOrigen();
        $esquema = Schema::connection('anezka');

        foreach (self::CATALOGOS as $catalogo) {
            $esquema->create($catalogo, function (Blueprint $table) {
                $table->id();
                $table->string('name_es');
            });
        }

        $esquema->create('convocatorias', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $esquema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        $esquema->create('lanzaderas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('coordinator_id')->nullable();
        });

        $esquema->create('participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lanzadera_id')->nullable();
            $table->unsignedBigInteger('convocatoria_id')->nullable();
            $table->unsignedBigInteger('participant_status_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name_1')->nullable();
            $table->string('last_name_2')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedBigInteger('gender_id')->nullable();
            $table->unsignedBigInteger('municipality_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('employment_situation_id')->nullable();
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->dateTime('registered_at')->nullable();
            $table->dateTime('talk_at')->nullable();
            $table->dateTime('interview_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
    }

    private function eliminarTablasOrigen(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::connection('anezka')->dropIfExists($tabla);
        }
    }
}
