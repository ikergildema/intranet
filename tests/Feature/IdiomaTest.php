<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Filament\Resources\Personas\PersonaResource;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdiomaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chosen_language_is_remembered_by_the_user_and_applied_to_the_interface(): void
    {
        $this->seed(RolesSeeder::class);
        $usuario = User::factory()->create()->assignRole(Rol::Tecnico->value);
        $persona = Persona::factory()->create(['documento' => null]);

        $this->actingAs($usuario)->get(route('idioma', 'eu'))->assertRedirect();

        $this->assertSame('eu', $usuario->fresh()->locale);
        $this->get(PersonaResource::getUrl('view', ['record' => $persona]))
            ->assertOk()
            ->assertSee('Agiri fidagarririk gabe')
            ->assertDontSee('Sin documento fiable');
    }

    public function test_the_login_page_offers_both_languages_and_unknown_ones_are_rejected(): void
    {
        $this->get('/login')->assertOk()->assertSee('Castellano')->assertSee('Euskara');
        $this->get(route('idioma', 'fr'))->assertNotFound();
    }
}
