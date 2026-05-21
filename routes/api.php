<?php

use App\Http\Controllers\AgendaController;
use App\Http\Controllers\AtelierController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\CreatorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EtabliController;
use App\Http\Controllers\FollowsController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\SubscriptionPlanController;
use App\Http\Controllers\VideoDisciplinesController;
use App\Http\Controllers\VideosController;
use Illuminate\Support\Facades\Route;

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



});
// ── Authenticated routes ───────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
 Route::get('/me', [EmailVerificationController::class, 'getById'])->name('profile.show-by-id');
    // Déconnexion
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware(['auth:sanctum', 'throttle:6,1'])
        ->name('verification.send');

    Route::patch('/public/profile/{pseudo}', [EmailVerificationController::class, 'updateByPseudo']);

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


Route::get('/creators/{profileId}/agenda', [AgendaController::class, 'index']);

Route::get('/videos/feed', [VideosController::class, 'feed']);

// Routes publiques pour Atelier et Établi (lecture seule)
// fetchAtelierFeed
Route::get('/atelier/feed', [AtelierController::class, 'feed']);
Route::get('/atelier/posts', [AtelierController::class, 'postsByCreator']);

// fetchEtabliItems
Route::get('/etabli/items', [EtabliController::class, 'index']);

// Next video aliases
Route::get('/next/videos', [VideosController::class, 'next']);
Route::get('/next/videos/{currentVideoId}', [VideosController::class, 'next']);
// Route::get('/videos/next', [VideosController::class, 'next']);
// Route::get('/videos/next/{currentVideoId}', [VideosController::class, 'next']);

//   Route::get('/creators/recommended/{userId}',           [FollowsController::class, 'recommended']);

Route::middleware('auth:sanctum')->group(function () {

    // Follow & Recommandations
    Route::post('/creators/{creatorId}/follow',   [FollowsController::class, 'follow']);
    Route::delete('/creators/{creatorId}/follow', [FollowsController::class, 'unfollow']);


    // fetchFollowStatus
    Route::get('/creators/{creatorId}/follow',         [FollowsController::class, 'status']);
    // fetchFollowing
    Route::get('/users/{userId}/following',            [FollowsController::class, 'following']);
    // fetchFollowers
    Route::get('/users/{userId}/followers',            [FollowsController::class, 'followers']);

    // // Abonnements
    // Route::post('/subscription/upgrade', [SubscriptionPlanController::class, 'upgradeToPremium']);
    // Route::post('/subscription/cancel',  [SubscriptionPlanController::class, 'cancelPremium']);

    //route pour les videos
    Route::get('/videos', [VideosController::class, 'feed']);





    // ── Recommandations & Follow ──────────────────────────────────────────
    // fetchRecommendedCreators
    Route::get('/creators/recommended',                [CreatorController::class, 'recommended']);

    //fetchCreatorsList
    Route::get('/creators',                [CreatorController::class, 'index']);
    // // followCreator
    // Route::post('/creators/{creatorId}/follow',        [FollowController::class, 'follow']);
    // // unfollowCreator
    // Route::delete('/creators/{creatorId}/follow',      [FollowController::class, 'unfollow']);
    // // fetchFollowStatus
    // Route::get('/creators/{creatorId}/follow',         [FollowController::class, 'status']);
    // // fetchFollowing
    // Route::get('/users/{userId}/following',            [FollowController::class, 'following']);
    // // fetchFollowers
    // Route::get('/users/{userId}/followers',            [FollowController::class, 'followers']);

    // ── Gestion vidéos propriétaire ───────────────────────────────────────
    // fetchPinnedVideos
    Route::get('/users/{userId}/videos',               [VideoDisciplinesController::class, 'index']);
    Route::get('/videos/pinned',                       [VideoDisciplinesController::class, 'publicPinned']);
    // addPinnedVideo
    Route::post('/videos/pinned',              [VideoDisciplinesController::class, 'store']);
    // deletePinnedVideo
    Route::delete('/videos/pinned/{videoId}',  [VideoDisciplinesController::class, 'destroy']);
    // fetchFeaturedVideoIds
    Route::get('/videos/featured-ids',      [VideoDisciplinesController::class, 'featuredIds']);
    // updateFeaturedVideoIds
    Route::put('/videos/featured-ids',      [VideoDisciplinesController::class, 'updateFeatured']);

    // ── Agenda (gestion propriétaire) ─────────────────────────────────────

    // addAgendaEvent
    Route::post('/creators/{profileId}/agenda',               [AgendaController::class, 'store']);
    // updateAgendaEvent
    Route::patch('/creators/{profileId}/agenda/{eventId}',    [AgendaController::class, 'update']);
    // deleteAgendaEvent
    Route::delete('/creators/{profileId}/agenda/{eventId}',   [AgendaController::class, 'destroy']);

    // ── Dashboard (L'Établi) ──────────────────────────────────────────────
    // fetchDashboardFeed
    Route::get('/dashboard/feed',                      [DashboardController::class, 'feed']);
    // // createDashboardPost
    Route::post('/dashboard/posts',                    [DashboardController::class, 'store']);
    // // deleteDashboardPost
    Route::delete('/dashboard/posts/{postId}',         [DashboardController::class, 'destroy']);
    // // updateWipPost
    Route::patch('/dashboard/wip',                     [DashboardController::class, 'updateWip']);
    // // toggleWipPin
    Route::post('/dashboard/wip/{postId}/pin',         [DashboardController::class, 'toggleWipPin']);

    // ── Atelier (Feed Social) ──────────────────────────────────────────────
    // createAtelierPost
    Route::post('/atelier/posts',                      [AtelierController::class, 'store']);
    Route::patch('/atelier/posts/{postId}',            [AtelierController::class, 'update']);
    Route::delete('/atelier/posts/{postId}',           [AtelierController::class, 'destroy']);

    // ── Établi (Vitrine personnelle) ───────────────────────────────────────
    // createEtabliItem
    Route::post('/etabli/items',                       [EtabliController::class, 'store']);
    Route::patch('/etabli/items/{itemId}',             [EtabliController::class, 'update']);
    Route::delete('/etabli/items/{itemId}',            [EtabliController::class, 'destroy']);
    Route::put('/etabli/order',                        [EtabliController::class, 'updateOrder']);

    // ── Abonnements ───────────────────────────────────────────────────────
    // fetchCurrentPlan
    Route::get('/subscription',                        [SubscriptionPlanController::class, 'current']);
    // // upgradeToPremium
    Route::post('/subscription/upgrade',               [SubscriptionPlanController::class, 'upgrade']);
    // // cancelPremium
    Route::post('/subscription/cancel',             [SubscriptionPlanController::class, 'cancel']);

    // ── Images ─────────────────────────────────────────────────────────────
    // renewImageUrl
    Route::patch('/images/renew',                      [ImageController::class, 'renewUrl']);
});

Route::get('/featured/videos', [VideosController::class, 'featured']);
Route::get('/home/showcase', [VideosController::class, 'homeShowcase']);
Route::get('/home/creators', [VideosController::class, 'homeCreators']);
Route::get('/videos/collection', [VideosController::class, 'collectionVideos']);
Route::get('/wolplay/spotlight', [VideosController::class, 'wolplaySpotlight']);
Route::get('/wolplay/creators', [VideosController::class, 'wolplayVideos']);
Route::get('/videos/tutorial', [VideosController::class, 'tutorialVideos']);
Route::get('/all_videos/{videoId}', [VideosController::class, 'show']);
Route::get('/tutorials/spotlight', [VideosController::class, 'tutorialSpotlight']);

Route::get('/collection/spotlights', [VideosController::class, 'collectionSpotlights']);
Route::get('/videos/creator/{creatorId}', [VideosController::class, 'creatorVideos']);
Route::get('/home/collection', [VideosController::class, 'homeCollection']);
// fetchAgendaEvents
