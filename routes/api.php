<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Middleware\EnsureProjectAccessMatchesToken;
use App\Http\Middleware\LogProjectApiRequest;
use App\Http\Middleware\ResolveProjectFromApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->as('api.v1.')
    ->group(function (): void {
        Route::prefix('auth')
            ->as('auth.')
            ->middleware([
                ResolveProjectFromApiKey::class,
                LogProjectApiRequest::class,
                'throttle:project-auth',
            ])
            ->group(function (): void {
                Route::post('register', [AuthController::class, 'register'])->name('register');
                Route::post('login', [AuthController::class, 'login'])->name('login');

                Route::middleware([
                    'auth:sanctum',
                    EnsureProjectAccessMatchesToken::class,
                ])->group(function (): void {
                    Route::get('me', [AuthController::class, 'me'])->name('me');
                    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
                });
            });
    });
