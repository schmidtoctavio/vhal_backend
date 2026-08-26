<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'character_skills',
            function (Blueprint $table): void {
                $table->uuid(
                    'learned_from_item_uid'
                )
                    ->nullable()
                    ->after('skill_id');


                $table->string(
                    'learned_from_item_id',
                    64
                )
                    ->nullable()
                    ->after('learned_from_item_uid');


                $table->unique(
                    'learned_from_item_uid'
                );
            }
        );
    }


    public function down(): void
    {
        Schema::table(
            'character_skills',
            function (Blueprint $table): void {
                $table->dropUnique([
                    'learned_from_item_uid',
                ]);


                $table->dropColumn([
                    'learned_from_item_uid',
                    'learned_from_item_id',
                ]);
            }
        );
    }
};