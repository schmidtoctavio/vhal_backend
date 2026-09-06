<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalCharacterEquipmentEnhancementTest extends TestCase
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
                'username' => 'enhancement_endpoint_test',

                'email' => 'enhancement-endpoint@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => $this->account->id,

                'slot_index' => 0,

                'name' => 'EnhancementEndpointTest',

                'class_id' => 'warrior',

                'level' => 120,
            ]);
    }


    public function test_game_server_can_enhance_inventory_item(): void
    {
        $item = $this->createItem([
            'state' => [],
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->enhancementUrl(
                    $item->uid
                ),
                [
                    'expected_container'
                        => 'inventory',

                    'expected_current_level'
                        => 0,

                    'next_level'
                        => 1,
                ]
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
                'inventory'
            )
            ->assertJsonPath(
                'data.item.uid',
                $item->uid
            )
            ->assertJsonPath(
                'data.item.state.enhancement_level',
                1
            )
            ->assertJsonPath(
                'data.item.grid_position.x',
                0
            )
            ->assertJsonPath(
                'data.item.grid_position.y',
                0
            );


        $item->refresh();


        $this->assertSame(
            1,
            $item->state[
                'enhancement_level'
            ]
        );
    }


    public function test_game_server_can_enhance_equipped_item_and_preserve_state(): void
    {
        $item = $this->createItem([
            'container' => 'equipment',

            'grid_x' => null,

            'grid_y' => null,

            'equipment_slot' => 'main_hand',

            'state' => [
                'enhancement_level' => 7,

                'rolled_modifiers' => [
                    [
                        'stat_id' => 'strength',

                        'operation_id' => 'flat_add',

                        'value' => 3,
                    ],
                ],
            ],
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->enhancementUrl(
                    $item->uid
                ),
                [
                    'expected_container'
                        => 'equipment',

                    'expected_current_level'
                        => 7,

                    'next_level'
                        => 8,
                ]
            );


        $response
            ->assertOk()
            ->assertJsonPath(
                'data.container',
                'equipment'
            )
            ->assertJsonPath(
                'data.item.uid',
                $item->uid
            )
            ->assertJsonPath(
                'data.item.equipment_slot',
                'main_hand'
            )
            ->assertJsonPath(
                'data.item.state.enhancement_level',
                8
            );


        $item->refresh();


        $this->assertSame(
            8,
            $item->state[
                'enhancement_level'
            ]
        );


        $this->assertSame(
            [
                [
                    'stat_id' => 'strength',

                    'operation_id' => 'flat_add',

                    'value' => 3,
                ],
            ],
            $item->state[
                'rolled_modifiers'
            ]
        );
    }


    public function test_endpoint_rejects_stale_enhancement_level(): void
    {
        $item = $this->createItem([
            'state' => [
                'enhancement_level' => 7,
            ],
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->enhancementUrl(
                    $item->uid
                ),
                [
                    'expected_container'
                        => 'inventory',

                    'expected_current_level'
                        => 6,

                    'next_level'
                        => 7,
                ]
            );


        $response->assertStatus(
            409
        );


        $item->refresh();


        $this->assertSame(
            7,
            $item->state[
                'enhancement_level'
            ]
        );
    }


    public function test_endpoint_rejects_level_jump(): void
    {
        $item = $this->createItem([
            'state' => [
                'enhancement_level' => 7,
            ],
        ]);


        $response = $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->enhancementUrl(
                    $item->uid
                ),
                [
                    'expected_container'
                        => 'inventory',

                    'expected_current_level'
                        => 7,

                    'next_level'
                        => 9,
                ]
            );


        $response->assertStatus(
            409
        );


        $item->refresh();


        $this->assertSame(
            7,
            $item->state[
                'enhancement_level'
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
                            '11111111-1111-4111-8111-111111111111'
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


    private function enhancementUrl(
        string $uid
    ): string {
        return sprintf(
            (
                '/api/internal/accounts/%d/characters/%d'
                .'/equipment/items/%s/enhancement'
            ),
            $this->account->id,
            $this->character->id,
            $uid
        );
    }
}