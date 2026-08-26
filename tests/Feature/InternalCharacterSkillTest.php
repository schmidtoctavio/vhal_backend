<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterSkill;
use App\Models\GameSessionTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;


class InternalCharacterSkillTest extends TestCase
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
                'username' => 'skill_test',

                'email' => 'skill-test@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 0,

                'name' => 'SkillTest',

                'class_id' => 'warrior',

                'level' => 25,

                'experience' => 10,
            ]);
    }


    // =====================================================
    // GRANT
    // =====================================================

    public function test_game_server_can_persist_learned_skill(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->skillsUrl(),
                [
                    'skill_id' => 'heal',
                ]
            )
            ->assertCreated()
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
                'data.idempotent',
                false
            )
            ->assertJsonPath(
                'data.skill.skill_id',
                'heal'
            );


        $this->assertDatabaseHas(
            'character_skills',
            [
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'heal',
            ]
        );
    }


    // =====================================================
    // IDEMPOTENCIA
    // =====================================================

    public function test_same_skill_grant_retry_is_idempotent(): void
    {
        $payload = [
            'skill_id' => 'heal',
        ];


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->skillsUrl(),
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
                $this->skillsUrl(),
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                true
            )
            ->assertJsonPath(
                'data.skill.skill_id',
                'heal'
            );


        $this->assertSame(
            1,
            CharacterSkill::query()
                ->where(
                    'character_id',
                    $this->character->id
                )
                ->where(
                    'skill_id',
                    'heal'
                )
                ->count()
        );
    }


    // =====================================================
    // OWNERSHIP POR PERSONAJE
    // =====================================================

    public function test_two_characters_can_own_same_skill_independently(): void
    {
        $secondCharacter = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 1,

                'name' => 'SkillTestTwo',

                'class_id' => 'warrior',

                'level' => 1,

                'experience' => 0,
            ]);


        CharacterSkill::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'heal',
            ]);


        CharacterSkill::query()
            ->create([
                'character_id' => (
                    $secondCharacter->id
                ),

                'skill_id' => 'heal',
            ]);


        $this->assertSame(
            2,
            CharacterSkill::query()
                ->where(
                    'skill_id',
                    'heal'
                )
                ->count()
        );


        $this->assertDatabaseHas(
            'character_skills',
            [
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'heal',
            ]
        );


        $this->assertDatabaseHas(
            'character_skills',
            [
                'character_id' => (
                    $secondCharacter->id
                ),

                'skill_id' => 'heal',
            ]
        );
    }


    // =====================================================
    // ACCOUNT / CHARACTER BOUNDARY
    // =====================================================

    public function test_skill_grant_rejects_character_from_another_account(): void
    {
        $otherAccount = Account::query()
            ->create([
                'username' => 'skill_test_other',

                'email' => 'skill-test-other@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $url = sprintf(
            (
                '/api/internal/accounts/%d'
                .'/characters/%d/skills'
            ),
            $otherAccount->id,
            $this->character->id
        );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $url,
                [
                    'skill_id' => 'heal',
                ]
            )
            ->assertNotFound()
            ->assertJsonPath(
                'ok',
                false
            );


        $this->assertDatabaseMissing(
            'character_skills',
            [
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'heal',
            ]
        );
    }


    // =====================================================
    // TICKET BOOTSTRAP
    // =====================================================

    public function test_consumed_game_session_ticket_includes_durable_skill_ownership(): void
    {
        CharacterSkill::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'poison',
            ]);


        CharacterSkill::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'heal',
            ]);


        $plainTicket = str_repeat(
            'b',
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
                'ok',
                true
            )
            ->assertJsonPath(
                (
                    'data.character.skills'
                    .'.learned_skill_ids'
                ),
                [
                    'heal',
                    'poison',
                ]
            );
    }


    // =====================================================
    // CASCADE
    // =====================================================

    public function test_character_deletion_removes_skill_ownership(): void
    {
        CharacterSkill::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'heal',
            ]);


        $characterId = (
            $this->character->id
        );


        $this->character->delete();


        $this->assertDatabaseMissing(
            'character_skills',
            [
                'character_id' => (
                    $characterId
                ),

                'skill_id' => 'heal',
            ]
        );
    }


    // =====================================================
    // HEADERS
    // =====================================================

    private function gameServerHeaders(): array
    {
        return [
            'X-VHAL-Game-Server-Key'
                => 'test-internal-key',
        ];
    }


    // =====================================================
    // URL
    // =====================================================

    private function skillsUrl(): string
    {
        return sprintf(
            (
                '/api/internal/accounts/%d'
                .'/characters/%d/skills'
            ),
            $this->account->id,
            $this->character->id
        );
    }
}