<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;


class InternalCharacterStatAllocationTest extends TestCase
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
                'username' => 'stat_endpoint_test',

                'email' => 'stat-endpoint@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 0,

                'name' => 'StatEndpointTest',

                'class_id' => 'warrior',

                'level' => 124,

                'experience' => 0,

                'reset_count' => 0,
            ]);
    }


    // =====================================================
    // FIRST ALLOCATION
    // =====================================================

    public function test_game_server_can_allocate_stats(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                [
                    'expected_revision' => 0,

                    'next' => [
                        'strength' => 10,

                        'agility' => 0,

                        'vitality' => 0,

                        'energy' => 0,
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'ok',
                true
            )
            ->assertJsonPath(
                'data.idempotent',
                false
            )
            ->assertJsonPath(
                'data.stats.revision',
                1
            )
            ->assertJsonPath(
                'data.stats.allocated.strength',
                10
            )
            ->assertJsonPath(
                'data.stats.budget.spent_points',
                10
            )
            ->assertJsonPath(
                'data.stats.budget.unspent_points',
                605
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

    public function test_exact_allocation_retry_is_idempotent(): void
    {
        $payload = [
            'expected_revision' => 0,

            'next' => [
                'strength' => 10,

                'agility' => 0,

                'vitality' => 0,

                'energy' => 0,
            ],
        ];


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                false
            );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                true
            )
            ->assertJsonPath(
                'data.stats.revision',
                1
            )
            ->assertJsonPath(
                'data.stats.allocated.strength',
                10
            );
    }


    // =====================================================
    // NEXT REVISION
    // =====================================================

    public function test_game_server_can_allocate_more_stats(): void
    {
        $this->allocateFirstTenStrength();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                [
                    'expected_revision' => 1,

                    'next' => [
                        'strength' => 10,

                        'agility' => 5,

                        'vitality' => 0,

                        'energy' => 0,
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                false
            )
            ->assertJsonPath(
                'data.stats.revision',
                2
            )
            ->assertJsonPath(
                'data.stats.allocated.agility',
                5
            )
            ->assertJsonPath(
                'data.stats.budget.spent_points',
                15
            )
            ->assertJsonPath(
                'data.stats.budget.unspent_points',
                600
            );
    }


    // =====================================================
    // STALE
    // =====================================================

    public function test_stale_revision_is_rejected(): void
    {
        $this->allocateFirstTenStrength();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                [
                    'expected_revision' => 0,

                    'next' => [
                        'strength' => 20,

                        'agility' => 0,

                        'vitality' => 0,

                        'energy' => 0,
                    ],
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'ok',
                false
            )
            ->assertJsonPath(
                'data.reason',
                'stale_revision'
            )
            ->assertJsonPath(
                'data.current.revision',
                1
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
    // OVERSPEND
    // =====================================================

    public function test_overspend_is_rejected(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                [
                    'expected_revision' => 0,

                    'next' => [
                        'strength' => 616,

                        'agility' => 0,

                        'vitality' => 0,

                        'energy' => 0,
                    ],
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'ok',
                false
            )
            ->assertJsonPath(
                'data.reason',
                'stat_budget_exceeded'
            )
            ->assertJsonPath(
                'data.total_points',
                615
            )
            ->assertJsonPath(
                'data.next_spent_points',
                616
            );


        $this->assertDatabaseCount(
            'character_stat_allocations',
            0
        );
    }


    // =====================================================
    // REGRESSION / RESPEC NOT ALLOWED
    // =====================================================

    public function test_normal_endpoint_cannot_remove_allocated_points(): void
    {
        $this->allocateFirstTenStrength();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                [
                    'expected_revision' => 1,

                    'next' => [
                        'strength' => 5,

                        'agility' => 0,

                        'vitality' => 0,

                        'energy' => 0,
                    ],
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'ok',
                false
            )
            ->assertJsonPath(
                'data.reason',
                'allocation_regression'
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
    // VALIDATION
    // =====================================================

    public function test_negative_stat_value_is_rejected_by_validation(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                [
                    'expected_revision' => 0,

                    'next' => [
                        'strength' => -1,

                        'agility' => 0,

                        'vitality' => 0,

                        'energy' => 0,
                    ],
                ]
            )
            ->assertStatus(
                422
            );


        $this->assertDatabaseCount(
            'character_stat_allocations',
            0
        );
    }


    // =====================================================
    // ACCOUNT BOUNDARY
    // =====================================================

    public function test_other_account_cannot_allocate_character_stats(): void
    {
        $otherAccount = Account::query()
            ->create([
                'username' => 'stat_endpoint_other',

                'email' => 'stat-endpoint-other@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $url = sprintf(
            (
                '/api/internal/accounts/%d'
                .'/characters/%d/stats'
            ),
            $otherAccount->id,
            $this->character->id
        );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $url,
                [
                    'expected_revision' => 0,

                    'next' => [
                        'strength' => 10,

                        'agility' => 0,

                        'vitality' => 0,

                        'energy' => 0,
                    ],
                ]
            )
            ->assertNotFound()
            ->assertJsonPath(
                'ok',
                false
            );


        $this->assertDatabaseCount(
            'character_stat_allocations',
            0
        );
    }


    // =====================================================
    // HELPERS
    // =====================================================

    private function allocateFirstTenStrength(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->statsUrl(),
                [
                    'expected_revision' => 0,

                    'next' => [
                        'strength' => 10,

                        'agility' => 0,

                        'vitality' => 0,

                        'energy' => 0,
                    ],
                ]
            )
            ->assertOk();
    }


    private function gameServerHeaders(): array
    {
        return [
            'X-VHAL-Game-Server-Key'
                => 'test-internal-key',
        ];
    }


    private function statsUrl(): string
    {
        return sprintf(
            (
                '/api/internal/accounts/%d'
                .'/characters/%d/stats'
            ),
            $this->account->id,
            $this->character->id
        );
    }
}