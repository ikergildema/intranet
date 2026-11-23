<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the person counted as migrant in that participation, by the program's own rule
     * (null when the program does not record it).
     */
    public function up(): void
    {
        Schema::table('participacion_persona', function (Blueprint $table) {
            $table->boolean('migrante')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('participacion_persona', function (Blueprint $table) {
            $table->dropColumn('migrante');
        });
    }
};
