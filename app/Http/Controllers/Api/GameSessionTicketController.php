<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\GameSessionTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GameSessionTicketController extends Controller
{
    private const TICKET_TTL_SECONDS = 60;

    public function store(
        Request $request
    ): JsonResponse {
        /** @var Account $account */
        $account = $request->user();


        $data = $request->validate([
            'character_id' => [
                'required',
                'integer',
            ],
        ]);


        // -------------------------------------------------
        // El personaje debe pertenecer a la cuenta
        // autenticada.
        // -------------------------------------------------

        $character = $account
            ->characters()
            ->whereKey(
                $data['character_id']
            )
            ->first();


        if ($character === null) {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró el personaje.',
            ], 404);
        }


        // -------------------------------------------------
        // Sólo dejamos un ticket pendiente para esta
        // cuenta/personaje.
        // -------------------------------------------------

        GameSessionTicket::query()
            ->where(
                'account_id',
                $account->id
            )
            ->where(
                'character_id',
                $character->id
            )
            ->whereNull(
                'consumed_at'
            )
            ->delete();


        // -------------------------------------------------
        // El ticket real se entrega una sola vez.
        // En la base guardamos únicamente su SHA-256.
        // -------------------------------------------------

        $plainTicket = Str::random(
            64
        );


        $expiresAt = now()->addSeconds(
            self::TICKET_TTL_SECONDS
        );


        GameSessionTicket::query()->create([
            'account_id' => $account->id,
            'character_id' => $character->id,
            'token_hash' => hash(
                'sha256',
                $plainTicket
            ),
            'expires_at' => $expiresAt,
            'consumed_at' => null,
        ]);


        return response()->json([
            'ok' => true,

            'data' => [
                'ticket' => $plainTicket,
                'character_id' => $character->id,
                'expires_at' => $expiresAt->toISOString(),
            ],
        ], 201);
    }
}