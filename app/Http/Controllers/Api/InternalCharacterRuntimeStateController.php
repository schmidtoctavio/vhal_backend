<?php

namespace App\Http\Controllers\Api;

use App\Application\Runtime\CharacterRuntimeStatePersistence;
use App\Application\Runtime\CharacterRuntimeStatePersistenceException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class InternalCharacterRuntimeStateController extends Controller
{
    public function update(
        Request $request,
        int $accountId,
        int $characterId,
        CharacterRuntimeStatePersistence $persistence
    ): JsonResponse {
        $validated = $request->validate([
            'expected_revision' => [
                'required',
                'integer',
                'min:0',
            ],


            'state' => [
                'required',
                'array',
            ],


            'state.world' => [
                'required',
                'array',
            ],

            'state.world.map_id' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
            ],


            'state.world.position' => [
                'required',
                'array',
            ],

            'state.world.position.x' => [
                'required',
                'numeric',
                'between:-1000000,1000000',
            ],

            'state.world.position.y' => [
                'required',
                'numeric',
                'between:-1000000,1000000',
            ],

            'state.world.position.z' => [
                'required',
                'numeric',
                'between:-1000000,1000000',
            ],


            'state.world.rotation_y' => [
                'required',
                'numeric',
                'between:-1000,1000',
            ],


            'state.vitals' => [
                'required',
                'array',
            ],

            'state.vitals.hp' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'state.vitals.mp' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
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


        $state = [
            'world' => [
                'map_id' => (
                    strtolower(
                        trim(
                            $validated[
                                'state'
                            ]['world']['map_id']
                        )
                    )
                ),

                'position' => [
                    'x' => (
                        (float) $validated[
                            'state'
                        ]['world']['position']['x']
                    ),

                    'y' => (
                        (float) $validated[
                            'state'
                        ]['world']['position']['y']
                    ),

                    'z' => (
                        (float) $validated[
                            'state'
                        ]['world']['position']['z']
                    ),
                ],

                'rotation_y' => (
                    (float) $validated[
                        'state'
                    ]['world']['rotation_y']
                ),
            ],

            'vitals' => [
                'hp' => (
                    (int) $validated[
                        'state'
                    ]['vitals']['hp']
                ),

                'mp' => (
                    (int) $validated[
                        'state'
                    ]['vitals']['mp']
                ),
            ],
        ];


        try {
            $result = (
                $persistence->persistState(
                    $account,
                    $character,
                    (int) $validated[
                        'expected_revision'
                    ],
                    $state
                )
            );
        } catch (
            CharacterRuntimeStatePersistenceException $exception
        ) {
            return response()->json([
                'ok' => false,

                'message' => (
                    $exception->getMessage()
                ),

                'data' => (
                    $exception->context()
                ),
            ], 409);
        }


        $runtimeState = $result[
            'runtime_state'
        ];


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $account->id,

                'character_id' => $character->id,

                'idempotent' => (
                    (bool) $result[
                        'idempotent'
                    ]
                ),

                'runtime' => (
                    $persistence->toRuntimeSnapshot(
                        $runtimeState
                    )
                ),
            ],
        ]);
    }
}