<?php

use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [RegistrationController::class, 'store'])
            ->middleware('throttle:5,1')->name('register');
        Route::post('login', [TokenController::class, 'store'])
            ->middleware('throttle:5,1')->name('login');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
            ->middleware(['auth:sanctum', 'throttle:5,1'])->name('two-factor.challenge');
        Route::post('forgot-password', [PasswordController::class, 'forgot'])
            ->middleware('throttle:5,1')->name('password.forgot');
        Route::post('reset-password', [PasswordController::class, 'reset'])
            ->middleware('throttle:5,1')->name('password.reset');
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('user', [TokenController::class, 'show'])->name('user');
            Route::delete('logout', [TokenController::class, 'destroy'])->name('logout');
            Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
                ->middleware('throttle:6,1')->name('verification.send');
        });
    });
});
