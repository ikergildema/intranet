<?php

namespace Tests\Feature;

use App\Enums\Programa;
use App\Enums\Rol;
use App\Enums\TipoParticipacion;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Filament\Resources\Empresas\Pages\ListEmpresas;
use App\Filament\Resources\Empresas\Pages\ViewEmpresa;
use App\Filament\Resources\Empresas\RelationManagers\ParticipacionesRelationManager as ParticipacionesEmpresa;
use App\Filament\Resources\Personas\Pages\ListPersonas;
use App\Filament\Resources\Personas\Pages\ViewPersona;
use App\Filament\Resources\Personas\PersonaResource;
use App\Filament\Resources\Personas\RelationManagers\ParticipacionesRelationManager as ParticipacionesPersona;
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

    public function test_the_persona_ficha_lists_every_participation_with_a_link_to_the_original_record(): void
    {
        $persona = Persona::factory()->create();
        $recepcion = Participacion::factory()->create(['origen_id' => 10]);
        $plan = Participacion::factory()->create(['tipo' => TipoParticipacion::PlanNegocio, 'origen_id' => 7, 'empresa_id' => Empresa::factory()->create()->id]);
        $ajena = Participacion::factory()->create();
        $persona->participaciones()->attach([$recepcion->id, $plan->id]);

        $this->get(PersonaResource::getUrl('view', ['record' => $persona]))->assertOk();

        Livewire::test(ParticipacionesPersona::class, ['ownerRecord' => $persona, 'pageClass' => ViewPersona::class])
            ->assertCanSeeTableRecords([$recepcion, $plan])
            ->assertCanNotSeeTableRecords([$ajena])
            ->assertTableActionHasUrl('origen', 'http://abianv2.dema.local/planes/7', record: $plan);
    }

    public function test_companies_are_found_by_cif_and_their_ficha_shows_the_people_of_each_participation(): void
    {
        $empresa = Empresa::factory()->create(['cif' => 'B95123456']);
        $otra = Empresa::factory()->create(['cif' => 'B11111111']);
        $plan = Participacion::factory()->create(['tipo' => TipoParticipacion::PlanNegocio, 'empresa_id' => $empresa->id]);
        $plan->personas()->attach(Persona::factory()->create(['nombre' => 'Ane', 'apellido1' => 'Etxeberria', 'apellido2' => null]));

        Livewire::test(ListEmpresas::class)
            ->searchTable('b-95.123.456')
            ->assertCanSeeTableRecords([$empresa])
            ->assertCanNotSeeTableRecords([$otra]);

        $this->get(EmpresaResource::getUrl('view', ['record' => $empresa]))->assertOk();

        Livewire::test(ParticipacionesEmpresa::class, ['ownerRecord' => $empresa, 'pageClass' => ViewEmpresa::class])
            ->assertCanSeeTableRecords([$plan])
            ->assertSee('Ane Etxeberria');
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
