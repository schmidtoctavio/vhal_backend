<?php

namespace App\Application\Skills;

use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterSkill;
use App\Models\ItemInstance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;


final class CharacterSkillLearningPersistence
{
    public function persistLearning(
        Account $account,
        Character $character,
        string $skillId,
        string $scrollUid,
        string $scrollItemId
    ): array {
        $normalizedSkillId = strtolower(
            trim(
                $skillId
            )
        );


        $normalizedScrollUid = strtolower(
            trim(
                $scrollUid
            )
        );


        $normalizedScrollItemId = strtolower(
            trim(
                $scrollItemId
            )
        );


        try {
            return DB::transaction(
                function () use (
                    $account,
                    $character,
                    $normalizedSkillId,
                    $normalizedScrollUid,
                    $normalizedScrollItemId
                ): array {
                    // -------------------------------------
                    // SERIALIZAR MUTACIONES DEL CHARACTER
                    // -------------------------------------

                    Character::query()
                        ->whereKey(
                            $character->id
                        )
                        ->where(
                            'account_id',
                            $account->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                    // -------------------------------------
                    // SKILL YA APRENDIDA
                    // -------------------------------------

                    $existingSkill = CharacterSkill::query()
                        ->where(
                            'character_id',
                            $character->id
                        )
                        ->where(
                            'skill_id',
                            $normalizedSkillId
                        )
                        ->lockForUpdate()
                        ->first();


                    if ($existingSkill !== null) {
                        return $this->resolveExistingLearning(
                            $existingSkill,
                            $normalizedScrollUid,
                            $normalizedScrollItemId
                        );
                    }


                    // -------------------------------------
                    // UN MISMO SCROLL NO PUEDE ENSEÑAR
                    // DOS SKILLS.
                    // -------------------------------------

                    $existingScrollLearning = CharacterSkill::query()
                        ->where(
                            'learned_from_item_uid',
                            $normalizedScrollUid
                        )
                        ->lockForUpdate()
                        ->first();


                    if ($existingScrollLearning !== null) {
                        throw SkillLearningPersistenceException::scrollAlreadyUsed(
                            $normalizedScrollUid
                        );
                    }


                    // -------------------------------------
                    // SCROLL DURABLE REAL
                    // -------------------------------------

                    $scroll = ItemInstance::query()
                        ->where(
                            'account_id',
                            $account->id
                        )
                        ->where(
                            'character_id',
                            $character->id
                        )
                        ->where(
                            'container',
                            'inventory'
                        )
                        ->where(
                            'uid',
                            $normalizedScrollUid
                        )
                        ->lockForUpdate()
                        ->first();


                    if ($scroll === null) {
                        throw SkillLearningPersistenceException::scrollNotFound(
                            $normalizedScrollUid
                        );
                    }


                    $actualItemId = strtolower(
                        trim(
                            (string) $scroll->item_id
                        )
                    );


                    if (
                        $actualItemId
                        !==
                        $normalizedScrollItemId
                    ) {
                        throw SkillLearningPersistenceException::scrollItemMismatch(
                            $normalizedScrollUid,
                            $normalizedScrollItemId,
                            $actualItemId
                        );
                    }


                    if ($scroll->quantity <= 0) {
                        throw SkillLearningPersistenceException::invalidScrollQuantity(
                            $normalizedScrollUid
                        );
                    }


                    // -------------------------------------
                    // CREAR OWNERSHIP
                    // -------------------------------------

                    $characterSkill = CharacterSkill::query()
                        ->create([
                            'character_id' => (
                                $character->id
                            ),

                            'skill_id' => (
                                $normalizedSkillId
                            ),

                            'learned_from_item_uid' => (
                                $normalizedScrollUid
                            ),

                            'learned_from_item_id' => (
                                $normalizedScrollItemId
                            ),
                        ]);


                    // -------------------------------------
                    // CONSUMIR EXACTAMENTE UNA UNIDAD
                    // -------------------------------------

                    if ($scroll->quantity === 1) {
                        $scroll->delete();
                    } else {
                        $scroll->quantity -= 1;

                        $scroll->save();
                    }


                    return [
                        'character_skill' => (
                            $characterSkill->refresh()
                        ),

                        'created' => true,
                    ];
                }
            );
        } catch (QueryException $exception) {
            // ---------------------------------------------
            // DEFENSA FINAL CONTRA RACES DE UNIQUE
            // ---------------------------------------------

            if (
                (string) $exception->getCode()
                !==
                '23000'
            ) {
                throw $exception;
            }


            $existingSkill = CharacterSkill::query()
                ->where(
                    'character_id',
                    $character->id
                )
                ->where(
                    'skill_id',
                    $normalizedSkillId
                )
                ->first();


            if ($existingSkill !== null) {
                return $this->resolveExistingLearning(
                    $existingSkill,
                    $normalizedScrollUid,
                    $normalizedScrollItemId
                );
            }


            $existingScrollLearning = CharacterSkill::query()
                ->where(
                    'learned_from_item_uid',
                    $normalizedScrollUid
                )
                ->first();


            if ($existingScrollLearning !== null) {
                throw SkillLearningPersistenceException::scrollAlreadyUsed(
                    $normalizedScrollUid
                );
            }


            throw $exception;
        }
    }


    private function resolveExistingLearning(
        CharacterSkill $existingSkill,
        string $scrollUid,
        string $scrollItemId
    ): array {
        $existingSourceUid = strtolower(
            trim(
                (string) (
                    $existingSkill->learned_from_item_uid
                    ?? ''
                )
            )
        );


        $existingSourceItemId = strtolower(
            trim(
                (string) (
                    $existingSkill->learned_from_item_id
                    ?? ''
                )
            )
        );


        // ---------------------------------------------
        // MISMA OPERACIÓN YA CONFIRMADA.
        //
        // Esto cubre:
        // Backend COMMIT
        // → respuesta se pierde
        // → GS hace retry
        // ---------------------------------------------

        if (
            $existingSourceUid === $scrollUid
            &&
            $existingSourceItemId === $scrollItemId
        ) {
            return [
                'character_skill' => (
                    $existingSkill
                ),

                'created' => false,
            ];
        }


        throw SkillLearningPersistenceException::alreadyLearned(
            (string) $existingSkill->skill_id
        );
    }
}