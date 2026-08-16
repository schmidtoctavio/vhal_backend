<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CharacterController;
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

});