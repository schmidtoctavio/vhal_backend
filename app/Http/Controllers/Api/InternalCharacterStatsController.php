<?php

namespace App\Http\Controllers\Api;

use App\Application\Stats\CharacterStatSnapshotBuilder;
use App\Application\Stats\CharacterStatSnapshotException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use Illuminate\Http\JsonResponse;


class InternalCharacterStatsController extends Controller
{
    public function show(
        int $accountId,
        int $characterId,
        CharacterStatSnapshotBuilder $snapshotBuilder
    ): JsonResponse {
        $account = Account::query()
            ->whereKey(
                $accountId
            )
            ->first();


        if ($account === null) {
            return response()->json([
                'ok' => false,

                'message' => 'Cuenta no encontrada.',
            ], 404);
        }


        if ($account->status !== 'active') {
            return response()->json([
                'ok' => false,

                'message' => 'La cuenta no está habilitada.',
            ], 403);
        }


        $character = Character::query()
            ->with(
                'statAllocation'
            )
            ->whereKey(
                $characterId
            )
            ->where(
                'account_id',
                $account->id
            )
            ->first();


        if ($character === null) {
            return response()->json([
                'ok' => false,

                'message' => 'Personaje no encontrado.',
            ], 404);
        }


        try {
            $snapshot = $snapshotBuilder->build(
                $character
            );
        } catch (
            CharacterStatSnapshotException $exception
        ) {
            return response()->json([
                'ok' => false,

                'message' => $exception->getMessage(),

                'data' => $exception->context(),
            ], 409);
        }


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $account->id,

                'character_id' => $character->id,

                'stats' => $snapshot,
            ],
        ]);
    }
}