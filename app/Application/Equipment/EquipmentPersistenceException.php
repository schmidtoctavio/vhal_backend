<?php

namespace App\Application\Equipment;

use RuntimeException;

final class EquipmentPersistenceException extends RuntimeException
{
    public const ITEM_NOT_FOUND = 'item_not_found';

    public const SOURCE_STATE_CONFLICT = 'source_state_conflict';

    public const SLOT_OCCUPIED = 'slot_occupied';


    public function __construct(
        private readonly string $reason,
        string $message,
        private readonly array $context = []
    ) {
        parent::__construct(
            $message
        );
    }


    public static function itemNotFound(
        string $container
    ): self {
        return new self(
            self::ITEM_NOT_FOUND,
            (
                'Item no encontrado en el contenedor '
                .$container.'.'
            ),
            [
                'container' => $container,
            ]
        );
    }


    public static function sourceStateConflict(
        array $context = []
    ): self {
        return new self(
            self::SOURCE_STATE_CONFLICT,
            (
                'El estado persistente del item cambió '
                .'antes de aplicar la operación.'
            ),
            $context
        );
    }


    public static function slotOccupied(
        string $slotId,
        ?string $occupiedByUid = null
    ): self {
        $context = [
            'equipment_slot' => $slotId,
        ];


        if ($occupiedByUid !== null) {
            $context[
                'occupied_by_uid'
            ] = $occupiedByUid;
        }


        return new self(
            self::SLOT_OCCUPIED,
            'El slot de Equipment ya está ocupado.',
            $context
        );
    }


    public function reason(): string
    {
        return $this->reason;
    }


    public function context(): array
    {
        return $this->context;
    }
}