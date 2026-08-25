<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'character_runtime_states',
            function (Blueprint $table): void {
                $table->foreignId(
                    'character_id'
                )->constrained(
                    'characters'
                )->cascadeOnDelete();


                $table->primary(
                    'character_id'
                );


                $table->string(
                    'map_id',
                    100
                );


                $table->double(
                    'position_x'
                );

                $table->double(
                    'position_y'
                );

                $table->double(
                    'position_z'
                );

                $table->double(
                    'rotation_y'
                );


                $table->unsignedBigInteger(
                    'hp'
                );

                $table->unsignedBigInteger(
                    'mp'
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
            'character_runtime_states'
        );
    }
};