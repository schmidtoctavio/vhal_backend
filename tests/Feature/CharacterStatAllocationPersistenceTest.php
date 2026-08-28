<?php

namespace Tests\Feature;

use App\Application\Stats\CharacterStatAllocationPersistence;
use App\Application\Stats\CharacterStatAllocationPersistenceException;
use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterStatAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;


class CharacterStatAllocationPersistenceTest extends TestCase
{
    use RefreshDatabase;


    private Account $account;

    private Character $character;


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
                'username' => 'stat_persistence_test',

                'email' => 'stat-persistence@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 0,

                'name' => 'StatPersistenceTest',

                'class_id' => 'warrior',

                'level' => 124,

                'experience' => 0,

                'reset_count' => 0,
            ]);
    }


    // =====================================================
    // PRIMERA ALLOCATION
    // =====================================================

    public function test_first_stat_allocation_creates_revision_one(): void
    {
        $result = app(
            CharacterStatAllocationPersistence::class
        )->persistAllocation(
            $this->account,
            $this->character,
            0,
            [
                'strength' => 10,

                'agility' => 0,

                'vitality' => 0,

                'energy' => 0,
            ]
        );


        $this->assertFalse(
            $result['idempotent']
        );


        $this->assertSame(
            1,
            $result['snapshot']['revision']
        );


        $this->assertSame(
            10,
            $result['snapshot'][
                'allocated'
            ]['strength']
        );


        $this->assertSame(
            10,
            $result['snapshot'][
                'budget'
            ]['spent_points']
        );


        $this->assertSame(
            605,
            $result['snapshot'][
                'budget'
            ]['unspent_points']
        );


        $this->assertDatabaseHas(
            'character_stat_allocations',
            [
                'character_id' => (
                    $this->character->id
                ),

                'allocated_strength' => 10,

                'revision' => 1,
            ]
        );
    }


    // =====================================================
    // IDEMPOTENT RETRY
    // =====================================================

    public function test_exact_retry_is_idempotent(): void
    {
        $persistence = app(
            CharacterStatAllocationPersistence::class
        );


        $next = [
            'strength' => 10,

            'agility' => 0,

            'vitality' => 0,

            'energy' => 0,
        ];


        $first = $persistence->persistAllocation(
            $this->account,
            $this->character,
            0,
            $next
        );


        $retry = $persistence->persistAllocation(
            $this->account,
            $this->character,
            0,
            $next
        );


        $this->assertFalse(
            $first['idempotent']
        );

        $this->assertTrue(
            $retry['idempotent']
        );


        $this->assertSame(
            1,
            $retry['snapshot']['revision']
        );


        $this->assertSame(
            10,
            $retry['snapshot'][
                'allocated'
            ]['strength']
        );


        $this->assertDatabaseCount(
            'character_stat_allocations',
            1
        );
    }


    // =====================================================
    // SIGUIENTE REVISION
    // =====================================================

    public function test_existing_allocation_can_advance_revision(): void
    {
        $persistence = app(
            CharacterStatAllocationPersistence::class
        );


        $persistence->persistAllocation(
            $this->account,
            $this->character,
            0,
            [
                'strength' => 10,

                'agility' => 0,

                'vitality' => 0,

                'energy' => 0,
            ]
        );


        $result = $persistence->persistAllocation(
            $this->account,
            $this->character,
            1,
            [
                'strength' => 10,

                'agility' => 5,

                'vitality' => 0,

                'energy' => 0,
            ]
        );


        $this->assertFalse(
            $result['idempotent']
        );


        $this->assertSame(
            2,
            $result['snapshot']['revision']
        );


        $this->assertSame(
            10,
            $result['snapshot'][
                'allocated'
            ]['strength']
        );

        $this->assertSame(
            5,
            $result['snapshot'][
                'allocated'
            ]['agility']
        );


        $this->assertSame(
            15,
            $result['snapshot'][
                'budget'
            ]['spent_points']
        );


        $this->assertSame(
            600,
            $result['snapshot'][
                'budget'
            ]['unspent_points']
        );
    }


    // =====================================================
    // STALE REVISION
    // =====================================================

    public function test_stale_revision_is_rejected_without_mutation(): void
    {
        $persistence = app(
            CharacterStatAllocationPersistence::class
        );


        $persistence->persistAllocation(
            $this->account,
            $this->character,
            0,
            [
                'strength' => 10,

                'agility' => 0,

                'vitality' => 0,

                'energy' => 0,
            ]
        );


        try {
            $persistence->persistAllocation(
                $this->account,
                $this->character,
                0,
                [
                    'strength' => 20,

                    'agility' => 0,

                    'vitality' => 0,

                    'energy' => 0,
                ]
            );


            $this->fail(
                'Se esperaba stale_revision.'
            );
        } catch (
            CharacterStatAllocationPersistenceException $exception
        ) {
            $this->assertSame(
                'stale_revision',
                $exception->context()['reason']
            );


            $this->assertSame(
                1,
                $exception->context()[
                    'current'
                ]['revision']
            );
        }


        $this->assertDatabaseHas(
            'character_stat_allocations',
            [
                'character_id' => (
                    $this->character->id
                ),

                'allocated_strength' => 10,

                'revision' => 1,
            ]
        );
    }


    // =====================================================
    // OVERSPEND
    // =====================================================

    public function test_allocation_cannot_exceed_available_budget(): void
    {
        try {
            app(
                CharacterStatAllocationPersistence::class
            )->persistAllocation(
                $this->account,
                $this->character,
                0,
                [
                    'strength' => 616,

                    'agility' => 0,

                    'vitality' => 0,

                    'energy' => 0,
                ]
            );


            $this->fail(
                'Se esperaba stat_budget_exceeded.'
            );
        } catch (
            CharacterStatAllocationPersistenceException $exception
        ) {
            $this->assertSame(
                'stat_budget_exceeded',
                $exception->context()['reason']
            );


            $this->assertSame(
                615,
                $exception->context()['total_points']
            );


            $this->assertSame(
                616,
                $exception->context()[
                    'next_spent_points'
                ]
            );
        }


        $this->assertDatabaseCount(
            'character_stat_allocations',
            0
        );
    }


    // =====================================================
    // REGRESSION
    // =====================================================

    public function test_normal_allocation_cannot_remove_spent_points(): void
    {
        $persistence = app(
            CharacterStatAllocationPersistence::class
        );


        $persistence->persistAllocation(
            $this->account,
            $this->character,
            0,
            [
                'strength' => 10,

                'agility' => 0,

                'vitality' => 0,

                'energy' => 0,
            ]
        );


        try {
            $persistence->persistAllocation(
                $this->account,
                $this->character,
                1,
                [
                    'strength' => 5,

                    'agility' => 0,

                    'vitality' => 0,

                    'energy' => 0,
                ]
            );


            $this->fail(
                'Se esperaba allocation_regression.'
            );
        } catch (
            CharacterStatAllocationPersistenceException $exception
        ) {
            $this->assertSame(
                'allocation_regression',
                $exception->context()['reason']
            );


            $this->assertSame(
                10,
                $exception->context()[
                    'current_allocated'
                ]['strength']
            );


            $this->assertSame(
                5,
                $exception->context()[
                    'next_allocated'
                ]['strength']
            );
        }


        $this->assertDatabaseHas(
            'character_stat_allocations',
            [
                'character_id' => (
                    $this->character->id
                ),

                'allocated_strength' => 10,

                'revision' => 1,
            ]
        );
    }


    // =====================================================
    // ACCOUNT BOUNDARY
    // =====================================================

    public function test_character_from_another_account_cannot_be_mutated(): void
    {
        $otherAccount = Account::query()
            ->create([
                'username' => 'stat_persistence_other',

                'email' => 'stat-persistence-other@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        try {
            app(
                CharacterStatAllocationPersistence::class
            )->persistAllocation(
                $otherAccount,
                $this->character,
                0,
                [
                    'strength' => 10,

                    'agility' => 0,

                    'vitality' => 0,

                    'energy' => 0,
                ]
            );


            $this->fail(
                'Se esperaba RuntimeException.'
            );
        } catch (
            RuntimeException $exception
        ) {
            $this->assertSame(
                (
                    'El personaje dejó de estar '
                    .'disponible.'
                ),
                $exception->getMessage()
            );
        }


        $this->assertDatabaseCount(
            'character_stat_allocations',
            0
        );
    }
}