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
                Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
                Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
                Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
                Route::post('send-otp', [AuthController::class, 'sendOtp'])->name('send-otp');
                Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('verify-otp');
                Route::post('resend-otp', [AuthController::class, 'resendOtp'])->name('resend-otp');
                Route::post('ghost-accounts', [AuthController::class, 'storeGhostAccount'])->name('ghost-accounts.store');
                Route::post('ghost-accounts/claim', [AuthController::class, 'claimGhostAccount'])->name('ghost-accounts.claim');

                Route::middleware([
                    'auth:sanctum',
                    EnsureProjectAccessMatchesToken::class,
                ])->group(function (): void {
                    Route::get('me', [AuthController::class, 'me'])->name('me');
                    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
                });
            });
    });
