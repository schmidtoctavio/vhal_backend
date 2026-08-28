<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'character_stat_allocations',
            function (Blueprint $table): void {
                $table->foreignId(
                    'character_id'
                )->constrained(
                    'characters'
                )->cascadeOnDelete();


                $table->primary(
                    'character_id'
                );


                $table->unsignedInteger(
                    'allocated_strength'
                )->default(
                    0
                );

                $table->unsignedInteger(
                    'allocated_agility'
                )->default(
                    0
                );

                $table->unsignedInteger(
                    'allocated_vitality'
                )->default(
                    0
                );

                $table->unsignedInteger(
                    'allocated_energy'
                )->default(
                    0
                );


                $table->unsignedInteger(
                    'bonus_stat_points'
                )->default(
                    0
                );


                $table->unsignedBigInteger(
                    'revision'
                )->default(
                    1
                );


                $table->timestamps();
            }
        );
    }


    public function down(): void
    {
        Schema::dropIfExists(
            'character_stat_allocations'
        );
    }
};