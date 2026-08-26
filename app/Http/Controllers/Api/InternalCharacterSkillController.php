<?php

namespace App\Http\Controllers\Api;

use App\Application\Skills\CharacterSkillOwnershipPersistence;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterSkill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class InternalCharacterSkillController extends Controller
{
    public function store(
        Request $request,
        int $accountId,
        int $characterId,
        CharacterSkillOwnershipPersistence $persistence
    ): JsonResponse {
        $validated = $request->validate([
            'skill_id' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
            ],
        ]);


        // -------------------------------------------------
        // CUENTA
        // -------------------------------------------------

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

                'message' => (
                    'La cuenta no está habilitada.'
                ),
            ], 403);
        }


        // -------------------------------------------------
        // PERSONAJE
        // -------------------------------------------------

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


        // -------------------------------------------------
        // PERSISTENCIA
        // -------------------------------------------------

        $result = $persistence->persistLearnedSkill(
            $character,
            (string) $validated['skill_id']
        );


        /** @var CharacterSkill $characterSkill */
        $characterSkill = (
            $result['character_skill']
        );


        $created = (bool) $result[
            'created'
        ];


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $account->id,

                'character_id' => $character->id,

                'idempotent' => (
                    ! $created
                ),

                'skill' => [
                    'skill_id' => (
                        $characterSkill->skill_id
                    ),
                ],
            ],
        ], $created ? 201 : 200);
    }
}