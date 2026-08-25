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


                $character = Character::query()
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
                        'slot_index' => $character->slot_index,
                        'name' => $character->name,
                        'class_id' => $character->class_id,
                        'level' => $character->level,
                        'experience' => $character->experience,
                    ],
                ];
            }
        );


        if (! $result['ok']) {
            $reason = $result['reason'];


            $status = match ($reason) {
                'expired' => 410,
                'consumed' => 409,
                'account_disabled' => 403,
                default => 401,
            };


            return response()->json([
                'ok' => false,
                'message' => match ($reason) {
                    'expired' => 'El ticket expiró.',
                    'consumed' => 'El ticket ya fue utilizado.',
                    'account_disabled' => 'La cuenta no está habilitada.',
                    default => 'Ticket inválido.',
                },
            ], $status);
        }


        return response()->json([
            'ok' => true,

            'data' => [
                'account_id' => $result['account_id'],
                'character' => $result['character'],
            ],
        ]);
    }
}