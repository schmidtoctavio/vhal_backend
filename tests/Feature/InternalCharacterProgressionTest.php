<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalCharacterProgressionTest extends TestCase
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
                'username' => 'progression_test',

                'email' => 'progression@test.local',

                'password' => 'secret',

                'status' => 'active',
            ]);


        $this->character = Character::query()
            ->create([
                'account_id' => $this->account->id,

                'slot_index' => 0,

                'name' => 'ProgressionTest',

                'class_id' => 'warrior',

                'level' => 120,

                'experience' => 0,
            ]);
    }


    public function test_game_server_can_persist_experience(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->progressionUrl(),
                [
                    'expected' => [
                        'level' => 120,

                        'experience' => 0,
                    ],

                    'next' => [
                        'level' => 120,

                        'experience' => 50,
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
                'data.progression.level',
                120
            )
            ->assertJsonPath(
                'data.progression.experience',
                50
            );


        $this->assertDatabaseHas(
            'characters',
            [
                'id' => $this->character->id,

                'level' => 120,

                'experience' => 50,
            ]
        );
    }


    public function test_same_progression_state_is_idempotent(): void
    {
        $payload = [
            'expected' => [
                'level' => 120,

                'experience' => 0,
            ],

            'next' => [
                'level' => 120,

                'experience' => 50,
            ],
        ];


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->progressionUrl(),
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
                $this->progressionUrl(),
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                true
            )
            ->assertJsonPath(
                'data.progression.experience',
                50
            );


        $this->assertDatabaseHas(
            'characters',
            [
                'id' => $this->character->id,

                'level' => 120,

                'experience' => 50,
            ]
        );
    }


    public function test_stale_progression_is_rejected(): void
    {
        $this->character->forceFill([
            'experience' => 50,
        ])->save();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->progressionUrl(),
                [
                    'expected' => [
                        'level' => 120,

                        'experience' => 0,
                    ],

                    'next' => [
                        'level' => 120,

                        'experience' => 75,
                    ],
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'data.current.level',
                120
            )
            ->assertJsonPath(
                'data.current.experience',
                50
            );


        $this->assertDatabaseHas(
            'characters',
            [
                'id' => $this->character->id,

                'experience' => 50,
            ]
        );
    }


    public function test_game_server_can_persist_level_up(): void
    {
        $this->character->forceFill([
            'experience' => 50,
        ])->save();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->progressionUrl(),
                [
                    'expected' => [
                        'level' => 120,

                        'experience' => 50,
                    ],

                    'next' => [
                        'level' => 121,

                        'experience' => 0,
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                false
            )
            ->assertJsonPath(
                'data.progression.level',
                121
            )
            ->assertJsonPath(
                'data.progression.experience',
                0
            );


        $this->assertDatabaseHas(
            'characters',
            [
                'id' => $this->character->id,

                'level' => 121,

                'experience' => 0,
            ]
        );
    }


    public function test_progression_regression_is_rejected(): void
    {
        $this->character->forceFill([
            'experience' => 50,
        ])->save();


        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->patchJson(
                $this->progressionUrl(),
                [
                    'expected' => [
                        'level' => 120,

                        'experience' => 50,
                    ],

                    'next' => [
                        'level' => 120,

                        'experience' => 25,
                    ],
                ]
            )
            ->assertStatus(
                422
            );


        $this->assertDatabaseHas(
            'characters',
            [
                'id' => $this->character->id,

                'level' => 120,

                'experience' => 50,
            ]
        );
    }


    private function gameServerHeaders(): array
    {
        return [
            'X-VHAL-Game-Server-Key'
                => 'test-internal-key',
        ];
    }


    private function progressionUrl(): string
    {
        return sprintf(
            (
                '/api/internal/accounts/%d'
                .'/characters/%d/progression'
            ),
            $this->account->id,
            $this->character->id
        );
    }
}