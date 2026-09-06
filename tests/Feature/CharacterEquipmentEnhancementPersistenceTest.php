<?php

namespace Tests\Feature;

use App\Application\Equipment\CharacterEquipmentPersistence;
use App\Application\Equipment\EquipmentPersistenceException;
use App\Models\Account;
use App\Models\Character;
use App\Models\ItemInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CharacterEquipmentEnhancementPersistenceTest extends TestCase
{
    use RefreshDatabase;


    private Account $account;

    private Character $character;

    private CharacterEquipmentPersistence $persistence;


    protected function setUp(): void
    {
        parent::setUp();


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
                'username' => 'enhancement_test',

                'email' => 'enhancement@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => $this->account->id,

                'slot_index' => 0,

                'name' => 'EnhancementTest',

                'class_id' => 'warrior',

                'level' => 120,
            ]);


        $this->persistence = app(
            CharacterEquipmentPersistence::class
        );
    }


    public function test_legacy_zero_can_advance_to_one(): void
    {
        $item = $this->createItem([
            'state' => [],
        ]);


        $result = $this->persistence
            ->advanceEnhancementLevel(
                $this->account,
                $this->character,
                $item->uid,
                'inventory',
                0,
                1
            );


        $this->assertSame(
            1,
            $result->state[
                'enhancement_level'
            ]
        );
    }


    public function test_advancing_level_preserves_other_state(): void
    {
        $item = $this->createItem([
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


        $result = $this->persistence
            ->advanceEnhancementLevel(
                $this->account,
                $this->character,
                $item->uid,
                'inventory',
                7,
                8
            );


        $this->assertSame(
            8,
            $result->state[
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
            $result->state[
                'rolled_modifiers'
            ]
        );
    }


    public function test_stale_current_level_is_rejected(): void
    {
        $item = $this->createItem([
            'state' => [
                'enhancement_level' => 7,
            ],
        ]);


        try {
            $this->persistence
                ->advanceEnhancementLevel(
                    $this->account,
                    $this->character,
                    $item->uid,
                    'inventory',
                    6,
                    7
                );


            $this->fail(
                'Se esperaba SOURCE_STATE_CONFLICT.'
            );
        } catch (
            EquipmentPersistenceException $exception
        ) {
            $this->assertSame(
                EquipmentPersistenceException
                    ::SOURCE_STATE_CONFLICT,
                $exception->reason()
            );
        }


        $item->refresh();


        $this->assertSame(
            7,
            $item->state[
                'enhancement_level'
            ]
        );
    }


    public function test_level_jump_is_rejected(): void
    {
        $item = $this->createItem([
            'state' => [
                'enhancement_level' => 7,
            ],
        ]);


        try {
            $this->persistence
                ->advanceEnhancementLevel(
                    $this->account,
                    $this->character,
                    $item->uid,
                    'inventory',
                    7,
                    9
                );


            $this->fail(
                'Se esperaba INVALID_ENHANCEMENT_TRANSITION.'
            );
        } catch (
            EquipmentPersistenceException $exception
        ) {
            $this->assertSame(
                EquipmentPersistenceException
                    ::INVALID_ENHANCEMENT_TRANSITION,
                $exception->reason()
            );
        }


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
}