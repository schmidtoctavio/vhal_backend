<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Http\Requests\Api\StoreCharacterRequest;

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

    public function store(
        StoreCharacterRequest $request
    ): JsonResponse {
        /** @var Account $account */
        $account = $request->user();


        $data = $request->validated();


        $slotOccupied = $account
            ->characters()
            ->where(
                'slot_index',
                $data['slot_index']
            )
            ->exists();


        if ($slotOccupied) {
            return response()->json([
                'ok' => false,
                'message' => 'Ese slot ya está ocupado.',
            ], 409);
        }


        $character = $account
            ->characters()
            ->create([
                'slot_index' => $data['slot_index'],
                'name' => $data['name'],
                'class_id' => $data['class_id'],
                'level' => 1,
            ]);


        return response()->json([
            'ok' => true,

            'data' => [
                'character' => [
                    'id' => $character->id,
                    'slot_index' => $character->slot_index,
                    'name' => $character->name,
                    'class_id' => $character->class_id,
                    'level' => $character->level,
                ],
            ],
        ], 201);
    }
}