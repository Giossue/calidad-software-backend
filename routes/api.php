<?php

use App\Http\Controllers\Api\V1\Admin\CareerController;
use App\Http\Controllers\Api\V1\Admin\CycleController;
use App\Http\Controllers\Api\V1\Admin\FacultyController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
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

    Route::middleware(['auth:sanctum', 'verified'])
        ->prefix('admin')
        ->name('admin.')
        ->group(function (): void {
            Route::get('faculties', [FacultyController::class, 'index'])->name('faculties.index');
            Route::get('careers', [CareerController::class, 'index'])->name('careers.index');
            Route::post('careers', [CareerController::class, 'store'])->name('careers.store');
            Route::get('cycles', [CycleController::class, 'index'])->name('cycles.index');
            Route::patch('careers/{career}', [CareerController::class, 'update'])->name('careers.update');
            Route::patch('careers/{career}/deactivate', [CareerController::class, 'deactivate'])
                ->middleware('can:deactivate,career')
                ->name('careers.deactivate');

            Route::post('cycles', [CycleController::class, 'store'])->name('cycles.store');
            Route::patch('cycles/{cycle}', [CycleController::class, 'update'])->name('cycles.update');
            Route::patch('cycles/{cycle}/deactivate', [CycleController::class, 'deactivate'])
                ->middleware('can:deactivate,cycle')
                ->name('cycles.deactivate');
        });
});
