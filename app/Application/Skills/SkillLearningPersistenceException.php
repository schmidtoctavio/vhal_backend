<?php

namespace App\Application\Skills;

use RuntimeException;


final class SkillLearningPersistenceException extends RuntimeException
{
    private string $reason;

    private array $exceptionContext;


    private function __construct(
        string $reason,
        string $message,
        array $context = []
    ) {
        parent::__construct(
            $message
        );


        $this->reason = $reason;

        $this->exceptionContext = $context;
    }


    public function reason(): string
    {
        return $this->reason;
    }


    public function context(): array
    {
        return $this->exceptionContext;
    }


    public static function alreadyLearned(
        string $skillId
    ): self {
        return new self(
            'skill_already_learned',
            'El personaje ya aprendió esta skill.',
            [
                'skill_id' => $skillId,
            ]
        );
    }


    public static function scrollNotFound(
        string $scrollUid
    ): self {
        return new self(
            'scroll_not_found',
            'El Scroll requerido no existe en el Inventory del personaje.',
            [
                'scroll_uid' => $scrollUid,
            ]
        );
    }


    public static function scrollItemMismatch(
        string $scrollUid,
        string $expectedItemId,
        string $actualItemId
    ): self {
        return new self(
            'scroll_item_mismatch',
            'El item indicado no corresponde al Scroll esperado.',
            [
                'scroll_uid' => $scrollUid,

                'expected_item_id' => $expectedItemId,

                'actual_item_id' => $actualItemId,
            ]
        );
    }


    public static function scrollAlreadyUsed(
        string $scrollUid
    ): self {
        return new self(
            'scroll_already_used',
            'Este Scroll ya fue utilizado para aprender otra skill.',
            [
                'scroll_uid' => $scrollUid,
            ]
        );
    }


    public static function invalidScrollQuantity(
        string $scrollUid
    ): self {
        return new self(
            'invalid_scroll_quantity',
            'El Scroll posee una cantidad persistente inválida.',
            [
                'scroll_uid' => $scrollUid,
            ]
        );
    }
}