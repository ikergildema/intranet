<?php

namespace Tests\Feature;

use App\Enums\Programa;
use App\Enums\Rol;
use App\Enums\TipoParticipacion;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\Personas\Pages\ListPersonas;
use App\Filament\Resources\Personas\PersonaResource;
use App\Models\Empresa;
use App\Models\Participacion;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FichasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(User::factory()->create()->assignRole(Rol::Tecnico->value));
    }

    public function test_people_are_found_by_document_written_in_any_format(): void
    {
        $ane = Persona::factory()->create(['documento' => '01234567L']);
        $otra = Persona::factory()->create(['documento' => '22222222J']);

        Livewire::test(ListPersonas::class)
            ->searchTable('1234567-l')
            ->assertCanSeeTableRecords([$ane])
            ->assertCanNotSeeTableRecords([$otra]);
    }

    public function test_people_are_found_by_name_and_surnames_in_any_order_and_by_phone_without_spaces(): void
    {
        $ane = Persona::factory()->create(['nombre' => 'Ane', 'apellido1' => 'Etxeberria', 'apellido2' => 'Goikoetxea', 'telefono' => '600 11 22 33']);
        $otra = Persona::factory()->create(['nombre' => 'Miren', 'apellido1' => 'Etxeberria', 'telefono' => '699999999']);

        Livewire::test(ListPersonas::class)
            ->searchTable('etxeberria ane')
            ->assertCanSeeTableRecords([$ane])
            ->assertCanNotSeeTableRecords([$otra])
            ->searchTable('600112233')
            ->assertCanSeeTableRecords([$ane])
            ->assertCanNotSeeTableRecords([$otra]);
    }

    public function test_the_persona_ficha_shows_each_expediente_like_the_abianv2_quick_search(): void
    {
        $persona = Persona::factory()->create(['nombre' => 'Ane', 'apellido1' => 'Etxeberria', 'apellido2' => null, 'pais_origen' => 'Colombia', 'area_origen' => 'América del Sur']);
        $empresa = Empresa::factory()->create(['nombre' => 'Txokoa SL', 'estado' => 'Funcionando']);
        $expediente = Participacion::factory()->create([
            'origen_id' => 10,
            'referencia' => 'AB-2026-00000010',
            'estado' => 'En curso',
            'responsable' => 'Itziar',
            'empresa_id' => $empresa->id,
            'datos' => [
                'resultado' => 2,
                'resultado_etiqueta' => 'Abian',
                'llamadas' => [['fecha' => '2026-02-20 09:30:00', 'telefonica' => false]],
                'plan' => [
                    'id' => 7, 'nombre' => 'Txokoa', 'consultor' => 'Mikel', 'tipo_plan' => 'Sortu',
                    'fecha_asignacion' => '2026-03-02', 'fecha_final' => null, 'cerrado' => false, 'baja' => false, 'motivo_baja' => null,
                    'reuniones' => [['fecha' => '2026-04-01', 'tipo' => 'Abian', 'celebrada' => true, 'no_presentado' => false]],
                ],
            ],
        ]);
        $ajena = Participacion::factory()->create(['referencia' => 'AB-2026-00000099']);
        $persona->participaciones()->attach($expediente);

        $this->get(PersonaResource::getUrl('view', ['record' => $persona]))
            ->assertOk()
            ->assertSeeInOrder(['Ane Etxeberria', 'Colombia', 'América del Sur', '1 participación', 'AB-2026-00000010', 'Atendida por Itziar', 'En curso', 'Llamada del 20/02/2026 09:30', 'Plan de negocio', 'Mikel', '01/04/2026', 'Txokoa SL'])
            ->assertSee('http://abianv2.dema.local/recepciones/10')
            ->assertSee(EmpresaResource::getUrl('view', ['record' => $empresa]))
            ->assertDontSee($ajena->referencia);
    }

    public function test_companies_are_found_by_cif_and_their_ficha_shows_the_people_of_each_participation(): void
    {
        $empresa = Empresa::factory()->create(['cif' => 'B95123456']);
        $otra = Empresa::factory()->create(['cif' => 'B11111111']);
        $plan = Participacion::factory()->create(['tipo' => TipoParticipacion::PlanNegocio, 'empresa_id' => $empresa->id]);
        $persona = Persona::factory()->create(['nombre' => 'Ane', 'apellido1' => 'Etxeberria', 'apellido2' => null]);
        $plan->personas()->attach($persona);

        Livewire::test(ListEmpresas::class)
            ->searchTable('b-95.123.456')
            ->assertCanSeeTableRecords([$empresa])
            ->assertCanNotSeeTableRecords([$otra]);

        $this->get(EmpresaResource::getUrl('view', ['record' => $empresa]))
            ->assertOk()
            ->assertSee('Ane Etxeberria')
            ->assertSee(PersonaResource::getUrl('view', ['record' => $persona]));
    }

    public function test_people_can_be_filtered_by_program(): void
    {
        $enAbian = Persona::factory()->create();
        $enAbian->participaciones()->attach(Participacion::factory()->create(['programa' => Programa::Abian]));
        $sinAbian = Persona::factory()->create();

        Livewire::test(ListPersonas::class)
            ->filterTable('programa', Programa::Abian->value)
            ->assertCanSeeTableRecords([$enAbian])
            ->assertCanNotSeeTableRecords([$sinAbian]);
    }

    public function test_the_registry_is_read_only(): void
    {
        $this->get('/personas/create')->assertNotFound();
        $this->assertFalse(PersonaResource::canEdit(Persona::factory()->create()));
        $this->assertFalse(EmpresaResource::canDelete(Empresa::factory()->create()));
    }
}
