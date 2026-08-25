<?php

namespace App\Http\Controllers\Api;

use App\Application\Progression\CharacterProgressionPersistence;
use App\Application\Progression\CharacterProgressionPersistenceException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalCharacterProgressionController extends Controller
{
    public function update(
        Request $request,
        int $accountId,
        int $characterId,
        CharacterProgressionPersistence $persistence
    ): JsonResponse {
        $validated = $request->validate([
            'expected' => [
                'required',
                'array',
            ],

            'expected.level' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],

            'expected.experience' => [
                'required',
                'integer',
                'min:0',
            ],

            'next' => [
                'required',
                'array',
            ],

            'next.level' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],

            'next.experience' => [
                'required',
                'integer',
                'min:0',
            ],
        ]);


        $expectedLevel = (int) $validated[
            'expected'
        ]['level'];

        $expectedExperience = (int) $validated[
            'expected'
        ]['experience'];

        $nextLevel = (int) $validated[
            'next'
        ]['level'];

        $nextExperience = (int) $validated[
            'next'
        ]['experience'];


        // ---------------------------------------------
        // PROGRESIÓN MONÓTONA
        //
        // Laravel no calcula la curva de EXP, pero sí
        // evita una regresión estructural obvia.
        // ---------------------------------------------

        if ($nextLevel < $expectedLevel) {
            return response()->json([
                'ok' => false,

                'message' => (
                    'El nivel siguiente no puede ser menor '
                    .'que el nivel esperado.'
                ),
            ], 422);
        }


        if (
            $nextLevel === $expectedLevel
            &&
            $nextExperience < $expectedExperience
        ) {
            return response()->json([
                'ok' => false,

                'message' => (
                    'La experiencia no puede retroceder '
                    .'sin un cambio de nivel.'
                ),
            ], 422);
        }


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
            $result = $persistence->persistState(
                $account,
                $character,
                $expectedLevel,
                $expectedExperience,
                $nextLevel,
                $nextExperience
            );
        } catch (
            CharacterProgressionPersistenceException $exception
        ) {
            return response()->json([
                'ok' => false,

                'message' => $exception->getMessage(),

                'data' => $exception->context(),
            ], 409);
        }


        /** @var Character $persistedCharacter */
        $persistedCharacter = $result[
            'character'
        ];


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $account->id,

                'character_id' => $persistedCharacter->id,

                'idempotent' => (bool) $result[
                    'idempotent'
                ],

                'progression' => [
                    'level' => (
                        (int) $persistedCharacter->level
                    ),

                    'experience' => (
                        (int) $persistedCharacter->experience
                    ),
                ],
            ],
        ]);
    }
}