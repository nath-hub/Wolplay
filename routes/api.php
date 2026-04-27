<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ── Guest routes ───────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {

    // Inscription
    Route::post('/register', [RegisterController::class, 'store']);

    // Connexion
    Route::post('/login', [LoginController::class, 'store']);

    // Mot de passe oublié
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'passwordReset'])->name('password.reset');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'notice'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

});
// ── Authenticated routes ───────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Déconnexion
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout'); 
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');

    // Profil (requiert email vérifié)
    Route::middleware('verified')->group(function () {
        Route::get('/profile', [ProfileController::class, 'getUserWithToken'])->name('profile.show');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/profile/pseudo', [ProfileController::class, 'updatePseudo'])->name('profile.pseudo');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

