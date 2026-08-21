<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalCharacterEquipmentTest extends TestCase
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
                'username' => 'equipment_test',

                'email' => 'equipment@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => $this->account->id,

                'slot_index' => 0,

                'name' => 'EquipmentTest',

                'class_id' => 'warrior',

                'level' => 120,
            ]);
    }


    public function test_game_server_can_read_equipment_snapshot(): void
    {
        $item = $this->createItem([
            'uid' => (
                '11111111-1111-4111-8111-111111111111'
            ),

            'item_id' => 'leather_helmet',

            'container' => 'equipment',

            'grid_x' => null,

            'grid_y' => null,

            'equipment_slot' => 'head',
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->getJson(
                $this->equipmentUrl()
            );


        $response
            ->assertOk()
            ->assertJsonPath(
                'data.account_id',
                $this->account->id
            )
            ->assertJsonPath(
                'data.character_id',
                $this->character->id
            )
            ->assertJsonPath(
                'data.container',
                'equipment'
            )
            ->assertJsonPath(
                'data.items.0.uid',
                $item->uid
            )
            ->assertJsonPath(
                'data.items.0.item_id',
                'leather_helmet'
            )
            ->assertJsonPath(
                'data.items.0.equipment_slot',
                'head'
            );
    }


    public function test_inventory_item_can_be_equipped_without_changing_uid(): void
    {
        $uid = (
            '22222222-2222-4222-8222-222222222222'
        );


        $item = $this->createItem([
            'uid' => $uid,

            'item_id' => 'bronze_sword',

            'container' => 'inventory',

            'grid_x' => 3,

            'grid_y' => 2,

            'equipment_slot' => null,
        ]);


        $originalId = $item->id;


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->equipUrl(
                    $uid
                ),
                [
                    'current_grid_position' => [
                        'x' => 3,
                        'y' => 2,
                    ],

                    'equipment_slot' => 'main_hand',
                ]
            );


        $response
            ->assertOk()
            ->assertJsonPath(
                'data.source_container',
                'inventory'
            )
            ->assertJsonPath(
                'data.target_container',
                'equipment'
            )
            ->assertJsonPath(
                'data.item.uid',
                $uid
            )
            ->assertJsonPath(
                'data.item.equipment_slot',
                'main_hand'
            );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'id' => $originalId,

                'uid' => $uid,

                'account_id' => (
                    $this->account->id
                ),

                'character_id' => (
                    $this->character->id
                ),

                'container' => 'equipment',

                'grid_x' => null,

                'grid_y' => null,

                'equipment_slot' => 'main_hand',
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


    public function test_equip_rejects_stale_inventory_position(): void
    {
        $uid = (
            '33333333-3333-4333-8333-333333333333'
        );


        $this->createItem([
            'uid' => $uid,

            'item_id' => 'bronze_sword',

            'container' => 'inventory',

            'grid_x' => 4,

            'grid_y' => 2,

            'equipment_slot' => null,
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->equipUrl(
                    $uid
                ),
                [
                    'current_grid_position' => [
                        'x' => 1,
                        'y' => 1,
                    ],

                    'equipment_slot' => 'main_hand',
                ]
            );


        $response->assertStatus(
            409
        );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'uid' => $uid,

                'container' => 'inventory',

                'grid_x' => 4,

                'grid_y' => 2,

                'equipment_slot' => null,
            ]
        );
    }


    public function test_equip_rejects_occupied_equipment_slot(): void
    {
        $occupiedUid = (
            '44444444-4444-4444-8444-444444444444'
        );

        $candidateUid = (
            '55555555-5555-4555-8555-555555555555'
        );


        $this->createItem([
            'uid' => $occupiedUid,

            'item_id' => 'bronze_sword',

            'container' => 'equipment',

            'grid_x' => null,

            'grid_y' => null,

            'equipment_slot' => 'main_hand',
        ]);


        $this->createItem([
            'uid' => $candidateUid,

            'item_id' => 'bronze_sword',

            'container' => 'inventory',

            'grid_x' => 2,

            'grid_y' => 3,

            'equipment_slot' => null,
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->equipUrl(
                    $candidateUid
                ),
                [
                    'current_grid_position' => [
                        'x' => 2,
                        'y' => 3,
                    ],

                    'equipment_slot' => 'main_hand',
                ]
            );


        $response
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'data.equipment_slot',
                'main_hand'
            );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'uid' => $candidateUid,

                'container' => 'inventory',

                'grid_x' => 2,

                'grid_y' => 3,
            ]
        );
    }


    public function test_equip_rejects_invalid_equipment_slot(): void
    {
        $uid = (
            '66666666-6666-4666-8666-666666666666'
        );


        $this->createItem([
            'uid' => $uid,

            'item_id' => 'bronze_sword',

            'container' => 'inventory',

            'grid_x' => 1,

            'grid_y' => 1,

            'equipment_slot' => null,
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->equipUrl(
                    $uid
                ),
                [
                    'current_grid_position' => [
                        'x' => 1,
                        'y' => 1,
                    ],

                    'equipment_slot' => 'weapon_left',
                ]
            );


        $response->assertStatus(
            422
        );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'uid' => $uid,

                'container' => 'inventory',
            ]
        );
    }


    public function test_equipped_item_can_be_unequipped_without_changing_uid(): void
    {
        $uid = (
            '77777777-7777-4777-8777-777777777777'
        );


        $item = $this->createItem([
            'uid' => $uid,

            'item_id' => 'bronze_sword',

            'container' => 'equipment',

            'grid_x' => null,

            'grid_y' => null,

            'equipment_slot' => 'main_hand',
        ]);


        $originalId = $item->id;


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->unequipUrl(
                    $uid
                ),
                [
                    'current_equipment_slot' => (
                        'main_hand'
                    ),

                    'new_grid_position' => [
                        'x' => 5,
                        'y' => 4,
                    ],
                ]
            );


        $response
            ->assertOk()
            ->assertJsonPath(
                'data.source_container',
                'equipment'
            )
            ->assertJsonPath(
                'data.target_container',
                'inventory'
            )
            ->assertJsonPath(
                'data.item.uid',
                $uid
            )
            ->assertJsonPath(
                'data.item.grid_position.x',
                5
            )
            ->assertJsonPath(
                'data.item.grid_position.y',
                4
            );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'id' => $originalId,

                'uid' => $uid,

                'container' => 'inventory',

                'grid_x' => 5,

                'grid_y' => 4,

                'equipment_slot' => null,
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


    public function test_unequip_rejects_stale_equipment_slot(): void
    {
        $uid = (
            '88888888-8888-4888-8888-888888888888'
        );


        $this->createItem([
            'uid' => $uid,

            'item_id' => 'bronze_sword',

            'container' => 'equipment',

            'grid_x' => null,

            'grid_y' => null,

            'equipment_slot' => 'main_hand',
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->unequipUrl(
                    $uid
                ),
                [
                    'current_equipment_slot' => (
                        'off_hand'
                    ),

                    'new_grid_position' => [
                        'x' => 2,
                        'y' => 2,
                    ],
                ]
            );


        $response->assertStatus(
            409
        );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'uid' => $uid,

                'container' => 'equipment',

                'grid_x' => null,

                'grid_y' => null,

                'equipment_slot' => 'main_hand',
            ]
        );
    }


    private function createItem(
        array $overrides = []
    ): ItemInstance {
        return ItemInstance::query()
            ->create(
                array_merge(
                    [
                        'account_id' => (
                            $this->account->id
                        ),

                        'character_id' => (
                            $this->character->id
                        ),

                        'uid' => (
                            '99999999-9999-4999-8999-999999999999'
                        ),

                        'item_id' => 'bronze_sword',

                        'container' => 'inventory',

                        'quantity' => 1,

                        'grid_x' => 0,

                        'grid_y' => 0,

                        'equipment_slot' => null,

                        'state' => [],
                    ],
                    $overrides
                )
            );
    }


    private function gameServerHeaders(): array
    {
        return [
            'X-VHAL-Game-Server-Key'
                => 'test-internal-key',
        ];
    }


    private function equipmentUrl(): string
    {
        return sprintf(
            '/api/internal/accounts/%d/characters/%d/equipment',
            $this->account->id,
            $this->character->id
        );
    }


    private function equipUrl(
        string $uid
    ): string {
        return sprintf(
            (
                '/api/internal/accounts/%d/characters/%d'
                .'/equipment/items/%s/equip'
            ),
            $this->account->id,
            $this->character->id,
            $uid
        );
    }


    private function unequipUrl(
        string $uid
    ): string {
        return sprintf(
            (
                '/api/internal/accounts/%d/characters/%d'
                .'/equipment/items/%s/unequip'
            ),
            $this->account->id,
            $this->character->id,
            $uid
        );
    }
}