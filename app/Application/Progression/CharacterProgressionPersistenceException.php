<?php

namespace App\Application\Progression;

use RuntimeException;

final class CharacterProgressionPersistenceException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $context = []
    ) {
        parent::__construct(
            $message
        );
    }


    public static function staleState(
        int $expectedLevel,
        int $expectedExperience,
        int $currentLevel,
        int $currentExperience
    ): self {
        return new self(
            'El estado persistente de progresión cambió.',
            [
                'expected' => [
                    'level' => $expectedLevel,
                    'experience' => $expectedExperience,
                ],

                'current' => [
                    'level' => $currentLevel,
                    'experience' => $currentExperience,
                ],
            ]
        );
    }


    public function context(): array
    {
        return $this->context;
    }
}