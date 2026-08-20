<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Http\JsonResponse;

class InternalCharacterInventoryController extends Controller
{
    public function show(
        int $accountId,
        int $characterId
    ): JsonResponse {
        $account = Account::query()
            ->whereKey($accountId)
            ->first();


        if ($account === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Cuenta no encontrada.',
            ], 404);
        }


        if ($account->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'La cuenta no está habilitada.',
            ], 403);
        }


        $character = Character::query()
            ->whereKey($characterId)
            ->where(
                'account_id',
                $account->id
            )
            ->first();


        if ($character === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Personaje no encontrado.',
            ], 404);
        }


        $items = ItemInstance::query()
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
            ->orderBy(
                'grid_y'
            )
            ->orderBy(
                'grid_x'
            )
            ->orderBy(
                'id'
            )
            ->get();


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $account->id,

                'character_id' => $character->id,

                'container' => 'inventory',

                'items' => $items
                    ->map(
                        static function (
                            ItemInstance $item
                        ): array {
                            return [
                                'uid' => $item->uid,

                                'item_id' => $item->item_id,

                                'quantity' => $item->quantity,

                                'grid_position' => [
                                    'x' => $item->grid_x,
                                    'y' => $item->grid_y,
                                ],

                                'state' => $item->state,
                            ];
                        }
                    )
                    ->values()
                    ->all(),
            ],
        ]);
    }
}