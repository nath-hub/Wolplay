<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanController extends Controller
{


  // ── fetchCurrentPlan ──────────────────────────────────────────────────────
    // GET /v1/subscription
    // @returns { plan: "free"|"premium"|"pro", expiresAt: string|null }

    public function current(Request $request): JsonResponse
    {
        $user = $request->user()->load('activeSubscription.plan');

        $sub = $user->activeSubscription;

        return response()->json([
            'plan'      => $sub?->plan->slug ?? 'free',
            'expiresAt' => $sub?->expires_at?->toIso8601String(),
            'status'    => $sub?->status ?? 'none',
        ]);
    }

    // ── upgradeToPremium ──────────────────────────────────────────────────────
    // POST /v1/subscription/premium
    // @returns { success: boolean, plan: "premium" }

    public function upgrade(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->plan === 'premium', 422, 'Vous êtes déjà en plan Premium.');
        abort_if($user->plan === 'pro',     422, 'Le plan Pro ne peut pas être rétrogradé via cette route.');

        $plan = SubscriptionPlan::where('slug', 'premium')
            ->where('is_active', true)
            ->firstOrFail();

        DB::transaction(function () use ($user, $plan) {
            // Annuler l'abonnement actif éventuel
            UserSubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            // Créer le nouvel abonnement
            UserSubscription::create([
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'started_at' => now(),
                'expires_at' => now()->addMonth(),
                'status'     => 'active',
            ]);

            // Mettre à jour le champ plan du user (dénormalisation pour les requêtes rapides)
            $user->update(['plan' => 'premium']);
        });

        return response()->json(['success' => true, 'plan' => 'premium']);
    }

    // ── cancelPremium ─────────────────────────────────────────────────────────
    // POST /v1/subscription/cancel
    // @returns { success: boolean, plan: "free" }

    public function cancel(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->plan === 'free', 422, 'Aucun abonnement Premium actif à annuler.');

        DB::transaction(function () use ($user) {
            UserSubscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update([
                    'status'       => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            $user->update(['plan' => 'free']);
        });

        return response()->json(['success' => false, 'plan' => 'free']);
    }
}
