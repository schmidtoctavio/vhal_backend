<?php

namespace App\Domain\Equipment;

final class EquipmentSlotCatalog
{
    public const HEAD = 'head';

    public const CHEST = 'chest';

    public const PANTS = 'pants';

    public const GLOVES = 'gloves';

    public const BOOTS = 'boots';

    public const MAIN_HAND = 'main_hand';

    public const OFF_HAND = 'off_hand';

    public const WINGS = 'wings';

    public const PENDANT = 'pendant';

    public const RING_LEFT = 'ring_left';

    public const RING_RIGHT = 'ring_right';


    private const SLOT_IDS = [
        self::HEAD,
        self::CHEST,
        self::PANTS,
        self::GLOVES,
        self::BOOTS,

        self::MAIN_HAND,
        self::OFF_HAND,

        self::WINGS,
        self::PENDANT,

        self::RING_LEFT,
        self::RING_RIGHT,
    ];


    /**
     * @return list<string>
     */
    public static function ids(): array
    {
        return self::SLOT_IDS;
    }


    public static function isValid(
        string $slotId
    ): bool {
        return in_array(
            $slotId,
            self::SLOT_IDS,
            true
        );
    }
}