<?php

namespace Tests\Feature;

use App\Application\Stats\CharacterStatSnapshotBuilder;
use App\Application\Stats\CharacterStatSnapshotException;
use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterStatAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;


class CharacterStatSnapshotBuilderTest extends TestCase
{
    use RefreshDatabase;


    private Account $account;

    private Character $character;


    // =====================================================
    // SETUP
    // =====================================================

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
                'username' => 'stats_snapshot_test',

                'email' => 'stats-snapshot@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 0,

                'name' => 'StatsSnapshotTest',

                'class_id' => 'warrior',

                'level' => 124,

                'experience' => 0,

                'reset_count' => 0,
            ]);
    }


    // =====================================================
    // FOUNDATION SIN FILA DURABLE
    // =====================================================

    public function test_missing_allocation_is_zero_revision_without_creating_row(): void
    {
        $snapshot = app(
            CharacterStatSnapshotBuilder::class
        )->build(
            $this->character
        );


        $this->assertSame(
            0,
            $snapshot['revision']
        );


        $this->assertSame(
            124,
            $snapshot['progression']['level']
        );

        $this->assertSame(
            0,
            $snapshot['progression']['reset_count']
        );


        $this->assertSame(
            0,
            $snapshot['allocated']['strength']
        );

        $this->assertSame(
            0,
            $snapshot['allocated']['agility']
        );

        $this->assertSame(
            0,
            $snapshot['allocated']['vitality']
        );

        $this->assertSame(
            0,
            $snapshot['allocated']['energy']
        );


        $this->assertSame(
            615,
            $snapshot['budget']['level_points']
        );

        $this->assertSame(
            0,
            $snapshot['budget']['reset_points']
        );

        $this->assertSame(
            615,
            $snapshot['budget']['total_points']
        );

        $this->assertSame(
            0,
            $snapshot['budget']['spent_points']
        );

        $this->assertSame(
            615,
            $snapshot['budget']['unspent_points']
        );


        $this->assertDatabaseCount(
            'character_stat_allocations',
            0
        );
    }


    // =====================================================
    // ASIGNACIÓN DURABLE EXISTENTE
    // =====================================================

    public function test_existing_allocation_is_reflected_in_budget(): void
    {
        CharacterStatAllocation::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'allocated_strength' => 100,

                'allocated_agility' => 50,

                'allocated_vitality' => 25,

                'allocated_energy' => 10,

                'bonus_stat_points' => 20,

                'revision' => 3,
            ]);


        $this->character->unsetRelation(
            'statAllocation'
        );


        $snapshot = app(
            CharacterStatSnapshotBuilder::class
        )->build(
            $this->character
        );


        $this->assertSame(
            3,
            $snapshot['revision']
        );


        $this->assertSame(
            100,
            $snapshot['allocated']['strength']
        );

        $this->assertSame(
            50,
            $snapshot['allocated']['agility']
        );

        $this->assertSame(
            25,
            $snapshot['allocated']['vitality']
        );

        $this->assertSame(
            10,
            $snapshot['allocated']['energy']
        );


        $this->assertSame(
            20,
            $snapshot['bonus_stat_points']
        );


        $this->assertSame(
            615,
            $snapshot['budget']['level_points']
        );

        $this->assertSame(
            20,
            $snapshot['budget']['bonus_points']
        );

        $this->assertSame(
            635,
            $snapshot['budget']['total_points']
        );

        $this->assertSame(
            185,
            $snapshot['budget']['spent_points']
        );

        $this->assertSame(
            450,
            $snapshot['budget']['unspent_points']
        );
    }


    // =====================================================
    // RESET POINTS
    // =====================================================

    public function test_reset_count_adds_cumulative_reset_points(): void
    {
        $this->character->forceFill([
            'level' => 1,

            'reset_count' => 2,
        ])->save();


        $snapshot = app(
            CharacterStatSnapshotBuilder::class
        )->build(
            $this->character
        );


        $this->assertSame(
            0,
            $snapshot['budget']['level_points']
        );

        $this->assertSame(
            700,
            $snapshot['budget']['reset_points']
        );

        $this->assertSame(
            700,
            $snapshot['budget']['total_points']
        );

        $this->assertSame(
            700,
            $snapshot['budget']['unspent_points']
        );
    }


    // =====================================================
    // BUDGET INVÁLIDO
    // =====================================================

    public function test_overallocated_durable_state_is_rejected(): void
    {
        $this->character->forceFill([
            'level' => 1,

            'reset_count' => 0,
        ])->save();


        CharacterStatAllocation::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'allocated_strength' => 1,

                'allocated_agility' => 0,

                'allocated_vitality' => 0,

                'allocated_energy' => 0,

                'bonus_stat_points' => 0,

                'revision' => 1,
            ]);


        $this->character->unsetRelation(
            'statAllocation'
        );


        try {
            app(
                CharacterStatSnapshotBuilder::class
            )->build(
                $this->character
            );


            $this->fail(
                'Se esperaba CharacterStatSnapshotException.'
            );
        } catch (
            CharacterStatSnapshotException $exception
        ) {
            $this->assertSame(
                'stat_budget_exceeded',
                $exception->context()['reason']
            );

            $this->assertSame(
                0,
                $exception->context()['total_points']
            );

            $this->assertSame(
                1,
                $exception->context()['spent_points']
            );
        }
    }
}