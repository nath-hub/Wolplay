<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ChangeEmailMail;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
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
                return redirect(config('app.frontend_url') . '/confirmer-compte?status=error&reason=invalid');
            }

            // 3. Vérifier si l'utilisateur est déjà vérifié
            if ($user->hasVerifiedEmail()) {
                return redirect(config('app.frontend_url') . '/confirmer-compte?status=already_verified');
            }

            // 4. Marquer comme vérifié et déclencher l'événement
            if ($user->markEmailAsVerified()) {

                event(new Verified($user));

                $user->email_verified_at = now();
                $user->save();

                Log::info('Compte vérifié avec succès : ' . $user->pseudo);

                return redirect(config('app.frontend_url') . '/confirmer-compte?status=success');
            }

            // 5. Redirection vers le frontend React

            return redirect(config('app.frontend_url') . '/confirmer-compte?status=error&reason=expired');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect(config('app.frontend_url') . '/confirmer-compte?status=invalid_token');
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



    /**
     * Changement d'email — envoie un lien de vérification à newEmail.
     * L'email en base ne change qu'après confirmation du lien.
     * Le backend redirige vers /confirmer-email?status=success&email=... ou ?status=error&reason=...
     * @returns {Promise<{ pending: true }>}
     * @throws "email_already_used"
     * @throws "invalid_password"
     */
    // Endpoint suggere : POST /auth/update-email
    // Entree attendue (body JSON) :
    // {
    //   "userId": 1,
    //   "newEmail": "nouveau@mail.com",
    //   "password": "mot-de-passe-actuel"
    // }
    // Sortie attendue (JSON) :
    // { "pending": true }
    // Flux backend attendu apres clic sur le lien email :
    // - succes   => /confirmer-email?status=success&email=nouveau@mail.com
    // - expire   => /confirmer-email?status=error&reason=expired
    // - invalide => /confirmer-email?status=error&reason=invalid
    // Important :
    // - le frontend ne fait aucun appel API sur /confirmer-email
    // - il lit uniquement les query params
    // - il ne mute le store local que si `email` correspond au pendingEmail local

    #[OA\Post(
        path: '/api/auth/update-email',
        summary: 'Changer d\'email',
        description: 'Envoie un lien de vérification à la nouvelle adresse email. L\'email en base ne change qu\'après confirmation du lien.',
        security: [['bearerAuth' => []]],
        tags: ['Auth']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['newEmail', 'password'],
            properties: [
                new OA\Property(property: 'newEmail', type: 'string', format: 'email', example: 'nouveau@mail.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'mot-de-passe-actuel')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Demande de changement d\'email envoyée',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'pending', type: 'boolean', example: true)
            ]
        )
    )]
    public function updateEmail(Request $request)
    {
        $request->validate([
            'userId' => 'required|exists:users,id',
            'newEmail' => 'required|email',
            'password' => 'required'
        ]);

        $user = $request->user();

        $user = User::findOrFail($request->userId);

        // Vérifier mot de passe
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'invalid_password'
            ], 400);
        }

        // Vérifier si email déjà utilisé
        if (User::where('email', $request->newEmail)->exists()) {
            return response()->json([
                'message' => 'email_already_used'
            ], 400);
        }

        // Stocker email temporaire
        $user->pending_email = $request->newEmail;
        $user->email_change_expires_at = now()->addMinutes(60);
        $user->save();

        // Générer lien signé
        $url = URL::temporarySignedRoute(
            'confirm.email.change',
            now()->addMinutes(60),
            [
                'userId' => $user->id,
                'email' => $request->newEmail
            ]
        );

        // Envoyer mail
        Mail::to($request->newEmail)->send(new ChangeEmailMail($url));

        return response()->json([
            'pending' => true
        ]);
    }




    public function confirmEmailChange(Request $request)
    {
        $user = User::find($request->userId);

        if (!$user) {
            return redirect('/confirmer-email?status=error&reason=invalid');
        }

        // Vérifier expiration
        if (now()->gt($user->email_change_expires_at)) {
            return redirect('/confirmer-email?status=error&reason=expired');
        }

        // Vérifier correspondance
        if ($user->pending_email !== $request->email) {
            return redirect('/confirmer-email?status=error&reason=invalid');
        }

        // Update réel
        $user->email = $user->pending_email;
        $user->pending_email = null;
        $user->email_change_expires_at = null;
        $user->save();

        return redirect('/confirmer-email?status=success&email=' . $user->email);
    }



    public function getByPseudo($pseudo)
    {
        $user = User::where('pseudo', $pseudo)->first();

        if (!$user) {
            return response()->json(null, 200);
        }

        return response()->json([
            'id' => $user->id,
            'pseudo' => $user->pseudo,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'bio' => $user->bio,
            'created_at' => $user->created_at,
        ]);
    }
}
