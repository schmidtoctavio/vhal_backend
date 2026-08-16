<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(
        Request $request
    ): JsonResponse {
        $credentials = $request->validate([
            'account' => [
                'required',
                'string',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $identifier = trim(
            $credentials['account']
        );

        $account = Account::query()
            ->where(
                'username',
                $identifier
            )
            ->orWhere(
                'email',
                $identifier
            )
            ->first();

        if (
            $account === null
            ||
            ! Hash::check(
                $credentials['password'],
                $account->password
            )
        ) {
            return response()->json([
                'ok' => false,
                'message' => 'Cuenta o contraseña incorrectos.',
            ], 401);
        }

        if ($account->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'La cuenta no está habilitada.',
            ], 403);
        }

        $account->forceFill([
            'last_login_at' => now(),
        ])->save();

        $expiresAt = now()->addHours(12);

        $token = $account->createToken(
            'godot-client',
            ['*'],
            $expiresAt
        );

        return response()->json([
            'ok' => true,

            'data' => [
                'account' => [
                    'id' => $account->id,
                    'username' => $account->username,
                ],

                'access_token' => (
                    $token->plainTextToken
                ),

                'token_type' => 'Bearer',

                'expires_at' => (
                    $expiresAt->toIso8601String()
                ),
            ],
        ]);
    }


    public function me(
        Request $request
    ): JsonResponse {
        /** @var Account $account */
        $account = $request->user();

        return response()->json([
            'ok' => true,

            'data' => [
                'account' => [
                    'id' => $account->id,
                    'username' => $account->username,
                ],
            ],
        ]);
    }


    public function logout(
        Request $request
    ): JsonResponse {
        $token = (
            $request
                ->user()
                ->currentAccessToken()
        );

        if ($token !== null) {
            $token->delete();
        }

        return response()->json([
            'ok' => true,
        ]);
    }
}