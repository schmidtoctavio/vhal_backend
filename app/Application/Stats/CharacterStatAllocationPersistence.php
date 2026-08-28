<?php

namespace App\Application\Stats;

use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterStatAllocation;
use Illuminate\Support\Facades\DB;
use RuntimeException;


final class CharacterStatAllocationPersistence
{
    public function __construct(
        private readonly CharacterStatSnapshotBuilder $snapshotBuilder
    ) {
    }


    // =====================================================
    // PERSISTIR ASIGNACIÓN
    // =====================================================

    public function persistAllocation(
        Account $account,
        Character $character,
        int $expectedRevision,
        array $nextAllocated
    ): array {
        return DB::transaction(
            function () use (
                $account,
                $character,
                $expectedRevision,
                $nextAllocated
            ): array {
                // =========================================
                // NORMALIZAR INPUT
                // =========================================

                $next = [
                    'strength' => (
                        (int) $nextAllocated['strength']
                    ),

                    'agility' => (
                        (int) $nextAllocated['agility']
                    ),

                    'vitality' => (
                        (int) $nextAllocated['vitality']
                    ),

                    'energy' => (
                        (int) $nextAllocated['energy']
                    ),
                ];


                // =========================================
                // LOCK DEL CHARACTER
                // =========================================
                //
                // Esto serializa:
                //
                // - primera allocation sin fila
                // - allocations concurrentes
                // - progression que también lockea Character
                // - futuro Reset
                //
                // =========================================

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


                // =========================================
                // LOCK DE ALLOCATION SI EXISTE
                // =========================================

                $allocation = CharacterStatAllocation::query()
                    ->where(
                        'character_id',
                        $lockedCharacter->id
                    )
                    ->lockForUpdate()
                    ->first();


                // Evitamos que SnapshotBuilder vuelva a
                // resolver la relación por otra query.

                $lockedCharacter->setRelation(
                    'statAllocation',
                    $allocation
                );


                // =========================================
                // SNAPSHOT ACTUAL
                // =========================================

                $currentSnapshot = (
                    $this->snapshotBuilder->build(
                        $lockedCharacter
                    )
                );


                $currentRevision = (
                    (int) $currentSnapshot['revision']
                );


                $currentAllocated = [
                    'strength' => (
                        (int) $currentSnapshot[
                            'allocated'
                        ]['strength']
                    ),

                    'agility' => (
                        (int) $currentSnapshot[
                            'allocated'
                        ]['agility']
                    ),

                    'vitality' => (
                        (int) $currentSnapshot[
                            'allocated'
                        ]['vitality']
                    ),

                    'energy' => (
                        (int) $currentSnapshot[
                            'allocated'
                        ]['energy']
                    ),
                ];


                // =========================================
                // IDEMPOTENCIA
                // =========================================
                //
                // Caso A:
                //
                // expected == current
                // y next ya coincide.
                //
                // Caso B:
                //
                // current == expected + 1
                // y next coincide.
                //
                // Esto cubre la respuesta perdida después
                // de aplicar correctamente el request.
                // =========================================

                if (
                    $this->sameAllocation(
                        $currentAllocated,
                        $next
                    )
                    &&
                    (
                        $currentRevision === (
                            $expectedRevision
                        )
                        ||
                        $currentRevision === (
                            $expectedRevision + 1
                        )
                    )
                ) {
                    return [
                        'stat_allocation' => $allocation,

                        'snapshot' => $currentSnapshot,

                        'idempotent' => true,
                    ];
                }


                // =========================================
                // STALE REVISION
                // =========================================

                if (
                    $currentRevision
                    !==
                    $expectedRevision
                ) {
                    throw (
                        CharacterStatAllocationPersistenceException
                            ::staleRevision(
                                $expectedRevision,
                                $currentSnapshot
                            )
                    );
                }


                // =========================================
                // ALLOCATION MONÓTONA
                // =========================================
                //
                // Este pipeline sólo permite gastar.
                //
                // Reset y Respec tendrán operaciones
                // específicas en el futuro.
                // =========================================

                if (
                    $next['strength']
                    <
                    $currentAllocated['strength']
                    ||
                    $next['agility']
                    <
                    $currentAllocated['agility']
                    ||
                    $next['vitality']
                    <
                    $currentAllocated['vitality']
                    ||
                    $next['energy']
                    <
                    $currentAllocated['energy']
                ) {
                    throw (
                        CharacterStatAllocationPersistenceException
                            ::allocationRegression(
                                $currentAllocated,
                                $next
                            )
                    );
                }


                // =========================================
                // VALIDAR BUDGET
                // =========================================

                $nextSpentPoints = (
                    $next['strength']
                    +
                    $next['agility']
                    +
                    $next['vitality']
                    +
                    $next['energy']
                );


                $totalPoints = (
                    (int) $currentSnapshot[
                        'budget'
                    ]['total_points']
                );


                if (
                    $nextSpentPoints
                    >
                    $totalPoints
                ) {
                    throw (
                        CharacterStatAllocationPersistenceException
                            ::budgetExceeded(
                                $totalPoints,
                                $nextSpentPoints,
                                $next
                            )
                    );
                }


                // =========================================
                // PRIMERA ALLOCATION
                // =========================================

                if ($allocation === null) {
                    $allocation = (
                        CharacterStatAllocation::query()
                        ->create([
                            'character_id' => (
                                $lockedCharacter->id
                            ),

                            'allocated_strength' => (
                                $next['strength']
                            ),

                            'allocated_agility' => (
                                $next['agility']
                            ),

                            'allocated_vitality' => (
                                $next['vitality']
                            ),

                            'allocated_energy' => (
                                $next['energy']
                            ),

                            'bonus_stat_points' => 0,

                            'revision' => 1,
                        ])
                    );
                } else {
                    // =====================================
                    // NUEVA REVISION
                    // =====================================

                    $allocation->forceFill([
                        'allocated_strength' => (
                            $next['strength']
                        ),

                        'allocated_agility' => (
                            $next['agility']
                        ),

                        'allocated_vitality' => (
                            $next['vitality']
                        ),

                        'allocated_energy' => (
                            $next['energy']
                        ),

                        'revision' => (
                            $currentRevision
                            +
                            1
                        ),
                    ])->save();
                }


                // =========================================
                // SNAPSHOT AUTORITATIVO RESULTANTE
                // =========================================

                $allocation->refresh();


                $lockedCharacter->setRelation(
                    'statAllocation',
                    $allocation
                );


                $nextSnapshot = (
                    $this->snapshotBuilder->build(
                        $lockedCharacter
                    )
                );


                return [
                    'stat_allocation' => $allocation,

                    'snapshot' => $nextSnapshot,

                    'idempotent' => false,
                ];
            }
        );
    }


    // =====================================================
    // COMPARAR
    // =====================================================

    private function sameAllocation(
        array $left,
        array $right
    ): bool {
        return (
            $left['strength'] === $right['strength']
            &&
            $left['agility'] === $right['agility']
            &&
            $left['vitality'] === $right['vitality']
            &&
            $left['energy'] === $right['energy']
        );
    }
}