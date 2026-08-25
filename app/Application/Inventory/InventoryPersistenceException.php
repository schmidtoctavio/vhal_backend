<?php

namespace App\Application\Inventory;

use RuntimeException;

final class InventoryPersistenceException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $context = []
    ) {
        parent::__construct(
            $message
        );
    }


    public static function uidConflict(
        string $uid
    ): self {
        return new self(
            'El UID ya pertenece a otro estado persistente.',
            [
                'uid' => $uid,
            ]
        );
    }


    public function context(): array
    {
        return $this->context;
    }
}