<?php

namespace App\Http\Controllers\Api;

use App\Application\Equipment\CharacterEquipmentPersistence;
use App\Application\Equipment\EquipmentPersistenceException;
use App\Domain\Equipment\EquipmentSlotCatalog;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternalCharacterEquipmentController extends Controller
{
    public function __construct(
        private readonly CharacterEquipmentPersistence $persistence
    ) {
    }


    public function show(
        int $accountId,
        int $characterId
    ): JsonResponse {
        $context = $this->resolveCharacterContext(
            $accountId,
            $characterId
        );


        if ($context instanceof JsonResponse) {
            return $context;
        }


        [
            $account,
            $character,
        ] = $context;


        $slotOrder = array_flip(
            EquipmentSlotCatalog::ids()
        );


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
                'equipment'
            )
            ->get()
            ->sortBy(
                static function (
                    ItemInstance $item
                ) use (
                    $slotOrder
                ): int {
                    return $slotOrder[
                        $item->equipment_slot
                    ] ?? PHP_INT_MAX;
                }
            )
            ->values();


        // -------------------------------------------------
        // Un snapshot de Equipment corrupto no se entrega
        // silenciosamente al Game Server.
        // -------------------------------------------------

        foreach ($items as $item) {
            if (
                $item->equipment_slot === null
                ||
                ! EquipmentSlotCatalog::isValid(
                    $item->equipment_slot
                )
                ||
                $item->grid_x !== null
                ||
                $item->grid_y !== null
            ) {
                return response()->json([
                    'ok' => false,

                    'message' => (
                        'El Equipment persistente contiene '
                        .'un estado inválido.'
                    ),

                    'data' => [
                        'uid' => $item->uid,

                        'equipment_slot' => (
                            $item->equipment_slot
                        ),

                        'grid_position' => [
                            'x' => $item->grid_x,
                            'y' => $item->grid_y,
                        ],
                    ],
                ], 409);
            }
        }


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $account->id,

                'character_id' => $character->id,

                'container' => 'equipment',

                'items' => $items
                    ->map(
                        fn (
                            ItemInstance $item
                        ): array => (
                            $this->serializeEquipmentItem(
                                $item
                            )
                        )
                    )
                    ->values()
                    ->all(),
            ],
        ]);
    }


    public function equipItem(
        Request $request,
        int $accountId,
        int $characterId,
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
                'max:7',
            ],

            'equipment_slot' => [
                'required',
                'string',

                Rule::in(
                    EquipmentSlotCatalog::ids()
                ),
            ],
        ]);


        $context = $this->resolveCharacterContext(
            $accountId,
            $characterId
        );


        if ($context instanceof JsonResponse) {
            return $context;
        }


        [
            $account,
            $character,
        ] = $context;


        try {
            $item = $this->persistence->equipFromInventory(
                $account,
                $character,
                $uid,
                (int) $validated[
                    'current_grid_position'
                ]['x'],
                (int) $validated[
                    'current_grid_position'
                ]['y'],
                (string) $validated[
                    'equipment_slot'
                ]
            );
        } catch (
            EquipmentPersistenceException $exception
        ) {
            return $this->persistenceError(
                $exception
            );
        }


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $account->id,

                'character_id' => $character->id,

                'source_container' => 'inventory',

                'target_container' => 'equipment',

                'item' => $this->serializeEquipmentItem(
                    $item
                ),
            ],
        ]);
    }


    public function unequipItem(
        Request $request,
        int $accountId,
        int $characterId,
        string $uid
    ): JsonResponse {
        $validated = $request->validate([
            'current_equipment_slot' => [
                'required',
                'string',

                Rule::in(
                    EquipmentSlotCatalog::ids()
                ),
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
                'max:7',
            ],
        ]);


        $context = $this->resolveCharacterContext(
            $accountId,
            $characterId
        );


        if ($context instanceof JsonResponse) {
            return $context;
        }


        [
            $account,
            $character,
        ] = $context;


        try {
            $item = $this->persistence->unequipToInventory(
                $account,
                $character,
                $uid,
                (string) $validated[
                    'current_equipment_slot'
                ],
                (int) $validated[
                    'new_grid_position'
                ]['x'],
                (int) $validated[
                    'new_grid_position'
                ]['y']
            );
        } catch (
            EquipmentPersistenceException $exception
        ) {
            return $this->persistenceError(
                $exception
            );
        }


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $account->id,

                'character_id' => $character->id,

                'source_container' => 'equipment',

                'target_container' => 'inventory',

                'item' => $this->serializeInventoryItem(
                    $item
                ),
            ],
        ]);
    }


    /**
     * @return array{0: Account, 1: Character}|JsonResponse
     */
    private function resolveCharacterContext(
        int $accountId,
        int $characterId
    ): array|JsonResponse {
        $account = Account::query()
            ->whereKey(
                $accountId
            )
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
            ->whereKey(
                $characterId
            )
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


        return [
            $account,
            $character,
        ];
    }


    private function serializeEquipmentItem(
        ItemInstance $item
    ): array {
        return [
            'uid' => $item->uid,

            'item_id' => $item->item_id,

            'quantity' => $item->quantity,

            'equipment_slot' => (
                $item->equipment_slot
            ),

            'state' => $item->state,
        ];
    }


    private function serializeInventoryItem(
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


    private function persistenceError(
        EquipmentPersistenceException $exception
    ): JsonResponse {
        $status = match (
            $exception->reason()
        ) {
            EquipmentPersistenceException::ITEM_NOT_FOUND
                => 404,

            EquipmentPersistenceException::SOURCE_STATE_CONFLICT,
            EquipmentPersistenceException::SLOT_OCCUPIED
                => 409,

            default
                => 409,
        };


        $payload = [
            'ok' => false,

            'message' => (
                $exception->getMessage()
            ),
        ];


        if ($exception->context() !== []) {
            $payload[
                'data'
            ] = $exception->context();
        }


        return response()->json(
            $payload,
            $status
        );
    }
}