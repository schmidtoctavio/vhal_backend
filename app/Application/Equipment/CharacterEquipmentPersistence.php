<?php

namespace App\Application\Equipment;

use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CharacterEquipmentPersistence
{
    public function equipFromInventory(
        Account $account,
        Character $character,
        string $uid,
        int $currentX,
        int $currentY,
        string $equipmentSlot
    ): ItemInstance {
        try {
            return DB::transaction(
                function () use (
                    $account,
                    $character,
                    $uid,
                    $currentX,
                    $currentY,
                    $equipmentSlot
                ): ItemInstance {
                    $item = ItemInstance::query()
                        ->where(
                            'account_id',
                            $account->id
                        )
                        ->where(
                            'character_id',
                            $character->id
                        )
                        ->where(
                            'container',
                            'inventory'
                        )
                        ->where(
                            'uid',
                            $uid
                        )
                        ->lockForUpdate()
                        ->first();


                    if ($item === null) {
                        throw EquipmentPersistenceException::itemNotFound(
                            'inventory'
                        );
                    }


                    // ---------------------------------------------
                    // Validamos la forma persistente del source.
                    // ---------------------------------------------

                    if (
                        $item->grid_x === null
                        ||
                        $item->grid_y === null
                        ||
                        $item->equipment_slot !== null
                    ) {
                        throw EquipmentPersistenceException::sourceStateConflict([
                            'uid' => $item->uid,

                            'container' => $item->container,

                            'grid_position' => [
                                'x' => $item->grid_x,
                                'y' => $item->grid_y,
                            ],

                            'equipment_slot' => (
                                $item->equipment_slot
                            ),
                        ]);
                    }


                    // ---------------------------------------------
                    // Protección contra source stale.
                    // ---------------------------------------------

                    if (
                        $item->grid_x !== $currentX
                        ||
                        $item->grid_y !== $currentY
                    ) {
                        throw EquipmentPersistenceException::sourceStateConflict([
                            'uid' => $item->uid,

                            'container' => $item->container,

                            'grid_position' => [
                                'x' => $item->grid_x,
                                'y' => $item->grid_y,
                            ],
                        ]);
                    }


                    // ---------------------------------------------
                    // Protegemos explícitamente el slot destino.
                    //
                    // El UNIQUE de DB continúa siendo el último
                    // backstop ante concurrencia.
                    // ---------------------------------------------

                    $occupiedItem = ItemInstance::query()
                        ->where(
                            'account_id',
                            $account->id
                        )
                        ->where(
                            'character_id',
                            $character->id
                        )
                        ->where(
                            'container',
                            'equipment'
                        )
                        ->where(
                            'equipment_slot',
                            $equipmentSlot
                        )
                        ->lockForUpdate()
                        ->first();


                    if ($occupiedItem !== null) {
                        throw EquipmentPersistenceException::slotOccupied(
                            $equipmentSlot,
                            $occupiedItem->uid
                        );
                    }


                    // ---------------------------------------------
                    // MISMA FILA / MISMO UID.
                    // ---------------------------------------------

                    $item->container = 'equipment';

                    $item->grid_x = null;

                    $item->grid_y = null;

                    $item->equipment_slot = (
                        $equipmentSlot
                    );


                    $item->save();


                    return $item->refresh();
                }
            );
        } catch (QueryException $exception) {
            // -------------------------------------------------
            // El UNIQUE de:
            //
            // character_id + container + equipment_slot
            //
            // es el último guard contra carreras concurrentes.
            // -------------------------------------------------

            if (
                (string) $exception->getCode()
                ===
                '23000'
            ) {
                throw EquipmentPersistenceException::slotOccupied(
                    $equipmentSlot
                );
            }


            throw $exception;
        }
    }


    public function unequipToInventory(
        Account $account,
        Character $character,
        string $uid,
        string $currentEquipmentSlot,
        int $newX,
        int $newY
    ): ItemInstance {
        return DB::transaction(
            function () use (
                $account,
                $character,
                $uid,
                $currentEquipmentSlot,
                $newX,
                $newY
            ): ItemInstance {
                $item = ItemInstance::query()
                    ->where(
                        'account_id',
                        $account->id
                    )
                    ->where(
                        'character_id',
                        $character->id
                    )
                    ->where(
                        'container',
                        'equipment'
                    )
                    ->where(
                        'uid',
                        $uid
                    )
                    ->lockForUpdate()
                    ->first();


                if ($item === null) {
                    throw EquipmentPersistenceException::itemNotFound(
                        'equipment'
                    );
                }


                // ---------------------------------------------
                // Un Equipment persistido no usa coordenadas.
                // ---------------------------------------------

                if (
                    $item->grid_x !== null
                    ||
                    $item->grid_y !== null
                    ||
                    $item->equipment_slot === null
                ) {
                    throw EquipmentPersistenceException::sourceStateConflict([
                        'uid' => $item->uid,

                        'container' => $item->container,

                        'grid_position' => [
                            'x' => $item->grid_x,
                            'y' => $item->grid_y,
                        ],

                        'equipment_slot' => (
                            $item->equipment_slot
                        ),
                    ]);
                }


                // ---------------------------------------------
                // Protección contra source stale.
                // ---------------------------------------------

                if (
                    $item->equipment_slot
                    !==
                    $currentEquipmentSlot
                ) {
                    throw EquipmentPersistenceException::sourceStateConflict([
                        'uid' => $item->uid,

                        'container' => $item->container,

                        'equipment_slot' => (
                            $item->equipment_slot
                        ),
                    ]);
                }


                // ---------------------------------------------
                // MISMA FILA / MISMO UID.
                // ---------------------------------------------

                $item->container = 'inventory';

                $item->grid_x = $newX;

                $item->grid_y = $newY;

                $item->equipment_slot = null;


                $item->save();


                return $item->refresh();
            }
        );
    }

    public function advanceEnhancementLevel(
        Account $account,
        Character $character,
        string $uid,
        string $expectedContainer,
        int $expectedCurrentLevel,
        int $nextLevel
    ): ItemInstance {
        if (
            ! in_array(
                $expectedContainer,
                [
                    'inventory',
                    'equipment',
                ],
                true
            )
        ) {
            throw EquipmentPersistenceException
                ::invalidEnhancementTransition([
                    'expected_container' => (
                        $expectedContainer
                    ),
                ]);
        }


        if (
            $expectedCurrentLevel < 0
            ||
            $nextLevel < 0
            ||
            $nextLevel !== $expectedCurrentLevel + 1
        ) {
            throw EquipmentPersistenceException
                ::invalidEnhancementTransition([
                    'expected_current_level' => (
                        $expectedCurrentLevel
                    ),

                    'next_level' => $nextLevel,
                ]);
        }


        return DB::transaction(
            function () use (
                $account,
                $character,
                $uid,
                $expectedContainer,
                $expectedCurrentLevel,
                $nextLevel
            ): ItemInstance {
                $item = ItemInstance::query()
                    ->where(
                        'account_id',
                        $account->id
                    )
                    ->where(
                        'character_id',
                        $character->id
                    )
                    ->where(
                        'container',
                        $expectedContainer
                    )
                    ->where(
                        'uid',
                        $uid
                    )
                    ->lockForUpdate()
                    ->first();


                if ($item === null) {
                    throw EquipmentPersistenceException
                        ::itemNotFound(
                            $expectedContainer
                        );
                }


                // ---------------------------------------------
                // VALIDAR FORMA DEL CONTENEDOR
                // ---------------------------------------------

                if (
                    $expectedContainer === 'inventory'
                    &&
                    (
                        $item->grid_x === null
                        ||
                        $item->grid_y === null
                        ||
                        $item->equipment_slot !== null
                    )
                ) {
                    throw EquipmentPersistenceException
                        ::sourceStateConflict([
                            'uid' => $item->uid,

                            'container' => $item->container,

                            'grid_position' => [
                                'x' => $item->grid_x,
                                'y' => $item->grid_y,
                            ],

                            'equipment_slot' => (
                                $item->equipment_slot
                            ),
                        ]);
                }


                if (
                    $expectedContainer === 'equipment'
                    &&
                    (
                        $item->grid_x !== null
                        ||
                        $item->grid_y !== null
                        ||
                        $item->equipment_slot === null
                    )
                ) {
                    throw EquipmentPersistenceException
                        ::sourceStateConflict([
                            'uid' => $item->uid,

                            'container' => $item->container,

                            'grid_position' => [
                                'x' => $item->grid_x,
                                'y' => $item->grid_y,
                            ],

                            'equipment_slot' => (
                                $item->equipment_slot
                            ),
                        ]);
                }


                // ---------------------------------------------
                // ESTADO ACTUAL
                //
                // null / [] legacy representan +0.
                // ---------------------------------------------

                $state = $item->state ?? [];


                if (! is_array($state)) {
                    throw EquipmentPersistenceException
                        ::sourceStateConflict([
                            'uid' => $item->uid,

                            'container' => $item->container,

                            'state' => $item->state,
                        ]);
                }


                $currentLevelValue = (
                    $state['enhancement_level']
                    ??
                    0
                );


                if (
                    ! is_int($currentLevelValue)
                    ||
                    $currentLevelValue < 0
                ) {
                    throw EquipmentPersistenceException
                        ::sourceStateConflict([
                            'uid' => $item->uid,

                            'container' => $item->container,

                            'state' => $state,
                        ]);
                }


                $currentLevel = $currentLevelValue;


                // ---------------------------------------------
                // PROTECCIÓN STALE
                //
                // El Game Server construyó su candidato usando
                // expectedCurrentLevel.
                //
                // Si DB ya cambió, no podemos sobreescribir.
                // ---------------------------------------------

                if (
                    $currentLevel
                    !==
                    $expectedCurrentLevel
                ) {
                    throw EquipmentPersistenceException
                        ::sourceStateConflict([
                            'uid' => $item->uid,

                            'container' => $item->container,

                            'expected_current_level' => (
                                $expectedCurrentLevel
                            ),

                            'actual_current_level' => (
                                $currentLevel
                            ),
                        ]);
                }


                // ---------------------------------------------
                // BACKSTOP DURABLE
                //
                // Laravel no decide Max Level ni gameplay,
                // pero sí garantiza que esta operación sea
                // exactamente N → N+1.
                // ---------------------------------------------

                if (
                    $nextLevel
                    !==
                    $currentLevel + 1
                ) {
                    throw EquipmentPersistenceException
                        ::invalidEnhancementTransition([
                            'uid' => $item->uid,

                            'current_level' => $currentLevel,

                            'next_level' => $nextLevel,
                        ]);
                }


                // ---------------------------------------------
                // PRESERVAR TODO EL RESTO DE STATE
                // ---------------------------------------------

                $state[
                    'enhancement_level'
                ] = $nextLevel;


                $item->state = $state;


                $item->save();


                return $item->refresh();
            }
        );
    }

}