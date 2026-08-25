<?php

namespace App\Application\Inventory;

use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class CharacterInventoryPersistence
{
    public function persistGrantedItem(
        Account $account,
        Character $character,
        string $uid,
        string $itemId,
        int $quantity,
        int $gridX,
        int $gridY
    ): array {
        try {
            return DB::transaction(
                function () use (
                    $account,
                    $character,
                    $uid,
                    $itemId,
                    $quantity,
                    $gridX,
                    $gridY
                ): array {
                    $existingItem = ItemInstance::query()
                        ->where(
                            'uid',
                            $uid
                        )
                        ->lockForUpdate()
                        ->first();


                    if ($existingItem !== null) {
                        return $this->resolveExistingGrant(
                            $existingItem,
                            $account,
                            $character,
                            $uid,
                            $itemId,
                            $quantity,
                            $gridX,
                            $gridY
                        );
                    }


                    $item = ItemInstance::query()
                        ->create([
                            'account_id' => $account->id,

                            'character_id' => $character->id,

                            'uid' => $uid,

                            'item_id' => $itemId,

                            'container' => 'inventory',

                            'quantity' => $quantity,

                            'grid_x' => $gridX,

                            'grid_y' => $gridY,

                            'equipment_slot' => null,

                            'state' => [],
                        ]);


                    return [
                        'item' => $item->refresh(),

                        'created' => true,
                    ];
                }
            );
        } catch (QueryException $exception) {
            /*
             * Protección adicional ante dos requests
             * concurrentes con el mismo UID.
             *
             * item_instances.uid posee UNIQUE.
             */
            if (
                (string) $exception->getCode()
                !==
                '23000'
            ) {
                throw $exception;
            }


            $existingItem = ItemInstance::query()
                ->where(
                    'uid',
                    $uid
                )
                ->first();


            if ($existingItem === null) {
                throw $exception;
            }


            return $this->resolveExistingGrant(
                $existingItem,
                $account,
                $character,
                $uid,
                $itemId,
                $quantity,
                $gridX,
                $gridY
            );
        }
    }


    private function resolveExistingGrant(
        ItemInstance $item,
        Account $account,
        Character $character,
        string $uid,
        string $itemId,
        int $quantity,
        int $gridX,
        int $gridY
    ): array {
        if (
            $item->account_id !== $account->id
            ||
            $item->character_id !== $character->id
            ||
            $item->container !== 'inventory'
            ||
            $item->item_id !== $itemId
            ||
            $item->quantity !== $quantity
            ||
            $item->grid_x !== $gridX
            ||
            $item->grid_y !== $gridY
            ||
            $item->equipment_slot !== null
        ) {
            throw InventoryPersistenceException::uidConflict(
                $uid
            );
        }


        return [
            'item' => $item,

            'created' => false,
        ];
    }
}