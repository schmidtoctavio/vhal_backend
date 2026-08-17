<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'game_session_tickets',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'account_id'
                )->constrained(
                    'accounts'
                )->cascadeOnDelete();

                $table->foreignId(
                    'character_id'
                )->constrained(
                    'characters'
                )->cascadeOnDelete();

                $table->char(
                    'token_hash',
                    64
                )->unique();

                $table->timestamp(
                    'expires_at'
                )->index();

                $table->timestamp(
                    'consumed_at'
                )->nullable()->index();

                $table->timestamps();

                $table->index([
                    'account_id',
                    'character_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'game_session_tickets'
        );
    }
};