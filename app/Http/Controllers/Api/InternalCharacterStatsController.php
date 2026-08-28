<?php

namespace App\Http\Controllers\Api;

use App\Application\Stats\CharacterStatAllocationPersistence;
use App\Application\Stats\CharacterStatAllocationPersistenceException;
use App\Application\Stats\CharacterStatSnapshotBuilder;
use App\Application\Stats\CharacterStatSnapshotException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class InternalCharacterStatsController extends Controller
{
    // =====================================================
    // READ SNAPSHOT
    // =====================================================

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


    // =====================================================
    // ALLOCATE
    // =====================================================

    public function update(
        Request $request,
        int $accountId,
        int $characterId,
        CharacterStatAllocationPersistence $persistence
    ): JsonResponse {
        $validated = $request->validate([
            'expected_revision' => [
                'required',
                'integer',
                'min:0',
            ],


            'next' => [
                'required',
                'array',
            ],

            'next.strength' => [
                'required',
                'integer',
                'min:0',
            ],

            'next.agility' => [
                'required',
                'integer',
                'min:0',
            ],

            'next.vitality' => [
                'required',
                'integer',
                'min:0',
            ],

            'next.energy' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);


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
            $result = $persistence->persistAllocation(
                $account,
                $character,
                (int) $validated[
                    'expected_revision'
                ],
                [
                    'strength' => (
                        (int) $validated[
                            'next'
                        ]['strength']
                    ),

                    'agility' => (
                        (int) $validated[
                            'next'
                        ]['agility']
                    ),

                    'vitality' => (
                        (int) $validated[
                            'next'
                        ]['vitality']
                    ),

                    'energy' => (
                        (int) $validated[
                            'next'
                        ]['energy']
                    ),
                ]
            );
        } catch (
            CharacterStatAllocationPersistenceException
                $exception
        ) {
            return response()->json([
                'ok' => false,

                'message' => $exception->getMessage(),

                'data' => $exception->context(),
            ], 409);
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

                'idempotent' => (
                    (bool) $result['idempotent']
                ),

                'stats' => (
                    $result['snapshot']
                ),
            ],
        ]);
    }
}