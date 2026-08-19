<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ItemInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalVaultController extends Controller
{
    public function show(
        int $accountId
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


        $items = ItemInstance::query()
            ->where(
                'account_id',
                $account->id
            )
            ->whereNull(
                'character_id'
            )
            ->where(
                'container',
                'vault'
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

                'container' => 'vault',

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

    public function moveItem(
        Request $request,
        int $accountId,
        string $uid
    ): JsonResponse {
        $validated = $request->validate([
            'current_grid_position' => [
                'required',
                'array',
            ],

            'current_grid_position.x' => [
                'required',
                'integer',
                'min:0',
                'max:7',
            ],

            'current_grid_position.y' => [
                'required',
                'integer',
                'min:0',
                'max:15',
            ],

            'new_grid_position' => [
                'required',
                'array',
            ],

            'new_grid_position.x' => [
                'required',
                'integer',
                'min:0',
                'max:7',
            ],

            'new_grid_position.y' => [
                'required',
                'integer',
                'min:0',
                'max:15',
            ],
        ]);


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


        return DB::transaction(
            function () use (
                $account,
                $uid,
                $validated
            ): JsonResponse {
                $item = ItemInstance::query()
                    ->where(
                        'account_id',
                        $account->id
                    )
                    ->whereNull(
                        'character_id'
                    )
                    ->where(
                        'container',
                        'vault'
                    )
                    ->where(
                        'uid',
                        $uid
                    )
                    ->lockForUpdate()
                    ->first();


                if ($item === null) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Item de Vault no encontrado.',
                    ], 404);
                }


                $currentX = (int) $validated[
                    'current_grid_position'
                ]['x'];

                $currentY = (int) $validated[
                    'current_grid_position'
                ]['y'];


                if (
                    $item->grid_x !== $currentX
                    ||
                    $item->grid_y !== $currentY
                ) {
                    return response()->json([
                        'ok' => false,

                        'message' => (
                            'La posición persistente del item '
                            .'cambió antes de aplicar la operación.'
                        ),

                        'data' => [
                            'uid' => $item->uid,

                            'grid_position' => [
                                'x' => $item->grid_x,
                                'y' => $item->grid_y,
                            ],
                        ],
                    ], 409);
                }


                $item->grid_x = (int) $validated[
                    'new_grid_position'
                ]['x'];

                $item->grid_y = (int) $validated[
                    'new_grid_position'
                ]['y'];

                $item->save();


                return response()->json([
                    'ok' => true,

                    'data' => [
                        'account_id' => $account->id,

                        'container' => 'vault',

                        'item' => [
                            'uid' => $item->uid,

                            'item_id' => $item->item_id,

                            'quantity' => $item->quantity,

                            'grid_position' => [
                                'x' => $item->grid_x,
                                'y' => $item->grid_y,
                            ],

                            'state' => $item->state,
                        ],
                    ],
                ]);
            }
        );
    }
    
}