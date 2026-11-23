<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consolidated registry of people, companies and their participations in DEMA programs,
     * filled by the nightly sync from the program applications.
     */
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('documento', 20)->nullable()->unique()->comment('Normalized DNI/NIE/passport; null when it cannot be matched safely');
            $table->string('documento_original')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido1')->nullable();
            $table->string('apellido2')->nullable();
            $table->string('sexo', 10)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('poblacion')->nullable();
            $table->timestamp('datos_actualizados_at')->nullable()->comment('Update time of the source record the data was taken from');
            $table->timestamps();

            $table->index(['apellido1', 'apellido2', 'nombre']);
            $table->index('telefono');
            $table->index('email');
        });

        Schema::create('persona_origenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();
            $table->string('programa', 20);
            $table->string('origen_tabla', 40);
            $table->unsignedBigInteger('origen_id');
            $table->timestamp('sincronizado_at');

            $table->unique(['programa', 'origen_tabla', 'origen_id']);
        });

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('cif', 20)->nullable()->unique();
            $table->string('nombre')->nullable();
            $table->string('sector')->nullable();
            $table->string('forma_juridica')->nullable();
            $table->string('poblacion')->nullable();
            $table->date('fecha_alta')->nullable();
            $table->string('estado')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('datos_actualizados_at')->nullable();
            $table->timestamps();

            $table->index('nombre');
        });

        Schema::create('empresa_origenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('programa', 20);
            $table->string('origen_tabla', 40);
            $table->unsignedBigInteger('origen_id');
            $table->timestamp('sincronizado_at');

            $table->unique(['programa', 'origen_tabla', 'origen_id']);
        });

        Schema::create('participaciones', function (Blueprint $table) {
            $table->id();
            $table->string('programa', 20);
            $table->string('tipo', 30);
            $table->unsignedBigInteger('origen_id');
            $table->foreignId('empresa_id')->nullable()->constrained()->nullOnDelete();
            $table->string('referencia')->nullable()->comment('Expediente number, course edition...');
            $table->string('titulo')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('estado')->nullable();
            $table->string('responsable')->nullable();
            $table->json('datos')->nullable()->comment('Program-specific fields for statistics');
            $table->timestamp('sincronizado_at');
            $table->timestamps();

            $table->unique(['programa', 'tipo', 'origen_id']);
            $table->index(['programa', 'fecha_inicio']);
        });

        Schema::create('participacion_persona', function (Blueprint $table) {
            $table->foreignId('participacion_id')->constrained('participaciones')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained()->cascadeOnDelete();

            $table->primary(['participacion_id', 'persona_id']);
        });

        Schema::create('sincronizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('programa', 20);
            $table->timestamp('iniciada_at');
            $table->timestamp('finalizada_at')->nullable();
            $table->string('estado', 20);
            $table->json('resumen')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['programa', 'iniciada_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sincronizaciones');
        Schema::dropIfExists('participacion_persona');
        Schema::dropIfExists('participaciones');
        Schema::dropIfExists('empresa_origenes');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('persona_origenes');
        Schema::dropIfExists('personas');
    }
};
