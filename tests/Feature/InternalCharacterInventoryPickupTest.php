<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalCharacterInventoryPickupTest extends TestCase
{
    use RefreshDatabase;


    private Account $account;

    private Character $character;


    protected function setUp(): void
    {
        parent::setUp();


        config([
            'services.game_server.internal_key'
                => 'test-internal-key',
        ]);


        DB::table(
            'character_classes'
        )->insert([
            'id' => 'warrior',

            'display_name' => 'Warrior',

            'is_enabled' => true,

            'sort_order' => 0,

            'created_at' => now(),

            'updated_at' => now(),
        ]);


        $this->account = Account::query()
            ->create([
                'username' => 'pickup_test',

                'email' => 'pickup@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => $this->account->id,

                'slot_index' => 0,

                'name' => 'PickupTest',

                'class_id' => 'warrior',

                'level' => 1,
            ]);
    }


    public function test_game_server_can_persist_inventory_grant(): void
    {
        $uid = (
            '11111111-1111-4111-8111-111111111111'
        );


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->inventoryItemsUrl(),
                [
                    'uid' => $uid,

                    'item_id' => 'health_potion',

                    'quantity' => 1,

                    'grid_position' => [
                        'x' => 2,
                        'y' => 3,
                    ],
                ]
            );


        $response
            ->assertCreated()
            ->assertJsonPath(
                'ok',
                true
            )
            ->assertJsonPath(
                'data.idempotent',
                false
            )
            ->assertJsonPath(
                'data.item.uid',
                $uid
            )
            ->assertJsonPath(
                'data.item.item_id',
                'health_potion'
            )
            ->assertJsonPath(
                'data.item.quantity',
                1
            )
            ->assertJsonPath(
                'data.item.grid_position.x',
                2
            )
            ->assertJsonPath(
                'data.item.grid_position.y',
                3
            );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'account_id' => (
                    $this->account->id
                ),

                'character_id' => (
                    $this->character->id
                ),

                'uid' => $uid,

                'item_id' => 'health_potion',

                'container' => 'inventory',

                'quantity' => 1,

                'grid_x' => 2,

                'grid_y' => 3,

                'equipment_slot' => null,
            ]
        );
    }


    public function test_same_grant_is_idempotent(): void
    {
        $uid = (
            '22222222-2222-4222-8222-222222222222'
        );


        $payload = [
            'uid' => $uid,

            'item_id' => 'health_potion',

            'quantity' => 1,

            'grid_position' => [
                'x' => 4,
                'y' => 5,
            ],
        ];


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->inventoryItemsUrl(),
                $payload
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.idempotent',
                false
            );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->inventoryItemsUrl(),
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                true
            )
            ->assertJsonPath(
                'data.item.uid',
                $uid
            );


        $this->assertSame(
            1,
            ItemInstance::query()
                ->where(
                    'uid',
                    $uid
                )
                ->count()
        );
    }


    public function test_same_uid_with_different_payload_is_rejected(): void
    {
        $uid = (
            '33333333-3333-4333-8333-333333333333'
        );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->inventoryItemsUrl(),
                [
                    'uid' => $uid,

                    'item_id' => 'health_potion',

                    'quantity' => 1,

                    'grid_position' => [
                        'x' => 1,
                        'y' => 1,
                    ],
                ]
            )
            ->assertCreated();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->inventoryItemsUrl(),
                [
                    'uid' => $uid,

                    'item_id' => 'health_potion',

                    'quantity' => 2,

                    'grid_position' => [
                        'x' => 1,
                        'y' => 1,
                    ],
                ]
            )
            ->assertStatus(
                409
            );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'uid' => $uid,

                'quantity' => 1,
            ]
        );


        $this->assertSame(
            1,
            ItemInstance::query()
                ->where(
                    'uid',
                    $uid
                )
                ->count()
        );
    }


    public function test_invalid_uid_is_rejected(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->inventoryItemsUrl(),
                [
                    'uid' => 'not-a-uuid',

                    'item_id' => 'health_potion',

                    'quantity' => 1,

                    'grid_position' => [
                        'x' => 0,
                        'y' => 0,
                    ],
                ]
            )
            ->assertStatus(
                422
            );
    }


    private function gameServerHeaders(): array
    {
        return [
            'X-VHAL-Game-Server-Key'
                => 'test-internal-key',
        ];
    }


    private function inventoryItemsUrl(): string
    {
        return sprintf(
            (
                '/api/internal/accounts/%d/characters/%d'
                .'/inventory/items'
            ),
            $this->account->id,
            $this->character->id
        );
    }
}