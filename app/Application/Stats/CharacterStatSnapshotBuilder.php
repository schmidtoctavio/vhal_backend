<?php

namespace App\Application\Stats;

use App\Models\Character;
use App\Models\CharacterStatAllocation;


class CharacterStatSnapshotBuilder
{
    // =====================================================
    // FOUNDATION DE PROGRESIÓN DE STATS
    // =====================================================
    //
    // Estos valores pertenecen al contrato durable de
    // presupuesto de Stats del Backend.
    //
    // No son fórmulas de Damage / HP / Combat.
    // Esas seguirán siendo responsabilidad gameplay.
    // =====================================================

    public const STAT_POINTS_PER_LEVEL = 5;

    public const RESET_STAT_POINTS = 350;


    // =====================================================
    // SNAPSHOT
    // =====================================================

    public function build(
        Character $character
    ): array {
        $character->loadMissing(
            'statAllocation'
        );


        $level = (int) $character->level;

        $resetCount = (int) $character->reset_count;


        if ($level < 1) {
            throw new CharacterStatSnapshotException(
                'El nivel durable del personaje es inválido.',
                [
                    'reason' => 'invalid_character_level',

                    'level' => $level,
                ]
            );
        }


        if ($resetCount < 0) {
            throw new CharacterStatSnapshotException(
                'El número de resets del personaje es inválido.',
                [
                    'reason' => 'invalid_reset_count',

                    'reset_count' => $resetCount,
                ]
            );
        }


        /** @var CharacterStatAllocation|null $allocation */
        $allocation = (
            $character->statAllocation
        );


        $revision = (
            $allocation === null
            ?
            0
            :
            (int) $allocation->revision
        );


        $allocatedStrength = (
            $allocation === null
            ?
            0
            :
            (int) $allocation->allocated_strength
        );

        $allocatedAgility = (
            $allocation === null
            ?
            0
            :
            (int) $allocation->allocated_agility
        );

        $allocatedVitality = (
            $allocation === null
            ?
            0
            :
            (int) $allocation->allocated_vitality
        );

        $allocatedEnergy = (
            $allocation === null
            ?
            0
            :
            (int) $allocation->allocated_energy
        );


        $bonusStatPoints = (
            $allocation === null
            ?
            0
            :
            (int) $allocation->bonus_stat_points
        );


        // =================================================
        // BUDGET DERIVADO
        // =================================================

        $levelPoints = (
            ($level - 1)
            *
            self::STAT_POINTS_PER_LEVEL
        );


        $resetPoints = (
            $resetCount
            *
            self::RESET_STAT_POINTS
        );


        $totalPoints = (
            $levelPoints
            +
            $resetPoints
            +
            $bonusStatPoints
        );


        $spentPoints = (
            $allocatedStrength
            +
            $allocatedAgility
            +
            $allocatedVitality
            +
            $allocatedEnergy
        );


        // =================================================
        // INVARIANTE CRÍTICA
        // =================================================
        //
        // Nunca permitimos representar como válido un
        // personaje que haya gastado más puntos de los que
        // su Level + Resets + Bonuses permiten.
        // =================================================

        if ($spentPoints > $totalPoints) {
            throw new CharacterStatSnapshotException(
                (
                    'La asignación durable de Stats excede '
                    .'el presupuesto disponible.'
                ),
                [
                    'reason' => 'stat_budget_exceeded',

                    'total_points' => $totalPoints,

                    'spent_points' => $spentPoints,
                ]
            );
        }


        $unspentPoints = (
            $totalPoints
            -
            $spentPoints
        );


        // =================================================
        // CONTRATO CANÓNICO
        // =================================================

        return [
            'revision' => $revision,


            'progression' => [
                'level' => $level,

                'reset_count' => $resetCount,
            ],


            'allocated' => [
                'strength' => $allocatedStrength,

                'agility' => $allocatedAgility,

                'vitality' => $allocatedVitality,

                'energy' => $allocatedEnergy,
            ],


            'bonus_stat_points' => (
                $bonusStatPoints
            ),


            'budget' => [
                'points_per_level' => (
                    self::STAT_POINTS_PER_LEVEL
                ),

                'points_per_reset' => (
                    self::RESET_STAT_POINTS
                ),

                'level_points' => $levelPoints,

                'reset_points' => $resetPoints,

                'bonus_points' => (
                    $bonusStatPoints
                ),

                'total_points' => $totalPoints,

                'spent_points' => $spentPoints,

                'unspent_points' => (
                    $unspentPoints
                ),
            ],
        ];
    }
}