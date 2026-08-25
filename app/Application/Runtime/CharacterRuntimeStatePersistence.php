<?php

namespace App\Application\Runtime;

use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterRuntimeState;
use Illuminate\Support\Facades\DB;
use RuntimeException;


final class CharacterRuntimeStatePersistence
{
    private const FLOAT_EPSILON = 0.000001;


    public function persistState(
        Account $account,
        Character $character,
        int $expectedRevision,
        array $nextState
    ): array {
        return DB::transaction(
            function () use (
                $account,
                $character,
                $expectedRevision,
                $nextState
            ): array {
                // -----------------------------------------
                // Lock del Character.
                //
                // Además de verificar ownership, esto
                // serializa incluso el caso donde todavía
                // NO existe una runtime row.
                // -----------------------------------------

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


                $runtimeState = CharacterRuntimeState::query()
                    ->where(
                        'character_id',
                        $lockedCharacter->id
                    )
                    ->lockForUpdate()
                    ->first();


                // =========================================
                // PRIMER CHECKPOINT
                // =========================================

                if ($runtimeState === null) {
                    if ($expectedRevision !== 0) {
                        throw (
                            CharacterRuntimeStatePersistenceException
                                ::staleRevision(
                                    $expectedRevision,
                                    null
                                )
                        );
                    }


                    $runtimeState = (
                        CharacterRuntimeState::query()
                        ->create([
                            'character_id' => (
                                $lockedCharacter->id
                            ),

                            'map_id' => (
                                $nextState[
                                    'world'
                                ]['map_id']
                            ),

                            'position_x' => (
                                $nextState[
                                    'world'
                                ]['position']['x']
                            ),

                            'position_y' => (
                                $nextState[
                                    'world'
                                ]['position']['y']
                            ),

                            'position_z' => (
                                $nextState[
                                    'world'
                                ]['position']['z']
                            ),

                            'rotation_y' => (
                                $nextState[
                                    'world'
                                ]['rotation_y']
                            ),

                            'hp' => (
                                $nextState[
                                    'vitals'
                                ]['hp']
                            ),

                            'mp' => (
                                $nextState[
                                    'vitals'
                                ]['mp']
                            ),

                            'revision' => 1,
                        ])
                    );


                    return [
                        'runtime_state' => (
                            $runtimeState->refresh()
                        ),

                        'idempotent' => false,
                    ];
                }


                $currentRevision = (
                    (int) $runtimeState->revision
                );


                // =========================================
                // IDEMPOTENCIA / RETRY
                // =========================================
                //
                // Dos casos válidos:
                //
                // expected == current
                //   → el estado solicitado ya es el actual.
                //
                // expected + 1 == current
                //   → probablemente Laravel aplicó el
                //     request pero la respuesta se perdió.
                // =========================================

                if (
                    $this->matchesState(
                        $runtimeState,
                        $nextState
                    )
                    &&
                    (
                        $currentRevision
                        ===
                        $expectedRevision

                        ||

                        $currentRevision
                        ===
                        ($expectedRevision + 1)
                    )
                ) {
                    return [
                        'runtime_state' => (
                            $runtimeState
                        ),

                        'idempotent' => true,
                    ];
                }


                // =========================================
                // STALE
                // =========================================

                if (
                    $currentRevision
                    !==
                    $expectedRevision
                ) {
                    throw (
                        CharacterRuntimeStatePersistenceException
                            ::staleRevision(
                                $expectedRevision,
                                $this->toRuntimeSnapshot(
                                    $runtimeState
                                )
                            )
                    );
                }


                // =========================================
                // NUEVA REVISION
                // =========================================

                $runtimeState->forceFill([
                    'map_id' => (
                        $nextState[
                            'world'
                        ]['map_id']
                    ),

                    'position_x' => (
                        $nextState[
                            'world'
                        ]['position']['x']
                    ),

                    'position_y' => (
                        $nextState[
                            'world'
                        ]['position']['y']
                    ),

                    'position_z' => (
                        $nextState[
                            'world'
                        ]['position']['z']
                    ),

                    'rotation_y' => (
                        $nextState[
                            'world'
                        ]['rotation_y']
                    ),

                    'hp' => (
                        $nextState[
                            'vitals'
                        ]['hp']
                    ),

                    'mp' => (
                        $nextState[
                            'vitals'
                        ]['mp']
                    ),

                    'revision' => (
                        $currentRevision
                        +
                        1
                    ),
                ])->save();


                return [
                    'runtime_state' => (
                        $runtimeState->refresh()
                    ),

                    'idempotent' => false,
                ];
            }
        );
    }


    // =====================================================
    // COMPARAR
    // =====================================================

    private function matchesState(
        CharacterRuntimeState $current,
        array $next
    ): bool {
        if (
            $current->map_id
            !==
            $next['world']['map_id']
        ) {
            return false;
        }


        if (
            ! $this->sameFloat(
                (float) $current->position_x,
                (float) $next[
                    'world'
                ]['position']['x']
            )
        ) {
            return false;
        }


        if (
            ! $this->sameFloat(
                (float) $current->position_y,
                (float) $next[
                    'world'
                ]['position']['y']
            )
        ) {
            return false;
        }


        if (
            ! $this->sameFloat(
                (float) $current->position_z,
                (float) $next[
                    'world'
                ]['position']['z']
            )
        ) {
            return false;
        }


        if (
            ! $this->sameFloat(
                (float) $current->rotation_y,
                (float) $next[
                    'world'
                ]['rotation_y']
            )
        ) {
            return false;
        }


        if (
            (int) $current->hp
            !==
            (int) $next['vitals']['hp']
        ) {
            return false;
        }


        if (
            (int) $current->mp
            !==
            (int) $next['vitals']['mp']
        ) {
            return false;
        }


        return true;
    }


    private function sameFloat(
        float $left,
        float $right
    ): bool {
        return (
            abs(
                $left
                -
                $right
            )
            <=
            self::FLOAT_EPSILON
        );
    }


    // =====================================================
    // SNAPSHOT
    // =====================================================

    public function toRuntimeSnapshot(
        CharacterRuntimeState $state
    ): array {
        return [
            'revision' => (
                (int) $state->revision
            ),

            'world' => [
                'map_id' => $state->map_id,

                'position' => [
                    'x' => (
                        (float) $state->position_x
                    ),

                    'y' => (
                        (float) $state->position_y
                    ),

                    'z' => (
                        (float) $state->position_z
                    ),
                ],

                'rotation_y' => (
                    (float) $state->rotation_y
                ),
            ],

            'vitals' => [
                'hp' => (
                    (int) $state->hp
                ),

                'mp' => (
                    (int) $state->mp
                ),
            ],
        ];
    }
}