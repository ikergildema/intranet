<?php

namespace Database\Seeders;

use App\Enums\Rol;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    /**
     * Create the intranet roles; safe to run more than once.
     */
    public function run(): void
    {
        foreach (Rol::cases() as $rol) {
            Role::findOrCreate($rol->value, 'web');
        }
    }
}
