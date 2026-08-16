<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                ->constrained('accounts')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('slot_index');

            $table->string('name', 32)
                ->unique();

            $table->string('class_id', 50);

            $table->unsignedSmallInteger('level')
                ->default(1);

            $table->timestamps();

            $table->unique([
                'account_id',
                'slot_index',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};