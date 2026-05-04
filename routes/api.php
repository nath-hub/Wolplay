<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\FollowsController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\VideosController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// ── Guest routes ───────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {

    // Inscription
    Route::post('/register', [RegisterController::class, 'store']);

    // Connexion
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/auth/check-pseudo', [ProfileController::class, 'checkPseudo'])->name('profile.check-pseudo');
    Route::get('/auth/check-email', [ProfileController::class, 'checkEmail'])->name('profile.check-email');

    // Mot de passe oublié
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'passwordReset'])->name('password.reset');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'notice'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::get('/public/profile/{pseudo}', [EmailVerificationController::class, 'getByPseudo']);

    Route::get('/users/{id}', [EmailVerificationController::class, 'getById'])->name('profile.show-by-id');
});
// ── Authenticated routes ───────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Déconnexion
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware(['auth:sanctum', 'throttle:6,1'])
        ->name('verification.send');

    Route::post('/auth/update-email', [EmailVerificationController::class, 'updateEmail']);

    Route::get('/auth/confirm-email-change', [EmailVerificationController::class, 'confirmEmailChange'])
        ->name('confirm.email.change')
        ->middleware('signed');

    // Profil (requiert email vérifié)
    Route::middleware('verified')->group(function () {
        Route::get('/users', [ProfileController::class, 'getUserWithToken'])->name('profile.show');
        Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/auth/update-password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::put('/profile/pseudo', [ProfileController::class, 'updatePseudo'])->name('profile.pseudo');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index']);
Route::post('/subscription-plans', [SubscriptionPlanController::class, 'store']);
Route::get('/subscription-plans/{id}', [SubscriptionPlanController::class, 'show']);
Route::put('/subscription-plans/{id}', [SubscriptionPlanController::class, 'update']);
Route::delete('/subscription-plans/{id}', [SubscriptionPlanController::class, 'destroy']);


Route::middleware('auth:sanctum')->group(function () {

    // Follow & Recommandations
    Route::post('/creators/{creatorId}/follow',   [FollowsController::class, 'follow']);
    Route::delete('/creators/{creatorId}/follow', [FollowsController::class, 'unfollow']);
    Route::get('/creators/recommended',           [FollowsController::class, 'recommended']);

    // Abonnements
    Route::post('/subscription/upgrade', [SubscriptionPlanController::class, 'upgradeToPremium']);
    Route::post('/subscription/cancel',  [SubscriptionPlanController::class, 'cancelPremium']);

    //route pour les videos
    Route::get('/videos', [VideosController::class, 'feed']);
});


Route::get('/next/videos', [VideosController::class, 'next']);
Route::get('/featured/videos', [VideosController::class, 'featured']);
Route::get('/home/showcase', [VideosController::class, 'homeShowcase']);
Route::get('/home/creators', [VideosController::class, 'homeCreators']);

Route::get('/wolplay/spotlight', [VideosController::class, 'wolplaySpotlight']);
Route::get('/videos/{videoId}', [VideosController::class, 'show']);
Route::get('/wolplay/creators', [VideosController::class, 'wolplayVideos']);
Route::get('/videos/tutorial', [VideosController::class, 'tutorialVideos']);
Route::get('/videos/tutorial/spotlight', [VideosController::class, 'tutorialSpotlight']);
Route::get('/videos/collection', [VideosController::class, 'collectionVideos']);
Route::get('/videos/collection/spotlights', [VideosController::class, 'collectionSpotlights']);
Route::get('/videos/creator/{creatorId}', [VideosController::class, 'creatorVideos']);
Route::get('/home/collection', [VideosController::class, 'homeCollection']);
