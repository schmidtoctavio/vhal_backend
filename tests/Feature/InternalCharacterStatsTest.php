<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterStatAllocation;
use App\Models\GameSessionTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;


class InternalCharacterStatsTest extends TestCase
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
                'username' => 'stats_test',

                'email' => 'stats@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 0,

                'name' => 'StatsTest',

                'class_id' => 'warrior',

                'level' => 124,

                'experience' => 50,

                'reset_count' => 0,
            ]);
    }


    // =====================================================
    // GET — SIN FILA DURABLE
    // =====================================================

    public function test_game_server_can_read_zero_revision_stat_snapshot(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->getJson(
                $this->statsUrl()
            )
            ->assertOk()
            ->assertJsonPath(
                'ok',
                true
            )
            ->assertJsonPath(
                'data.account_id',
                $this->account->id
            )
            ->assertJsonPath(
                'data.character_id',
                $this->character->id
            )
            ->assertJsonPath(
                'data.stats.revision',
                0
            )
            ->assertJsonPath(
                'data.stats.progression.level',
                124
            )
            ->assertJsonPath(
                'data.stats.progression.reset_count',
                0
            )
            ->assertJsonPath(
                'data.stats.allocated.strength',
                0
            )
            ->assertJsonPath(
                'data.stats.budget.level_points',
                615
            )
            ->assertJsonPath(
                'data.stats.budget.total_points',
                615
            )
            ->assertJsonPath(
                'data.stats.budget.unspent_points',
                615
            );


        $this->assertDatabaseCount(
            'character_stat_allocations',
            0
        );
    }


    // =====================================================
    // GET — ASIGNACIÓN DURABLE
    // =====================================================

    public function test_game_server_can_read_existing_stat_allocation(): void
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


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->getJson(
                $this->statsUrl()
            )
            ->assertOk()
            ->assertJsonPath(
                'data.stats.revision',
                3
            )
            ->assertJsonPath(
                'data.stats.allocated.strength',
                100
            )
            ->assertJsonPath(
                'data.stats.allocated.agility',
                50
            )
            ->assertJsonPath(
                'data.stats.allocated.vitality',
                25
            )
            ->assertJsonPath(
                'data.stats.allocated.energy',
                10
            )
            ->assertJsonPath(
                'data.stats.budget.total_points',
                635
            )
            ->assertJsonPath(
                'data.stats.budget.spent_points',
                185
            )
            ->assertJsonPath(
                'data.stats.budget.unspent_points',
                450
            );
    }


    // =====================================================
    // ACCOUNT / CHARACTER BOUNDARY
    // =====================================================

    public function test_stats_read_rejects_character_from_another_account(): void
    {
        $otherAccount = Account::query()
            ->create([
                'username' => 'stats_other',

                'email' => 'stats-other@test.local',

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
            ->getJson(
                $url
            )
            ->assertNotFound()
            ->assertJsonPath(
                'ok',
                false
            );
    }


    // =====================================================
    // INVALID DURABLE BUDGET
    // =====================================================

    public function test_stats_read_rejects_overallocated_durable_state(): void
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


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->getJson(
                $this->statsUrl()
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
                0
            )
            ->assertJsonPath(
                'data.spent_points',
                1
            );
    }


    // =====================================================
    // GAME SESSION TICKET
    // =====================================================

    public function test_consumed_game_session_ticket_includes_stat_snapshot(): void
    {
        $this->character->forceFill([
            'level' => 10,

            'experience' => 25,

            'reset_count' => 1,
        ])->save();


        CharacterStatAllocation::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'allocated_strength' => 100,

                'allocated_agility' => 50,

                'allocated_vitality' => 25,

                'allocated_energy' => 10,

                'bonus_stat_points' => 5,

                'revision' => 4,
            ]);


        $plainTicket = str_repeat(
            's',
            64
        );


        GameSessionTicket::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'character_id' => (
                    $this->character->id
                ),

                'token_hash' => hash(
                    'sha256',
                    $plainTicket
                ),

                'expires_at' => (
                    now()->addMinute()
                ),

                'consumed_at' => null,
            ]);


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                (
                    '/api/internal/game-session'
                    .'/tickets/consume'
                ),
                [
                    'ticket' => $plainTicket,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.character.level',
                10
            )
            ->assertJsonPath(
                'data.character.experience',
                25
            )
            ->assertJsonPath(
                'data.character.reset_count',
                1
            )
            ->assertJsonPath(
                'data.character.stats.revision',
                4
            )
            ->assertJsonPath(
                'data.character.stats.progression.level',
                10
            )
            ->assertJsonPath(
                (
                    'data.character.stats.progression'
                    .'.reset_count'
                ),
                1
            )
            ->assertJsonPath(
                'data.character.stats.budget.level_points',
                45
            )
            ->assertJsonPath(
                'data.character.stats.budget.reset_points',
                350
            )
            ->assertJsonPath(
                'data.character.stats.budget.bonus_points',
                5
            )
            ->assertJsonPath(
                'data.character.stats.budget.total_points',
                400
            )
            ->assertJsonPath(
                'data.character.stats.budget.spent_points',
                185
            )
            ->assertJsonPath(
                'data.character.stats.budget.unspent_points',
                215
            );
    }


    // =====================================================
    // INVALID STATS MUST NOT CONSUME TICKET
    // =====================================================

    public function test_invalid_stat_budget_does_not_consume_game_session_ticket(): void
    {
        $this->character->forceFill([
            'level' => 1,

            'experience' => 0,

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


        $plainTicket = str_repeat(
            'x',
            64
        );


        $ticket = GameSessionTicket::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'character_id' => (
                    $this->character->id
                ),

                'token_hash' => hash(
                    'sha256',
                    $plainTicket
                ),

                'expires_at' => (
                    now()->addMinute()
                ),

                'consumed_at' => null,
            ]);


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                (
                    '/api/internal/game-session'
                    .'/tickets/consume'
                ),
                [
                    'ticket' => $plainTicket,
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
            );


        $ticket->refresh();


        $this->assertNull(
            $ticket->consumed_at
        );
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