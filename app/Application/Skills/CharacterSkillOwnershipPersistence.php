<?php

namespace App\Application\Skills;

use App\Models\Character;
use App\Models\CharacterSkill;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;


final class CharacterSkillOwnershipPersistence
{
    public function persistLearnedSkill(
        Character $character,
        string $skillId
    ): array {
        $normalizedSkillId = strtolower(
            trim(
                $skillId
            )
        );


        try {
            return DB::transaction(
                function () use (
                    $character,
                    $normalizedSkillId
                ): array {
                    // -------------------------------------
                    // Serializamos mutaciones de ownership
                    // para el mismo personaje.
                    // -------------------------------------

                    Character::query()
                        ->whereKey(
                            $character->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


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
                        return [
                            'character_skill' => (
                                $existingSkill
                            ),

                            'created' => false,
                        ];
                    }


                    $characterSkill = CharacterSkill::query()
                        ->create([
                            'character_id' => (
                                $character->id
                            ),

                            'skill_id' => (
                                $normalizedSkillId
                            ),
                        ]);


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
            // Defensa adicional para dos requests
            // concurrentes que intenten aprender la misma
            // skill.
            //
            // character_skills posee:
            // UNIQUE(character_id, skill_id)
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


            if ($existingSkill === null) {
                throw $exception;
            }


            return [
                'character_skill' => (
                    $existingSkill
                ),

                'created' => false,
            ];
        }
    }
}