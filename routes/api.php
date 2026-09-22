<?php

use App\Http\Controllers\Api\V1\AcademicPeriodController;
use App\Http\Controllers\Api\V1\Admin\CareerController;
use App\Http\Controllers\Api\V1\Admin\CycleController;
use App\Http\Controllers\Api\V1\Admin\FacultyController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Api\V1\ModalityController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [RegistrationController::class, 'store'])->name('register');
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

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::prefix('users')->name('users.')->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('/', [UserController::class, 'store'])->name('store');
            Route::patch('{user}', [UserController::class, 'update'])->name('update');
            Route::patch('{user}/deactivate', [UserController::class, 'deactivate'])->name('deactivate');
            Route::patch('{user}/activate', [UserController::class, 'activate'])->name('activate');
        });

        Route::prefix('faculties')->name('faculties.')->group(function (): void {
            Route::get('/', [FacultyController::class, 'index'])->name('index');
            Route::post('/', [FacultyController::class, 'store'])->name('store');
            Route::patch('{faculty}', [FacultyController::class, 'update'])->name('update');
            Route::patch('{faculty}/deactivate', [FacultyController::class, 'deactivate'])->name('deactivate');
            Route::patch('{faculty}/activate', [FacultyController::class, 'activate'])->name('activate');
        });
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('academic-periods', [AcademicPeriodController::class, 'index'])->name('academic-periods.index.legacy');
        Route::post('academic-periods', [AcademicPeriodController::class, 'store'])->name('academic-periods.store.legacy');
        Route::patch('academic-periods/{academicPeriod}', [AcademicPeriodController::class, 'update'])->name('academic-periods.update.legacy');
        Route::patch('academic-periods/{academicPeriod}/deactivate', [AcademicPeriodController::class, 'deactivate'])
            ->middleware('can:deactivate,academicPeriod')->name('academic-periods.deactivate.legacy');
        Route::patch('academic-periods/{academicPeriod}/activate', [AcademicPeriodController::class, 'activate'])
            ->middleware('can:activate,academicPeriod')->name('academic-periods.activate.legacy');
        Route::get('modalities', [ModalityController::class, 'index'])->name('modalities.index.legacy');
        Route::post('modalities', [ModalityController::class, 'store'])->name('modalities.store.legacy');
        Route::patch('modalities/{modality}', [ModalityController::class, 'update'])->name('modalities.update.legacy');
        Route::patch('modalities/{modality}/deactivate', [ModalityController::class, 'deactivate'])
            ->middleware('can:deactivate,modality')->name('modalities.deactivate.legacy');
        Route::patch('modalities/{modality}/activate', [ModalityController::class, 'activate'])
            ->middleware('can:activate,modality')->name('modalities.activate.legacy');
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
                ->middleware('can:deactivate,career')->name('careers.deactivate');
            Route::patch('careers/{career}/activate', [CareerController::class, 'activate'])
                ->middleware('can:activate,career')->name('careers.activate');
            Route::post('cycles', [CycleController::class, 'store'])->name('cycles.store');
            Route::patch('cycles/{cycle}', [CycleController::class, 'update'])->name('cycles.update');
            Route::patch('cycles/{cycle}/deactivate', [CycleController::class, 'deactivate'])
                ->middleware('can:deactivate,cycle')->name('cycles.deactivate');
            Route::patch('cycles/{cycle}/activate', [CycleController::class, 'activate'])
                ->middleware('can:activate,cycle')->name('cycles.activate');
            Route::get('academic-periods', [AcademicPeriodController::class, 'index'])->name('academic-periods.index');
            Route::post('academic-periods', [AcademicPeriodController::class, 'store'])->name('academic-periods.store');
            Route::patch('academic-periods/{academicPeriod}', [AcademicPeriodController::class, 'update'])->name('academic-periods.update');
            Route::patch('academic-periods/{academicPeriod}/deactivate', [AcademicPeriodController::class, 'deactivate'])
                ->middleware('can:deactivate,academicPeriod')->name('academic-periods.deactivate');
            Route::patch('academic-periods/{academicPeriod}/activate', [AcademicPeriodController::class, 'activate'])
                ->middleware('can:activate,academicPeriod')->name('academic-periods.activate');
            Route::get('modalities', [ModalityController::class, 'index'])->name('modalities.index');
            Route::post('modalities', [ModalityController::class, 'store'])->name('modalities.store');
            Route::patch('modalities/{modality}', [ModalityController::class, 'update'])->name('modalities.update');
            Route::patch('modalities/{modality}/deactivate', [ModalityController::class, 'deactivate'])
                ->middleware('can:deactivate,modality')->name('modalities.deactivate');
            Route::patch('modalities/{modality}/activate', [ModalityController::class, 'activate'])
                ->middleware('can:activate,modality')->name('modalities.activate');
        });
});
