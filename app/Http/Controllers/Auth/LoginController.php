<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class LoginController extends Controller
{

    #[OA\Post(
        path: '/api/login',
        summary: 'Connexion utilisateur',
        description: 'Permet à un utilisateur de se connecter en utilisant son email ou son pseudo.',
        tags: ['Auth']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['identifier', 'password'],
            properties: [
                new OA\Property(property: 'identifier', type: 'string', description: 'Email ou Pseudo de l\'utilisateur', example: 'john_doe'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'votre_mot_de_passe')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Connexion réussie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string', example: 'Votre compte a été connecté avec succès.'),
                new OA\Property(property: 'user', type: 'object', additionalProperties: true),
                new OA\Property(property: 'accessToken', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
                new OA\Property(property: 'tokenType', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', example: '2024-07-01T12:00:00Z')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Erreur d\'authentification (Email non vérifié, Identifiants invalides ou Compte banni)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'status', type: 'integer', example: 400),
                new OA\Property(property: 'message', type: 'string', example: 'Identifiants invalides.')
            ]
        )
    )]
    public function store(Request $request)
    {
        $request->validate([
            'identifier' => ['required', 'string'], // email OR pseudo
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        // Déterminer si c'est un email ou un pseudo
        $field = filter_var($request->identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'pseudo';

        $credentials = [
            $field => $request->identifier,
            'password' => $request->password,
        ];

        // 1. Tentative de connexion
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Identifiants invalides.',
            ], 400);
        }

        $user = Auth::user();

        // 2. Vérification de l'email
        if (!$user->hasVerifiedEmail()) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.',
            ], 400);
        }

        // 3. Vérification du bannissement
        if ($user->is_banned) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Votre compte a été suspendu. Contactez le support.',
            ], 400);
        }

        // 4. Succès : Nettoyage du RateLimiter et mise à jour login
        RateLimiter::clear($this->throttleKey($request));
        $user->update(['last_login_at' => now()]);

        /** * CHANGEMENT ICI : Suppression de la session et génération du Token
         * Cela règle l'erreur "Session store not set"
         */
        $token = $user->createToken('auth_token')->plainTextToken;


        return response()->json([
            'user' => $user,
            'tokenType' => 'Bearer',
            'accessToken' => $token,
            'expiresAt' => now()->addMonth(6)->toISOString(), // IMPORTANT
        ]);
    }



    #[OA\Post(
        path: '/api/logout',
        summary: 'Déconnexion utilisateur',
        description: 'Supprime tous les tokens d\'accès de l\'utilisateur connecté.',
        security: [['bearerAuth' => []]],
        tags: ['Auth']
    )]
    #[OA\Response(
        response: 200,
        description: 'Déconnexion réussie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'Déconnexion réussie.')
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
    public function destroy(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Déconnexion réussie.',
        ]);
    }

    // ── Rate limiting ─────────────────────────────────────────────────────────

    protected function ensureIsNotRateLimited(Request $request): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->input('login')) . '|' . $request->ip());
    }
}
