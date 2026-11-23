<?php

namespace Tests\Feature;

use App\Enums\Programa;
use App\Enums\Rol;
use App\Enums\Sexo;
use App\Estadisticas\DistribucionPersonas;
use App\Filament\Pages\Estadisticas;
use App\Filament\Widgets\DistribucionPersonasChart;
use App\Filament\Widgets\TablaDistribucionPersonas;
use App\Filament\Widgets\TotalPersonasAtendidas;
use App\Models\Participacion;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class EstadisticasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_each_person_counts_once_in_the_period_whatever_their_participations(): void
    {
        $ane = $this->atendida(['sexo' => Sexo::Mujer], ['2026-02-01', '2026-05-01']);
        $this->atendida(['sexo' => Sexo::Hombre], ['2026-03-01']);
        $this->atendida(['sexo' => null], ['2026-04-01']);
        $this->atendida(['sexo' => Sexo::Mujer], ['2025-12-31']);

        $distribucion = $this->distribucion('2026-01-01', '2026-12-31');

        $this->assertSame(3, $distribucion->total());
        $this->assertSame(['Mujeres' => 1, 'Hombres' => 1, 'No consta' => 1], $distribucion->por(DistribucionPersonas::SEXO));
        $this->assertSame(2, $ane->participaciones()->count());
    }

    public function test_age_is_taken_at_the_first_participation_of_the_period(): void
    {
        $this->atendida(['fecha_nacimiento' => '1996-03-01'], ['2026-02-01', '2026-06-01']);
        $this->atendida(['fecha_nacimiento' => '1985-01-01'], ['2026-02-01']);
        $this->atendida(['fecha_nacimiento' => '1970-01-01'], ['2026-02-01']);

        $this->assertSame(
            ['Menos de 30' => 1, 'De 30 a 45' => 1, 'Más de 45' => 1],
            $this->distribucion('2026-01-01', '2026-12-31')->por(DistribucionPersonas::EDAD),
        );
    }

    public function test_a_person_is_migrant_when_migrant_in_any_participation_of_the_period(): void
    {
        $this->atendida([], ['2026-02-01', '2026-03-01'], migrante: [false, true]);
        $this->atendida([], ['2026-02-01'], migrante: [false]);
        $this->atendida([], ['2026-02-01'], migrante: [null]);

        $this->assertSame(
            ['Autóctonas' => 1, 'Migrantes' => 1, 'No consta' => 1],
            $this->distribucion('2026-01-01', '2026-12-31')->por(DistribucionPersonas::ORIGEN),
        );
    }

    public function test_only_the_main_towns_get_their_own_slice(): void
    {
        foreach (['Bilbao' => 5, 'Barakaldo' => 4, 'Getxo' => 3, 'Durango' => 2, 'Gernika' => 1, 'Ermua' => 1] as $poblacion => $personas) {
            for ($i = 0; $i < $personas; $i++) {
                $this->atendida(['poblacion' => $poblacion], ['2026-02-01']);
            }
        }
        $this->atendida(['poblacion' => null], ['2026-02-01']);

        $this->assertSame(
            ['Bilbao' => 5, 'Barakaldo' => 4, 'Getxo' => 3, 'Durango' => 2, 'Otras poblaciones' => 2, 'No consta' => 1],
            $this->distribucion('2026-01-01', '2026-12-31')->por(DistribucionPersonas::POBLACION),
        );
        $this->assertSame(
            ['Bilbao' => 5, 'Barakaldo' => 4, 'Getxo' => 3, 'Durango' => 2, 'Ermua' => 1, 'Gernika' => 1, 'No consta' => 1],
            $this->distribucion('2026-01-01', '2026-12-31')->porCompleto(DistribucionPersonas::POBLACION),
            'The full table keeps every town.',
        );

        Livewire::test(TablaDistribucionPersonas::class, ['dimension' => DistribucionPersonas::POBLACION, 'pageFilters' => ['desde' => '2026-01-01', 'hasta' => '2026-12-31']])
            ->assertSeeInOrder(['Bilbao', '5', '29,4 %', 'Ermua', 'Gernika', 'No consta']);
    }

    public function test_the_migrant_block_counts_only_migrant_people_by_area_and_country(): void
    {
        foreach (['Marruecos' => 'Magreb', 'Argelia' => 'Magreb', 'Colombia' => 'América del Sur'] as $pais => $area) {
            $this->atendida(['pais_origen' => $pais, 'area_origen' => $area], ['2026-02-01'], migrante: [true]);
        }
        $this->atendida(['pais_origen' => null, 'area_origen' => null], ['2026-02-01'], migrante: [true]);
        $this->atendida(['pais_origen' => 'Francia', 'area_origen' => 'Europa Occidental'], ['2026-02-01'], migrante: [false]);

        $migrantes = new DistribucionPersonas(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'), soloMigrantes: true);

        $this->assertSame(4, $migrantes->total());
        $this->assertSame(['Magreb' => 2, 'América del Sur' => 1, 'No consta' => 1], $migrantes->por(DistribucionPersonas::AREA_ORIGEN));
        $this->assertSame(['Argelia', 'Colombia', 'Marruecos', 'No consta'], $this->ordenarSinNoConsta(array_keys($migrantes->por(DistribucionPersonas::PAIS_ORIGEN))));
    }

    public function test_the_program_filter_keeps_only_its_participations(): void
    {
        $this->atendida([], ['2026-02-01'], programa: Programa::Abian);
        $this->atendida([], ['2026-02-01'], programa: Programa::Anezka);

        $this->assertSame(1, $this->distribucion('2026-01-01', '2026-12-31', Programa::Anezka)->total());
    }

    public function test_by_default_the_period_runs_from_the_start_of_the_current_quarter_to_today(): void
    {
        $this->travelTo('2026-11-23 10:00:00');
        $this->actingAs(User::factory()->create()->assignRole(Rol::Direccion->value));

        $pagina = Livewire::test(Estadisticas::class);
        $this->assertStringStartsWith('2026-10-01', $pagina->get('filters.desde'));
        $this->assertStringStartsWith('2026-11-23', $pagina->get('filters.hasta'));

    }

    public function test_management_can_open_the_statistics_but_technicians_cannot(): void
    {
        $this->atendida(['sexo' => Sexo::Mujer], [now()->toDateString()]);

        $this->actingAs(User::factory()->create()->assignRole(Rol::Direccion->value))
            ->get(Estadisticas::getUrl())
            ->assertOk()
            ->assertSee('Personas atendidas');

        Livewire::test(TotalPersonasAtendidas::class, ['soloMigrantes' => true, 'pageFilters' => ['desde' => now()->startOfYear()->toDateString(), 'hasta' => now()->toDateString()]])
            ->assertSee('Personas migrantes atendidas');

        Livewire::test(DistribucionPersonasChart::class, ['dimension' => DistribucionPersonas::SEXO, 'pageFilters' => ['desde' => now()->startOfYear()->toDateString(), 'hasta' => now()->toDateString()]])
            ->assertOk()
            ->assertSee('Mujeres: 1 (100,0 %)', escape: false);

        $this->actingAs(User::factory()->create()->assignRole(Rol::Tecnico->value))
            ->get(Estadisticas::getUrl())
            ->assertForbidden();
    }

    /**
     * A persona with one participation per date.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<string>  $fechas
     * @param  list<?bool>  $migrante
     */
    private function atendida(array $datos, array $fechas, array $migrante = [], Programa $programa = Programa::Abian): Persona
    {
        $persona = Persona::factory()->create($datos);

        foreach ($fechas as $indice => $fecha) {
            $persona->participaciones()->attach(
                Participacion::factory()->create(['programa' => $programa, 'fecha_inicio' => $fecha]),
                ['migrante' => $migrante[$indice] ?? null],
            );
        }

        return $persona;
    }

    /**
     * @param  list<string>  $grupos
     * @return list<string>
     */
    private function ordenarSinNoConsta(array $grupos): array
    {
        $sinNoConsta = array_values(array_diff($grupos, ['No consta']));
        sort($sinNoConsta);

        return [...$sinNoConsta, ...array_values(array_intersect($grupos, ['No consta']))];
    }

    private function distribucion(string $desde, string $hasta, ?Programa $programa = null): DistribucionPersonas
    {
        return new DistribucionPersonas(Carbon::parse($desde), Carbon::parse($hasta), $programa);
    }
}
