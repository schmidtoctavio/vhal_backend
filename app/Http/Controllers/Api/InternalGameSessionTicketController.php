<?php

namespace App\Http\Controllers\Api;

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
        Request $request
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
                $ticketHash
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
                // PERSONAJE + RUNTIME DURABLE
                // -----------------------------------------
                //
                // El runtime puede ser NULL.
                //
                // Eso significa que el personaje todavía
                // nunca generó un checkpoint persistente.
                //
                // En ese caso será el Game Server quien
                // use spawn y Vitals foundation.
                // -----------------------------------------

                $character = Character::query()
                    ->with(
                        'runtimeState'
                    )
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


                $runtimeState = (
                    $character->runtimeState
                );


                // -----------------------------------------
                // A partir de este momento el ticket queda
                // definitivamente consumido.
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

                default => 401,
            };


            return response()->json([
                'ok' => false,

                'message' => match ($reason) {
                    'expired' => (
                        'El ticket expiró.'
                    ),

                    'consumed' => (
                        'El ticket ya fue utilizado.'
                    ),

                    'account_disabled' => (
                        'La cuenta no está habilitada.'
                    ),

                    default => (
                        'Ticket inválido.'
                    ),
                },
            ], $status);
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