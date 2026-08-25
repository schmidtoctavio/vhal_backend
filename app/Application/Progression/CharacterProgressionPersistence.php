<?php

namespace App\Application\Progression;

use App\Models\Account;
use App\Models\Character;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CharacterProgressionPersistence
{
    public function persistState(
        Account $account,
        Character $character,
        int $expectedLevel,
        int $expectedExperience,
        int $nextLevel,
        int $nextExperience
    ): array {
        return DB::transaction(
            function () use (
                $account,
                $character,
                $expectedLevel,
                $expectedExperience,
                $nextLevel,
                $nextExperience
            ): array {
                $lockedCharacter = Character::query()
                    ->whereKey(
                        $character->id
                    )
                    ->where(
                        'account_id',
                        $account->id
                    )
                    ->lockForUpdate()
                    ->first();


                if ($lockedCharacter === null) {
                    throw new RuntimeException(
                        'El personaje dejó de estar disponible.'
                    );
                }


                $currentLevel = (int) $lockedCharacter->level;

                $currentExperience = (int) $lockedCharacter->experience;


                // -----------------------------------------
                // IDEMPOTENCIA
                //
                // Si este request ya fue aplicado y se
                // reintenta exactamente el mismo estado,
                // respondemos OK sin mutar nuevamente.
                // -----------------------------------------

                if (
                    $currentLevel === $nextLevel
                    &&
                    $currentExperience === $nextExperience
                ) {
                    return [
                        'character' => $lockedCharacter,

                        'idempotent' => true,
                    ];
                }


                // -----------------------------------------
                // STALE STATE
                // -----------------------------------------

                if (
                    $currentLevel !== $expectedLevel
                    ||
                    $currentExperience !== $expectedExperience
                ) {
                    throw CharacterProgressionPersistenceException::staleState(
                        $expectedLevel,
                        $expectedExperience,
                        $currentLevel,
                        $currentExperience
                    );
                }


                $lockedCharacter->forceFill([
                    'level' => $nextLevel,

                    'experience' => $nextExperience,
                ])->save();


                return [
                    'character' => $lockedCharacter->refresh(),

                    'idempotent' => false,
                ];
            }
        );
    }
}