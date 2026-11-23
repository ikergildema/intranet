<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_every_intranet_role_can_open_the_panel(): void
    {
        foreach (Rol::cases() as $rol) {
            $user = User::factory()->create()->assignRole($rol->value);

            $this->actingAs($user)->get('/')->assertOk();
        }
    }

    public function test_a_user_without_a_role_cannot_open_the_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertForbidden();
    }
}
