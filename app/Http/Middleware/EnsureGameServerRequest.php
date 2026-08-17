<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureGameServerRequest
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $expectedKey = (string) config(
            'services.game_server.internal_key',
            ''
        );


        $providedKey = (string) $request->header(
            'X-VHAL-Game-Server-Key',
            ''
        );


        if (
            $expectedKey === ''
            ||
            $providedKey === ''
            ||
            ! hash_equals(
                $expectedKey,
                $providedKey
            )
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Game Server no autorizado.',
            ], 401);
        }


        return $next(
            $request
        );
    }
}