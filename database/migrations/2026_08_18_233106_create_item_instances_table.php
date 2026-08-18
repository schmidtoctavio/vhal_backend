<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_instances', function (Blueprint $table) {
            $table->id();

            // La cuenta es siempre el propietario último del item.
            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete();

            // NULL cuando el item está en un contenedor de cuenta
            // como la Vault.
            $table->foreignId('character_id')
                ->nullable()
                ->constrained('characters')
                ->cascadeOnDelete();

            // Identidad persistente del objeto individual.
            $table->uuid('uid')
                ->unique();

            // ID lógico estable de ItemDefinition.
            // Ej: health_potion, leather_boots, short_sword.
            $table->string('item_id', 64);

            // inventory | equipment | vault
            $table->string('container', 32);

            $table->unsignedInteger('quantity')
                ->default(1);

            // Posición superior izquierda para contenedores
            // basados en grilla.
            $table->unsignedSmallInteger('grid_x')
                ->nullable();

            $table->unsignedSmallInteger('grid_y')
                ->nullable();

            // Sólo se utiliza cuando container = equipment.
            // Ej: head, chest, weapon...
            $table->string('equipment_slot', 32)
                ->nullable();

            // Reserva para estado propio de una instancia:
            // durabilidad, opciones, mejoras, etc.
            $table->json('state')
                ->nullable();

            $table->timestamps();

            $table->index([
                'account_id',
                'container',
            ]);

            $table->index([
                'character_id',
                'container',
            ]);

            $table->index('item_id');

            $table->unique([
                'character_id',
                'container',
                'equipment_slot',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_instances');
    }
};