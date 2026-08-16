<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        /** @var Account $account */
        $account = $request->user();


        $characters = $account
            ->characters()
            ->orderBy('slot_index')
            ->get()
            ->map(
                fn ($character): array => [
                    'id' => $character->id,
                    'slot_index' => $character->slot_index,
                    'name' => $character->name,
                    'class_id' => $character->class_id,
                    'level' => $character->level,
                ]
            )
            ->values();


        return response()->json([
            'ok' => true,

            'data' => [
                'characters' => $characters,
            ],
        ]);
    }
}