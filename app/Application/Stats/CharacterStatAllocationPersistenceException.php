<?php

namespace App\Application\Stats;

use RuntimeException;


final class CharacterStatAllocationPersistenceException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $context = []
    ) {
        parent::__construct(
            $message
        );
    }


    public static function staleRevision(
        int $expectedRevision,
        array $current
    ): self {
        return new self(
            'La asignación persistente de Stats cambió.',
            [
                'reason' => 'stale_revision',

                'expected_revision' => (
                    $expectedRevision
                ),

                'current' => $current,
            ]
        );
    }


    public static function allocationRegression(
        array $currentAllocated,
        array $nextAllocated
    ): self {
        return new self(
            (
                'La asignación normal de Stats '
                .'no puede reducir puntos ya gastados.'
            ),
            [
                'reason' => 'allocation_regression',

                'current_allocated' => (
                    $currentAllocated
                ),

                'next_allocated' => (
                    $nextAllocated
                ),
            ]
        );
    }


    public static function budgetExceeded(
        int $totalPoints,
        int $nextSpentPoints,
        array $nextAllocated
    ): self {
        return new self(
            (
                'La asignación solicitada excede '
                .'el presupuesto disponible.'
            ),
            [
                'reason' => 'stat_budget_exceeded',

                'total_points' => $totalPoints,

                'next_spent_points' => (
                    $nextSpentPoints
                ),

                'next_allocated' => (
                    $nextAllocated
                ),
            ]
        );
    }


    public function context(): array
    {
        return $this->context;
    }
}