<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class EmailVerificationController extends Controller
{


    public function notice(Request $request, $id, $hash)
    {
        try {
            // 1. Trouver l'utilisateur par son UUID
            $user = User::findOrFail($id);

            // 2. Vérifier si le hash correspond
            if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
                return redirect(config('app.frontend_url') . '/login?status=error&reason=invalid');
            }

            // 3. Vérifier si l'utilisateur est déjà vérifié
            if ($user->hasVerifiedEmail()) {
                return redirect(config('app.frontend_url') . '/login?status=already_verified');
            }

            // 4. Marquer comme vérifié et déclencher l'événement
            if ($user->markEmailAsVerified()) {

                event(new Verified($user));

                $user->email_verified_at = now();
                $user->save();

                Log::info('Compte vérifié avec succès : ' . $user->pseudo);

                return redirect(config('app.frontend_url') . '/login?status=success');
            }

            // 5. Redirection vers le frontend React

            return redirect(config('app.frontend_url') . '/login?status=error&reason=expired');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect(config('app.frontend_url') . '/login?status=invalid_token');
        }
    }


    #[OA\Post(
        path: '/api/email/verification-notification',
        summary: 'Renvoyer l\'email de vérification',
        description: 'Envoie un nouveau lien de vérification à l\'adresse email de l\'utilisateur connecté. Limité à 6 requêtes par minute.',
        security: [['bearerAuth' => []]],
        tags: ['Auth']
    )]
    #[OA\Response(
        response: 200,
        description: 'Email envoyé avec succès ou utilisateur déjà vérifié',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Email de vérification renvoyé.')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Non authentifié',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')
            ]
        )
    )]
    #[OA\Response(
        response: 429,
        description: 'Trop de requêtes (Throttle)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Too Many Attempts.')
            ]
        )
    )]
    public function resend(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié.',
            ], 401);
        }
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Votre email est déjà vérifié.',
            ], 200);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Email de vérification renvoyé.',
        ], 200);
    }
}
