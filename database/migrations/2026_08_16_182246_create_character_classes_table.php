<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_classes', function (Blueprint $table) {
            $table->string('id', 50)
                ->primary();

            $table->string('display_name', 50);

            $table->boolean('is_enabled')
                ->default(true);

            $table->unsignedTinyInteger('sort_order')
                ->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_classes');
    }
};