<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class RegisterController extends Controller
{
    #[OA\Post(
        path: '/api/register',
        summary: 'Inscription utilisateur',
        description: 'Crée un utilisateur avec pseudo, email et mot de passe (confirmé). Connecte l\'utilisateur après inscription.',
        tags: ['Auth']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['pseudo', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'pseudo', type: 'string', minLength: 3, maxLength: 30, example: 'john_doe'),
                new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'john@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'StrongPassword123!')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Inscription réussie',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 201),
                new OA\Property(property: 'message', type: 'string', example: 'Inscription réussie. Veuillez vérifier votre email.')
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erreur de validation',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.')
            ]
        )
    )]
    public function store(Request $request)
    {

        $reservedPseudos = [
            'admin',
            'support',
            'wolplay',
            'api',
            'root',
            'system',
        ];

        $validator = Validator::make($request->all(), [

            'pseudo' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_.-]+$/',
            ],

            'firstName' => ['nullable', 'string', 'max:255'],

            'lastName' => ['nullable', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],

            'password' => ['required', 'string', 'min:8'],

        ], [
            'pseudo.regex' =>
            'Le pseudo ne peut contenir que des lettres, chiffres, tirets, points et underscores.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $pseudo = Str::lower($request->pseudo);
        $email = Str::lower($request->email);

        // Vérification pseudo réservé
        if (in_array($pseudo, $reservedPseudos)) {

            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Pseudo réservé',
            ], 422);
        }

        // Vérification pseudo existant (insensible à la casse)
        $pseudoExists = User::whereRaw(
            'LOWER(pseudo) = ?',
            [$pseudo]
        )->exists();

        if ($pseudoExists) {

            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Pseudo deja utilise',
            ], 422);
        }

        // Vérification email existant (insensible à la casse)
        $emailExists = User::whereRaw(
            'LOWER(email) = ?',
            [$email]
        )->exists();

        if ($emailExists) {

            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Email deja utilise',
            ], 422);
        }

        // Création utilisateur
        $user = User::create([
            'pseudo' => $request->pseudo,
            'email' => $email,
            'password' => Hash::make($request->password),
            'firstName' => $request->firstName ?? '',
            'lastName' => $request->lastName ?? '',
        ]);

        event(new Registered($user));

        Log::info('Nouvelle inscription : ' . $user->pseudo, [
            'user_id' => $user->id,
        ]);

        // ❌ PAS de Auth::login($user)

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Inscription réussie. Veuillez vérifier votre email pour activer votre compte.',
        ], 201);
    }
}
