<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
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
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'StrongPassword123!'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'StrongPassword123!')
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
        $request->validate([
            'pseudo'   => [
                'required',
                'string',
                'min:3',
                'max:30',
                'unique:users,pseudo',
                'regex:/^[a-zA-Z0-9_.-]+$/',
            ],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'pseudo.regex' => 'Le pseudo ne peut contenir que des lettres, chiffres, tirets, points et underscores.',
        ]);

        $user = User::create([
            'pseudo'     => $request->pseudo,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'firstName'  => '',   // complété dans le profil
            'lastName'   => '',
        ]);

        $stat = event(new Registered($user));
        Log::info('Nouvelle inscription : ' . $user->pseudo, ['user_id' => $user->id, 'event_status' => $stat]);

        Auth::login($user);

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Inscription réussie. Veuillez vérifier votre email pour activer votre compte.',
        ], 201);
    }
}
