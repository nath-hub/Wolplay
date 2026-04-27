<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use OpenApi\Attributes as OA;

class PasswordResetController extends Controller
{

    #[OA\Post(
        path: '/api/forgot-password',
        summary: 'Envoyer un lien de réinitialisation',
        description: 'Envoie un email contenant le token de réinitialisation si l\'adresse existe.',
        tags: ['Auth']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Lien envoyé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Nous vous avons envoyé par courriel le lien de réinitialisation.')
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erreur de validation ou email introuvable',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'L\'adresse email est invalide.')
            ]
        )
    )]
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Utilisez => au lieu de = et mettez les crochets du tableau
        $user = User::where('email', $request->email)->update(['email_verified_at' => null]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['success' => true, 'status' => 200, 'message' => __($status)])
            : response()->json(['success' => false, 'status' => 422,  'message' => __($status)], 422);
    }


    #[OA\Post(
        path: '/api/reset-password',
        summary: 'Réinitialiser le mot de passe',
        description: 'Met à jour le mot de passe en utilisant le token reçu par email.',
        tags: ['Auth']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'token', type: 'string', description: 'Le token reçu par email'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Mot de passe réinitialisé',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Votre mot de passe a été réinitialisé !')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Token invalide ou expiré',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Ce jeton de réinitialisation du mot de passe n\'est pas valide.')
            ]
        )
    )]
    public function reset(Request $request)
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password'       => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['success' => true,  'status' => 200, 'message' => __($status)])
            : response()->json(['success' => false, 'status' => 400,  'message' => __($status)], 400);
    }



    public function passwordReset(string $token, Request $request)
    {
        User::where('email', $request->email)->update([
            'email_verified_at' => now()
        ]);

        return response()->json([
            'token' => $token,
            'email' => $request->email,
            'message' => 'Page de reset password',
            //TODO::page de redirection vers ou on doit mettre le nouveau mot de passe
        ]);
    }
}
