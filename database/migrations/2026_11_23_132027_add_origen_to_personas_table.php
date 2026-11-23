<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Country of origin and its world area, as recorded by the program.
     */
    public function up(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->string('pais_origen')->nullable()->after('poblacion');
            $table->string('area_origen')->nullable()->after('pais_origen');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropColumn(['pais_origen', 'area_origen']);
        });
    }
};
