<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterSkill;
use App\Models\ItemInstance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;


class InternalCharacterSkillLearningTest extends TestCase
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
                'username' => 'skill_learning_test',

                'email' => (
                    'skill-learning@test.local'
                ),

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 0,

                'name' => 'SkillLearning',

                'class_id' => 'warrior',

                'level' => 25,

                'experience' => 0,
            ]);
    }


    public function test_skill_learning_and_scroll_consumption_are_atomic(): void
    {
        $scroll = $this->createScroll(
            'skill_scroll_heal'
        );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->learningUrl(),
                [
                    'skill_id' => 'heal',

                    'scroll_uid' => $scroll->uid,

                    'scroll_item_id' => (
                        'skill_scroll_heal'
                    ),
                ]
            )
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
                'data.skill.skill_id',
                'heal'
            )
            ->assertJsonPath(
                'data.skill.learned_from_item_uid',
                $scroll->uid
            );


        $this->assertDatabaseHas(
            'character_skills',
            [
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'heal',

                'learned_from_item_uid' => (
                    $scroll->uid
                ),

                'learned_from_item_id' => (
                    'skill_scroll_heal'
                ),
            ]
        );


        $this->assertDatabaseMissing(
            'item_instances',
            [
                'uid' => $scroll->uid,
            ]
        );
    }


    public function test_exact_retry_is_idempotent_after_scroll_was_consumed(): void
    {
        $scroll = $this->createScroll(
            'skill_scroll_heal'
        );


        $payload = [
            'skill_id' => 'heal',

            'scroll_uid' => $scroll->uid,

            'scroll_item_id' => (
                'skill_scroll_heal'
            ),
        ];


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->learningUrl(),
                $payload
            )
            ->assertCreated();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->learningUrl(),
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                true
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


        $this->assertDatabaseMissing(
            'item_instances',
            [
                'uid' => $scroll->uid,
            ]
        );
    }


    public function test_already_learned_skill_does_not_consume_another_scroll(): void
    {
        CharacterSkill::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'heal',
            ]);


        $scroll = $this->createScroll(
            'skill_scroll_heal'
        );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->learningUrl(),
                [
                    'skill_id' => 'heal',

                    'scroll_uid' => $scroll->uid,

                    'scroll_item_id' => (
                        'skill_scroll_heal'
                    ),
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'data.reason',
                'skill_already_learned'
            );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'uid' => $scroll->uid,

                'quantity' => 1,
            ]
        );
    }


    public function test_wrong_scroll_item_does_not_mutate_anything(): void
    {
        $scroll = $this->createScroll(
            'skill_scroll_poison'
        );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->learningUrl(),
                [
                    'skill_id' => 'heal',

                    'scroll_uid' => $scroll->uid,

                    'scroll_item_id' => (
                        'skill_scroll_heal'
                    ),
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'data.reason',
                'scroll_item_mismatch'
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


        $this->assertDatabaseHas(
            'item_instances',
            [
                'uid' => $scroll->uid,

                'quantity' => 1,
            ]
        );
    }


    public function test_scroll_from_another_character_cannot_be_consumed(): void
    {
        $otherCharacter = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 1,

                'name' => 'OtherSkillCharacter',

                'class_id' => 'warrior',

                'level' => 25,

                'experience' => 0,
            ]);


        $scroll = $this->createScroll(
            'skill_scroll_heal',
            $otherCharacter
        );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->learningUrl(),
                [
                    'skill_id' => 'heal',

                    'scroll_uid' => $scroll->uid,

                    'scroll_item_id' => (
                        'skill_scroll_heal'
                    ),
                ]
            )
            ->assertNotFound()
            ->assertJsonPath(
                'data.reason',
                'scroll_not_found'
            );


        $this->assertDatabaseHas(
            'item_instances',
            [
                'uid' => $scroll->uid,
            ]
        );
    }


    public function test_consumed_scroll_uid_cannot_teach_another_skill(): void
    {
        $scroll = $this->createScroll(
            'skill_scroll_heal'
        );


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->learningUrl(),
                [
                    'skill_id' => 'heal',

                    'scroll_uid' => $scroll->uid,

                    'scroll_item_id' => (
                        'skill_scroll_heal'
                    ),
                ]
            )
            ->assertCreated();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                $this->learningUrl(),
                [
                    'skill_id' => 'poison',

                    'scroll_uid' => $scroll->uid,

                    'scroll_item_id' => (
                        'skill_scroll_heal'
                    ),
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'data.reason',
                'scroll_already_used'
            );


        $this->assertDatabaseMissing(
            'character_skills',
            [
                'character_id' => (
                    $this->character->id
                ),

                'skill_id' => 'poison',
            ]
        );
    }


    private function createScroll(
        string $itemId,
        ?Character $character = null
    ): ItemInstance {
        $owner = (
            $character
            ??
            $this->character
        );


        return ItemInstance::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'character_id' => (
                    $owner->id
                ),

                'uid' => (
                    (string) Str::uuid()
                ),

                'item_id' => $itemId,

                'container' => 'inventory',

                'quantity' => 1,

                'grid_x' => 0,

                'grid_y' => 0,

                'equipment_slot' => null,

                'state' => [],
            ]);
    }


    private function gameServerHeaders(): array
    {
        return [
            'X-VHAL-Game-Server-Key'
                => 'test-internal-key',
        ];
    }


    private function learningUrl(): string
    {
        return sprintf(
            (
                '/api/internal/accounts/%d'
                .'/characters/%d/skills/learn'
            ),
            $this->account->id,
            $this->character->id
        );
    }
}