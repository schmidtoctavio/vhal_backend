<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Character;
use App\Models\CharacterRuntimeState;
use App\Models\GameSessionTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;


class InternalCharacterRuntimeStateTest extends TestCase
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


        // -------------------------------------------------
        // CLASE FOUNDATION
        // -------------------------------------------------

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


        // -------------------------------------------------
        // CUENTA
        // -------------------------------------------------

        $this->account = Account::query()
            ->create([
                'username' => (
                    'runtime_state_test'
                ),

                'email' => (
                    'runtime-state@test.local'
                ),

                'password' => 'secret',

                'status' => 'active',
            ]);


        // -------------------------------------------------
        // PERSONAJE
        // -------------------------------------------------

        $this->character = Character::query()
            ->create([
                'account_id' => (
                    $this->account->id
                ),

                'slot_index' => 0,

                'name' => 'RuntimeStateTest',

                'class_id' => 'warrior',

                'level' => 123,

                'experience' => 50,
            ]);
    }


    // =====================================================
    // PRIMER CHECKPOINT
    // =====================================================

    public function test_game_server_can_create_first_runtime_checkpoint(): void
    {
        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->putJson(
                $this->runtimeStateUrl(),
                $this->runtimePayload(
                    expectedRevision: 0,
                    mapId: 'test_town',
                    positionX: 4.5,
                    positionY: 0.0,
                    positionZ: 7.25,
                    rotationY: 1.5,
                    hp: 85000,
                    mp: 290
                )
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
                'data.runtime.revision',
                1
            )
            ->assertJsonPath(
                'data.runtime.world.map_id',
                'test_town'
            )
            ->assertJsonPath(
                'data.runtime.world.position.x',
                4.5
            )
            ->assertJsonPath(
                'data.runtime.world.position.y',
                0
            )
            ->assertJsonPath(
                'data.runtime.world.position.z',
                7.25
            )
            ->assertJsonPath(
                'data.runtime.world.rotation_y',
                1.5
            )
            ->assertJsonPath(
                'data.runtime.vitals.hp',
                85000
            )
            ->assertJsonPath(
                'data.runtime.vitals.mp',
                290
            );


        $this->assertDatabaseHas(
            'character_runtime_states',
            [
                'character_id' => (
                    $this->character->id
                ),

                'map_id' => 'test_town',

                'hp' => 85000,

                'mp' => 290,

                'revision' => 1,
            ]
        );
    }


    // =====================================================
    // IDEMPOTENCIA
    // =====================================================

    public function test_same_first_runtime_checkpoint_retry_is_idempotent(): void
    {
        $payload = $this->runtimePayload(
            expectedRevision: 0,
            mapId: 'test_town',
            positionX: 4.5,
            positionY: 0.0,
            positionZ: 7.25,
            rotationY: 1.5,
            hp: 85000,
            mp: 290
        );


        // -------------------------------------------------
        // PRIMER REQUEST
        // -------------------------------------------------

        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->putJson(
                $this->runtimeStateUrl(),
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                false
            )
            ->assertJsonPath(
                'data.runtime.revision',
                1
            );


        // -------------------------------------------------
        // RETRY EXACTO
        //
        // El Game Server sigue creyendo expected=0
        // porque puede haber perdido la respuesta anterior.
        //
        // Laravel ya posee revision 1.
        //
        // Como el estado es exactamente el mismo:
        //
        // → OK
        // → idempotent true
        // → NO crea revision 2
        // -------------------------------------------------

        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->putJson(
                $this->runtimeStateUrl(),
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                true
            )
            ->assertJsonPath(
                'data.runtime.revision',
                1
            )
            ->assertJsonPath(
                'data.runtime.vitals.hp',
                85000
            )
            ->assertJsonPath(
                'data.runtime.vitals.mp',
                290
            );


        $this->assertDatabaseHas(
            'character_runtime_states',
            [
                'character_id' => (
                    $this->character->id
                ),

                'revision' => 1,

                'hp' => 85000,

                'mp' => 290,
            ]
        );


        $this->assertSame(
            1,
            CharacterRuntimeState::query()
                ->where(
                    'character_id',
                    $this->character->id
                )
                ->count()
        );
    }


    // =====================================================
    // ACTUALIZAR CHECKPOINT
    // =====================================================

    public function test_game_server_can_update_runtime_checkpoint(): void
    {
        // -------------------------------------------------
        // REVISION 1
        // -------------------------------------------------

        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->putJson(
                $this->runtimeStateUrl(),
                $this->runtimePayload(
                    expectedRevision: 0,
                    mapId: 'test_town',
                    positionX: 4.5,
                    positionY: 0.0,
                    positionZ: 7.25,
                    rotationY: 1.5,
                    hp: 85000,
                    mp: 290
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.runtime.revision',
                1
            );


        // -------------------------------------------------
        // REVISION 2
        // -------------------------------------------------

        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->putJson(
                $this->runtimeStateUrl(),
                $this->runtimePayload(
                    expectedRevision: 1,
                    mapId: 'test_town',
                    positionX: 12.0,
                    positionY: 0.0,
                    positionZ: 15.5,
                    rotationY: 2.25,
                    hp: 72000,
                    mp: 210
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.idempotent',
                false
            )
            ->assertJsonPath(
                'data.runtime.revision',
                2
            )
            ->assertJsonPath(
                'data.runtime.world.position.x',
                12
            )
            ->assertJsonPath(
                'data.runtime.world.position.z',
                15.5
            )
            ->assertJsonPath(
                'data.runtime.world.rotation_y',
                2.25
            )
            ->assertJsonPath(
                'data.runtime.vitals.hp',
                72000
            )
            ->assertJsonPath(
                'data.runtime.vitals.mp',
                210
            );


        $this->assertDatabaseHas(
            'character_runtime_states',
            [
                'character_id' => (
                    $this->character->id
                ),

                'map_id' => 'test_town',

                'revision' => 2,

                'hp' => 72000,

                'mp' => 210,
            ]
        );
    }


    // =====================================================
    // STALE REVISION
    // =====================================================

    public function test_stale_runtime_checkpoint_is_rejected(): void
    {
        // -------------------------------------------------
        // REVISION 1
        // -------------------------------------------------

        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->putJson(
                $this->runtimeStateUrl(),
                $this->runtimePayload(
                    expectedRevision: 0,
                    mapId: 'test_town',
                    positionX: 4.0,
                    positionY: 0.0,
                    positionZ: 4.0,
                    rotationY: 0.5,
                    hp: 90000,
                    mp: 300
                )
            )
            ->assertOk();


        // -------------------------------------------------
        // REVISION 2
        // -------------------------------------------------

        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->putJson(
                $this->runtimeStateUrl(),
                $this->runtimePayload(
                    expectedRevision: 1,
                    mapId: 'test_town',
                    positionX: 8.0,
                    positionY: 0.0,
                    positionZ: 8.0,
                    rotationY: 1.0,
                    hp: 80000,
                    mp: 250
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.runtime.revision',
                2
            );


        // -------------------------------------------------
        // REQUEST STALE
        //
        // Este request todavía cree que la revisión
        // actual es 1.
        //
        // Pero Laravel ya está en revision 2.
        //
        // Además intenta escribir OTRO estado.
        //
        // Debe rechazarse.
        // -------------------------------------------------

        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->putJson(
                $this->runtimeStateUrl(),
                $this->runtimePayload(
                    expectedRevision: 1,
                    mapId: 'test_town',
                    positionX: 20.0,
                    positionY: 0.0,
                    positionZ: 20.0,
                    rotationY: 2.0,
                    hp: 60000,
                    mp: 150
                )
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'ok',
                false
            )
            ->assertJsonPath(
                'data.expected_revision',
                1
            )
            ->assertJsonPath(
                'data.current.revision',
                2
            )
            ->assertJsonPath(
                'data.current.world.map_id',
                'test_town'
            )
            ->assertJsonPath(
                'data.current.world.position.x',
                8
            )
            ->assertJsonPath(
                'data.current.world.position.z',
                8
            )
            ->assertJsonPath(
                'data.current.vitals.hp',
                80000
            )
            ->assertJsonPath(
                'data.current.vitals.mp',
                250
            );


        // -------------------------------------------------
        // VERIFICAR QUE EL STALE NO MUTÓ LA DB
        // -------------------------------------------------

        $this->assertDatabaseHas(
            'character_runtime_states',
            [
                'character_id' => (
                    $this->character->id
                ),

                'revision' => 2,

                'hp' => 80000,

                'mp' => 250,
            ]
        );
    }


    // =====================================================
    // TICKET + BOOTSTRAP
    // =====================================================

    public function test_consumed_game_session_ticket_includes_progression_and_runtime_checkpoint(): void
    {
        // -------------------------------------------------
        // ESTADO RUNTIME DURABLE
        // -------------------------------------------------

        CharacterRuntimeState::query()
            ->create([
                'character_id' => (
                    $this->character->id
                ),

                'map_id' => 'test_town',

                'position_x' => 13.5,

                'position_y' => 0.0,

                'position_z' => 9.25,

                'rotation_y' => 1.75,

                'hp' => 64000,

                'mp' => 175,

                'revision' => 7,
            ]);


        // -------------------------------------------------
        // TICKET REAL
        // -------------------------------------------------

        $plainTicket = str_repeat(
            'a',
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


        // -------------------------------------------------
        // CONSUMIR TICKET
        // -------------------------------------------------

        $this
            ->withHeaders(
                $this->gameServerHeaders()
            )
            ->postJson(
                '/api/internal/game-session/tickets/consume',
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
                'data.account_id',
                $this->account->id
            )
            ->assertJsonPath(
                'data.character.id',
                $this->character->id
            )
            ->assertJsonPath(
                'data.character.name',
                'RuntimeStateTest'
            )
            ->assertJsonPath(
                'data.character.class_id',
                'warrior'
            )

            // ---------------------------------------------
            // PROGRESIÓN DURABLE YA EXISTENTE
            // ---------------------------------------------

            ->assertJsonPath(
                'data.character.level',
                123
            )
            ->assertJsonPath(
                'data.character.experience',
                50
            )

            // ---------------------------------------------
            // RUNTIME DURABLE NUEVO
            // ---------------------------------------------

            ->assertJsonPath(
                'data.character.runtime.revision',
                7
            )
            ->assertJsonPath(
                'data.character.runtime.world.map_id',
                'test_town'
            )
            ->assertJsonPath(
                'data.character.runtime.world.position.x',
                13.5
            )
            ->assertJsonPath(
                'data.character.runtime.world.position.y',
                0
            )
            ->assertJsonPath(
                'data.character.runtime.world.position.z',
                9.25
            )
            ->assertJsonPath(
                'data.character.runtime.world.rotation_y',
                1.75
            )
            ->assertJsonPath(
                'data.character.runtime.vitals.hp',
                64000
            )
            ->assertJsonPath(
                'data.character.runtime.vitals.mp',
                175
            );


        // -------------------------------------------------
        // EL TICKET DEBE QUEDAR CONSUMIDO
        // -------------------------------------------------

        $this->assertNotNull(
            GameSessionTicket::query()
                ->where(
                    'token_hash',
                    hash(
                        'sha256',
                        $plainTicket
                    )
                )
                ->value(
                    'consumed_at'
                )
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

    private function runtimeStateUrl(): string
    {
        return sprintf(
            (
                '/api/internal/accounts/%d'
                .'/characters/%d/runtime-state'
            ),
            $this->account->id,
            $this->character->id
        );
    }


    // =====================================================
    // PAYLOAD
    // =====================================================

    private function runtimePayload(
        int $expectedRevision,
        string $mapId,
        float $positionX,
        float $positionY,
        float $positionZ,
        float $rotationY,
        int $hp,
        int $mp
    ): array {
        return [
            'expected_revision' => (
                $expectedRevision
            ),

            'state' => [
                'world' => [
                    'map_id' => $mapId,

                    'position' => [
                        'x' => $positionX,

                        'y' => $positionY,

                        'z' => $positionZ,
                    ],

                    'rotation_y' => (
                        $rotationY
                    ),
                ],

                'vitals' => [
                    'hp' => $hp,

                    'mp' => $mp,
                ],
            ],
        ];
    }
}