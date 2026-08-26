<?php

namespace App\Http\Controllers\Api;

use App\Application\Skills\CharacterSkillLearningPersistence;
use App\Application\Skills\SkillLearningPersistenceException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterSkill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class InternalCharacterSkillLearningController extends Controller
{
    public function store(
        Request $request,
        int $accountId,
        int $characterId,
        CharacterSkillLearningPersistence $persistence
    ): JsonResponse {
        $validated = $request->validate([
            'skill_id' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
            ],

            'scroll_uid' => [
                'required',
                'uuid',
            ],

            'scroll_item_id' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
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
            $result = $persistence->persistLearning(
                $account,
                $character,
                (string) $validated['skill_id'],
                (string) $validated['scroll_uid'],
                (string) $validated['scroll_item_id']
            );
        } catch (SkillLearningPersistenceException $exception) {
            $status = match ($exception->reason()) {
                'scroll_not_found' => 404,

                default => 409,
            };


            return response()->json([
                'ok' => false,

                'message' => $exception->getMessage(),

                'data' => array_merge(
                    [
                        'reason' => (
                            $exception->reason()
                        ),
                    ],
                    $exception->context()
                ),
            ], $status);
        }


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

                    'learned_from_item_uid' => (
                        $characterSkill->learned_from_item_uid
                    ),

                    'learned_from_item_id' => (
                        $characterSkill->learned_from_item_id
                    ),
                ],
            ],
        ], $created ? 201 : 200);
    }
}