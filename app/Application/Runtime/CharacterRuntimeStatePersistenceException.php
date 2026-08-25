<?php

namespace App\Application\Runtime;

use RuntimeException;


final class CharacterRuntimeStatePersistenceException extends RuntimeException
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
        ?array $current
    ): self {
        return new self(
            'El checkpoint runtime persistente cambió.',
            [
                'expected_revision' => (
                    $expectedRevision
                ),

                'current' => $current,
            ]
        );
    }


    public function context(): array
    {
        return $this->context;
    }
}