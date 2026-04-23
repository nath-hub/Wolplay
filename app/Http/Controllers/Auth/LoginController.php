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
            required: ['login', 'password'],
            properties: [
                new OA\Property(property: 'login', type: 'string', description: 'Email ou Pseudo de l\'utilisateur', example: 'john_doe'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'votre_mot_de_passe'),
                new OA\Property(property: 'remember', type: 'boolean', example: true)
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
                new OA\Property(property: 'data', type: 'object', additionalProperties: true)
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
            'login' => ['required', 'string'],   // email OR pseudo
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        // Determine whether the user typed an e-mail or a pseudo
        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'pseudo';

        $credentials = [
            $field => $request->login,
            'password' => $request->password,
        ];

        //on verifie si le compte est verifier
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            if (!Auth::user()->hasVerifiedEmail()) {
                Auth::logout();
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Veuillez vérifier votre adresse email avant de vous connecter.',
                ], 400);
            }
        }

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Identifiants invalides.',
            ], 400);
        }

        // Block banned users
        if (Auth::user()->is_banned) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Votre compte a été suspendu. Contactez le support.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        Auth::user()->update(['last_login_at' => now()]);

        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Votre compte a été cree avec succes.',
            'data' => Auth::user(),
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
