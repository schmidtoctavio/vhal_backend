<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InternalItemTransferController extends Controller
{
    public function transfer(
        Request $request,
        int $accountId,
        int $characterId,
        string $uid
    ): JsonResponse {
        $validated = $request->validate([
            'source_container' => [
                'required',
                'string',
                'in:inventory,vault',
            ],

            'target_container' => [
                'required',
                'string',
                'in:inventory,vault',
            ],

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


        $sourceContainer = $validated[
            'source_container'
        ];

        $targetContainer = $validated[
            'target_container'
        ];


        if ($sourceContainer === $targetContainer) {
            return response()->json([
                'ok' => false,

                'message' => (
                    'El contenedor de origen y destino '
                    .'deben ser diferentes.'
                ),
            ], 422);
        }


        $currentX = (int) $validated[
            'current_grid_position'
        ]['x'];

        $currentY = (int) $validated[
            'current_grid_position'
        ]['y'];

        $newX = (int) $validated[
            'new_grid_position'
        ]['x'];

        $newY = (int) $validated[
            'new_grid_position'
        ]['y'];


        if (! $this->isPositionInsideContainer(
            $sourceContainer,
            $currentX,
            $currentY
        )) {
            return response()->json([
                'ok' => false,
                'message' => 'Posición de origen inválida.',
            ], 422);
        }


        if (! $this->isPositionInsideContainer(
            $targetContainer,
            $newX,
            $newY
        )) {
            return response()->json([
                'ok' => false,
                'message' => 'Posición de destino inválida.',
            ], 422);
        }


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


        return DB::transaction(
            function () use (
                $account,
                $character,
                $uid,
                $sourceContainer,
                $targetContainer,
                $currentX,
                $currentY,
                $newX,
                $newY
            ): JsonResponse {
                $query = ItemInstance::query()
                    ->where(
                        'account_id',
                        $account->id
                    )
                    ->where(
                        'container',
                        $sourceContainer
                    )
                    ->where(
                        'uid',
                        $uid
                    );


                if ($sourceContainer === 'inventory') {
                    $query->where(
                        'character_id',
                        $character->id
                    );
                } else {
                    $query->whereNull(
                        'character_id'
                    );
                }


                $item = $query
                    ->lockForUpdate()
                    ->first();


                if ($item === null) {
                    return response()->json([
                        'ok' => false,

                        'message' => (
                            'Item no encontrado en el '
                            .'contenedor de origen.'
                        ),
                    ], 404);
                }


                if (
                    $item->grid_x !== $currentX
                    ||
                    $item->grid_y !== $currentY
                ) {
                    return response()->json([
                        'ok' => false,

                        'message' => (
                            'La posición persistente del item '
                            .'cambió antes de aplicar '
                            .'la transferencia.'
                        ),

                        'data' => [
                            'uid' => $item->uid,

                            'container' => (
                                $item->container
                            ),

                            'grid_position' => [
                                'x' => $item->grid_x,
                                'y' => $item->grid_y,
                            ],
                        ],
                    ], 409);
                }


                if ($targetContainer === 'inventory') {
                    $item->character_id = (
                        $character->id
                    );
                } else {
                    $item->character_id = null;
                }


                $item->container = $targetContainer;

                $item->grid_x = $newX;

                $item->grid_y = $newY;

                $item->equipment_slot = null;

                $item->save();


                return response()->json([
                    'ok' => true,

                    'data' => [
                        'account_id' => (
                            $account->id
                        ),

                        'character_id' => (
                            $character->id
                        ),

                        'source_container' => (
                            $sourceContainer
                        ),

                        'target_container' => (
                            $targetContainer
                        ),

                        'item' => [
                            'uid' => $item->uid,

                            'item_id' => (
                                $item->item_id
                            ),

                            'quantity' => (
                                $item->quantity
                            ),

                            'container' => (
                                $item->container
                            ),

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


    private function isPositionInsideContainer(
        string $container,
        int $x,
        int $y
    ): bool {
        if (
            $x < 0
            ||
            $x >= 8
            ||
            $y < 0
        ) {
            return false;
        }


        return match ($container) {
            'inventory' => $y < 8,
            'vault' => $y < 16,
            default => false,
        };
    }
}