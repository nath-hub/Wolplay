<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{


    public function notice(Request $request, $id, $hash)
    {
        // 1. Trouver l'utilisateur par son UUID
        $user = User::findOrFail($id);

        // 2. Vérifier si le hash correspond
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Lien de vérification invalide.'], 403);
        }

        // 3. Vérifier si l'utilisateur est déjà vérifié
        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.frontend_url') . '/login?verified=already');
        }

        // 4. Marquer comme vérifié et déclencher l'événement
        if ($user->markEmailAsVerified()) {

            event(new Verified($user));

            $user->email_verified_at = now();
            $user->save();

            Log::info('Compte vérifié avec succès : ' . $user->pseudo); //
        }

        // 5. Redirection vers le frontend React

        return redirect(config('app.frontend_url') . '/login?status=verified_success');
    }


    /** Renvoi du mail de vérification */
    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Email de vérification renvoyé.',
        ], 200);
    }
}
