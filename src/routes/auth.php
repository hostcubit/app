<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\GoogleAuthenticatedController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::middleware(['guest','maintenance'])->group(function () {

    Route::controller(RegisteredUserController::class)->middleware('registration.allow')->group(function() {

        Route::get('register', 'create')->name('register');
        Route::post('register', 'store')->name('register.store');
    });

    Route::middleware('login.allow')->group(function() {

        Route::controller(AuthenticatedSessionController::class)->group(function() {
            
            Route::get('/login', 'create')->name('login');
            Route::post('login', 'store')->name('login.store');
        });
        Route::controller(PasswordResetLinkController::class)->name('password.')->group(function() {

            Route::get('forgot-password', 'create')->name('request');
            Route::post('forgot-password', 'store')->name('email');
            Route::get('password/code/verify', 'passwordResetCodeVerify')->name('verify.code');
            Route::post('password/code/verify', 'emailVerificationCode')->name('email.verify.code');
            Route::get('password/resend/code', 'resendCode')->name('resend.code');
        });
        Route::controller(NewPasswordController::class)->group(function() {

            Route::get('reset-password/{token}', 'create')->name('password.reset');
            Route::post('reset-password', 'store')->name('password.update');
        });
    });
});

Route::middleware('auth','maintenance')->group(function () {
    
    Route::get('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::controller(GoogleAuthenticatedController::class)->group(function() {

    Route::get('auth/google', 'redirectToGoogle');
    Route::get('auth/google/callback', 'handleGoogleCallback');
});
