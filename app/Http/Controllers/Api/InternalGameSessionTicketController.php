<?php

namespace App\Http\Controllers\Api;

use App\Application\Stats\CharacterStatSnapshotBuilder;
use App\Application\Stats\CharacterStatSnapshotException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Character;
use App\Models\GameSessionTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class InternalGameSessionTicketController extends Controller
{
    public function consume(
        Request $request,
        CharacterStatSnapshotBuilder $statSnapshotBuilder
    ): JsonResponse {
        $data = $request->validate([
            'ticket' => [
                'required',
                'string',
                'size:64',
            ],
        ]);


        $ticketHash = hash(
            'sha256',
            $data['ticket']
        );


        $result = DB::transaction(
            function () use (
                $ticketHash,
                $statSnapshotBuilder
            ): array {
                // -----------------------------------------
                // Bloqueamos la fila durante el consumo.
                //
                // Así dos requests simultáneos no pueden
                // consumir correctamente el mismo ticket.
                // -----------------------------------------

                $ticket = GameSessionTicket::query()
                    ->where(
                        'token_hash',
                        $ticketHash
                    )
                    ->lockForUpdate()
                    ->first();


                if ($ticket === null) {
                    return [
                        'ok' => false,
                        'reason' => 'invalid',
                    ];
                }


                if ($ticket->consumed_at !== null) {
                    return [
                        'ok' => false,
                        'reason' => 'consumed',
                    ];
                }


                if ($ticket->expires_at->isPast()) {
                    return [
                        'ok' => false,
                        'reason' => 'expired',
                    ];
                }


                $account = Account::query()
                    ->whereKey(
                        $ticket->account_id
                    )
                    ->first();


                if ($account === null) {
                    return [
                        'ok' => false,
                        'reason' => 'invalid',
                    ];
                }


                if ($account->status !== 'active') {
                    return [
                        'ok' => false,
                        'reason' => 'account_disabled',
                    ];
                }


                // -----------------------------------------
                // PERSONAJE + ESTADOS DURABLES
                // -----------------------------------------

                $character = Character::query()
                    ->with([
                        'runtimeState',
                        'skills',
                        'statAllocation',
                    ])
                    ->whereKey(
                        $ticket->character_id
                    )
                    ->where(
                        'account_id',
                        $account->id
                    )
                    ->first();


                if ($character === null) {
                    return [
                        'ok' => false,
                        'reason' => 'invalid',
                    ];
                }


                // -----------------------------------------
                // STATS DURABLES / BUDGET
                // -----------------------------------------
                //
                // Deben validarse ANTES de consumir el
                // ticket.
                //
                // Si el estado durable es inconsistente,
                // la sesión no puede arrancar.
                // -----------------------------------------

                try {
                    $statSnapshot = (
                        $statSnapshotBuilder->build(
                            $character
                        )
                    );
                } catch (
                    CharacterStatSnapshotException $exception
                ) {
                    return [
                        'ok' => false,

                        'reason' => 'invalid_character_stats',

                        'message' => (
                            $exception->getMessage()
                        ),

                        'context' => (
                            $exception->context()
                        ),
                    ];
                }


                $runtimeState = (
                    $character->runtimeState
                );


                $learnedSkillIds = (
                    $character
                        ->skills
                        ->pluck(
                            'skill_id'
                        )
                        ->map(
                            static fn (
                                string $skillId
                            ): string => strtolower(
                                trim(
                                    $skillId
                                )
                            )
                        )
                        ->sort()
                        ->values()
                        ->all()
                );


                // -----------------------------------------
                // A partir de este momento el ticket queda
                // definitivamente consumido.
                //
                // Llegar aquí significa que identidad,
                // progression, Stats y demás durable state
                // necesarios para bootstrap son válidos.
                // -----------------------------------------

                $ticket->forceFill([
                    'consumed_at' => now(),
                ])->save();


                return [
                    'ok' => true,

                    'account_id' => $account->id,

                    'character' => [
                        'id' => $character->id,

                        'slot_index' => (
                            $character->slot_index
                        ),

                        'name' => $character->name,

                        'class_id' => (
                            $character->class_id
                        ),


                        // ---------------------------------
                        // PROGRESIÓN DURABLE
                        // ---------------------------------

                        'level' => (
                            $character->level
                        ),

                        'experience' => (
                            $character->experience
                        ),

                        'reset_count' => (
                            $character->reset_count
                        ),


                        // ---------------------------------
                        // PRIMARY STATS DURABLES
                        // ---------------------------------

                        'stats' => (
                            $statSnapshot
                        ),


                        // ---------------------------------
                        // SKILL OWNERSHIP DURABLE
                        // ---------------------------------

                        'skills' => [
                            'learned_skill_ids' => (
                                $learnedSkillIds
                            ),
                        ],


                        // ---------------------------------
                        // RUNTIME DURABLE
                        // ---------------------------------

                        'runtime' => (
                            $runtimeState === null
                            ?
                            null
                            :
                            [
                                'revision' => (
                                    (int) $runtimeState
                                        ->revision
                                ),

                                'world' => [
                                    'map_id' => (
                                        $runtimeState
                                            ->map_id
                                    ),

                                    'position' => [
                                        'x' => (
                                            (float) $runtimeState
                                                ->position_x
                                        ),

                                        'y' => (
                                            (float) $runtimeState
                                                ->position_y
                                        ),

                                        'z' => (
                                            (float) $runtimeState
                                                ->position_z
                                        ),
                                    ],

                                    'rotation_y' => (
                                        (float) $runtimeState
                                            ->rotation_y
                                    ),
                                ],

                                'vitals' => [
                                    'hp' => (
                                        (int) $runtimeState
                                            ->hp
                                    ),

                                    'mp' => (
                                        (int) $runtimeState
                                            ->mp
                                    ),
                                ],
                            ]
                        ),
                    ],
                ];
            }
        );


        if (! $result['ok']) {
            $reason = $result[
                'reason'
            ];


            $status = match ($reason) {
                'expired' => 410,

                'consumed' => 409,

                'account_disabled' => 403,

                'invalid_character_stats' => 409,

                default => 401,
            };


            $message = match ($reason) {
                'expired' => (
                    'El ticket expiró.'
                ),

                'consumed' => (
                    'El ticket ya fue utilizado.'
                ),

                'account_disabled' => (
                    'La cuenta no está habilitada.'
                ),

                'invalid_character_stats' => (
                    $result['message']
                ),

                default => (
                    'Ticket inválido.'
                ),
            };


            $response = [
                'ok' => false,

                'message' => $message,
            ];


            if (
                $reason === 'invalid_character_stats'
            ) {
                $response['data'] = (
                    $result['context']
                );
            }


            return response()->json(
                $response,
                $status
            );
        }


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => (
                    $result['account_id']
                ),

                'character' => (
                    $result['character']
                ),
            ],
        ]);
    }
}