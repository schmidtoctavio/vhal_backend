<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CharacterController;
use App\Http\Controllers\Api\GameSessionTicketController;
use App\Http\Controllers\Api\InternalCharacterEquipmentController;
use App\Http\Controllers\Api\InternalCharacterInventoryController;
use App\Http\Controllers\Api\InternalGameSessionTicketController;
use App\Http\Controllers\Api\InternalItemTransferController;
use App\Http\Controllers\Api\InternalVaultController;
use App\Http\Controllers\Api\InternalCharacterProgressionController;
use App\Http\Controllers\Api\InternalCharacterRuntimeStateController;
use App\Http\Controllers\Api\InternalCharacterSkillController;
use App\Http\Controllers\Api\InternalCharacterSkillLearningController;
use App\Http\Controllers\Api\InternalCharacterStatsController;
use Illuminate\Support\Facades\Route;


// =========================================================
// AUTENTICACIÓN
// =========================================================

Route::prefix('auth')->group(function (): void {

    Route::post(
        '/login',
        [
            AuthController::class,
            'login',
        ]
    );


    Route::middleware(
        'auth:sanctum'
    )->group(function (): void {

        Route::get(
            '/me',
            [
                AuthController::class,
                'me',
            ]
        );


        Route::post(
            '/logout',
            [
                AuthController::class,
                'logout',
            ]
        );

    });

});


// =========================================================
// PERSONAJES
// =========================================================

Route::middleware(
    'auth:sanctum'
)->group(function (): void {

    Route::get(
        '/characters',
        [
            CharacterController::class,
            'index',
        ]
    );


    Route::post(
        '/characters',
        [
            CharacterController::class,
            'store',
        ]
    );


    Route::delete(
        '/characters/{characterId}',
        [
            CharacterController::class,
            'destroy',
        ]
    )->whereNumber(
        'characterId'
    );


    // =====================================================
    // GAME SESSION
    // =====================================================

    Route::post(
        '/game-session/tickets',
        [
            GameSessionTicketController::class,
            'store',
        ]
    );

});


// =========================================================
// GAME SERVER INTERNO
// =========================================================

Route::middleware(
    'game-server'
)->group(function (): void {

    Route::post(
        '/internal/game-session/tickets/consume',
        [
            InternalGameSessionTicketController::class,
            'consume',
        ]
    );

    // =========================================================
    // RUNTIME PERSISTENTE DEL PERSONAJE
    // =========================================================

    Route::put(
        '/internal/accounts/{accountId}/characters/{characterId}/runtime-state',
        [
            InternalCharacterRuntimeStateController::class,
            'update',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    );

    // =====================================================
    // SKILLS PERSISTENTES DEL PERSONAJE
    // =====================================================

    Route::post(
        '/internal/accounts/{accountId}/characters/{characterId}/skills',
        [
            InternalCharacterSkillController::class,
            'store',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    );

    Route::post(
        '/internal/accounts/{accountId}/characters/{characterId}/skills/learn',
        [
            InternalCharacterSkillLearningController::class,
            'store',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    );

    // =====================================================
    // VAULT / WAREHOUSE
    // =====================================================

    Route::get(
        '/internal/accounts/{accountId}/vault',
        [
            InternalVaultController::class,
            'show',
        ]
    )->whereNumber(
        'accountId'
    );


    Route::patch(
        '/internal/accounts/{accountId}/vault/items/{uid}/position',
        [
            InternalVaultController::class,
            'moveItem',
        ]
    )->whereNumber(
        'accountId'
    )->whereUuid(
        'uid'
    );

    // =========================================================
    // PROGRESIÓN PERSISTENTE DEL PERSONAJE
    // =========================================================

    Route::patch(
        '/internal/accounts/{accountId}/characters/{characterId}/progression',
        [
            InternalCharacterProgressionController::class,
            'update',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    );

    // =========================================================
    // STATS PERSISTENTES DEL PERSONAJE
    // =========================================================

    Route::get(
        '/internal/accounts/{accountId}/characters/{characterId}/stats',
        [
            InternalCharacterStatsController::class,
            'show',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    );

    // =====================================================
    // INVENTARIO PERSISTENTE DEL PERSONAJE
    // =====================================================

    Route::get(
        '/internal/accounts/{accountId}/characters/{characterId}/inventory',
        [
            InternalCharacterInventoryController::class,
            'show',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    );


    Route::patch(
        '/internal/accounts/{accountId}/characters/{characterId}/inventory/items/{uid}/position',
        [
            InternalCharacterInventoryController::class,
            'moveItem',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    )->whereUuid(
        'uid'
    );

    Route::post(
        '/internal/accounts/{accountId}/characters/{characterId}/inventory/items',
        [
            InternalCharacterInventoryController::class,
            'storeItem',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    );

    // =====================================================
    // EQUIPMENT PERSISTENTE DEL PERSONAJE
    // =====================================================

    Route::get(
        '/internal/accounts/{accountId}/characters/{characterId}/equipment',
        [
            InternalCharacterEquipmentController::class,
            'show',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    );


    Route::patch(
        '/internal/accounts/{accountId}/characters/{characterId}/equipment/items/{uid}/equip',
        [
            InternalCharacterEquipmentController::class,
            'equipItem',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    )->whereUuid(
        'uid'
    );


    Route::patch(
        '/internal/accounts/{accountId}/characters/{characterId}/equipment/items/{uid}/unequip',
        [
            InternalCharacterEquipmentController::class,
            'unequipItem',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    )->whereUuid(
        'uid'
    );


    // =====================================================
    // TRANSFERENCIAS INVENTORY <-> VAULT
    // =====================================================

    Route::patch(
        '/internal/accounts/{accountId}/characters/{characterId}/items/{uid}/transfer',
        [
            InternalItemTransferController::class,
            'transfer',
        ]
    )->whereNumber(
        'accountId'
    )->whereNumber(
        'characterId'
    )->whereUuid(
        'uid'
    );

});