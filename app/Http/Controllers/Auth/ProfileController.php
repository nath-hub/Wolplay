<?php

namespace App\Http\Controllers\Auth;

use App\Models\HandleHistory;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{


    #[OA\Post(
        path: '/api/profile',
        summary: 'Mise à jour du profil',
        description: 'Met à jour les informations du profil utilisateur. Nécessite une vérification email.',
        security: [['bearerAuth' => []]],
        tags: ['Profile']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                properties: [
                    new OA\Property(property: 'firstName', type: 'string', maxLength: 100, example: 'John'),
                    new OA\Property(property: 'lastName', type: 'string', maxLength: 100, example: 'Doe'),
                    new OA\Property(property: 'public_name', type: 'string', maxLength: 100, nullable: true, example: 'Jojo'),
                    new OA\Property(property: 'bio', type: 'string', maxLength: 500, nullable: true, example: 'Développeur passionné.'),
                    new OA\Property(property: 'role', type: 'string', enum: ['creator', 'collector', 'admin', 'member'], example: 'member'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'john.doe@example.com'),
                    new OA\Property(property: 'avatar', description: 'Fichier image (max 2Mo)', type: 'string', format: 'binary')
                ]
            )
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Profil mis à jour avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Profile updated successfully.')
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erreur de validation',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'The email has already been taken.'),
                new OA\Property(property: 'errors', type: 'object')
            ]
        )
    )]
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'firstName'   => ['nullable', 'string', 'max:100'],
            'lastName'    => ['nullable', 'string', 'max:100'],
            'public_name' => ['nullable', 'string', 'max:100'],
            'bio'         => ['nullable', 'string', 'max:500'],
            'role'        => ['nullable', 'in:creator,collector,admin,member'],
            'email'       => [
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'avatar'      => ['nullable', 'image', 'max:2048'],
        ]);

        // 🔥 Nettoyage des champs vides
        $validated = array_filter($validated, fn($v) => $v !== null && $v !== '');

        // Email change
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        // Role change
        if (isset($validated['role']) && $validated['role'] !== $user->role) {
            $user->role = $validated['role'];
        }

        // Avatar
        if ($request->hasFile('avatar')) {
            if ($user->avatar_url) {
                Storage::disk('public')->delete($user->avatar_url);
            }
            $validated['avatar_url'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);

        $user->fill($validated)->save();

        if (is_null($user->email_verified_at)) {
            $user->sendEmailVerificationNotification();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Profil modifié avec succès. Veuillez vérifier votre email pour confirmer les changements.',
            ]);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Profile modifié avec succès.',
        ]);
    }

    #[OA\Put(
        path: '/api/profile/password',
        summary: 'Changer le mot de passe',
        description: 'Permet à l\'utilisateur connecté de modifier son mot de passe.',
        security: [['bearerAuth' => []]],
        tags: ['Profile']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['current_password', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'AncienMdp123!'),
                new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'NouveauMdp456!'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'NouveauMdp456!')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Mot de passe modifié',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Mot de passe mis à jour avec succès.')
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erreur de validation (ex: mot de passe actuel incorrect)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'The provided password does not match your current password.')
            ]
        )
    )]
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Mot de passe mis à jour avec succès.',
        ]);
    }

    #[OA\Put(
        path: '/api/profile/pseudo',
        summary: 'Mettre à jour le pseudo',
        description: 'Permet de changer le pseudo si le délai autorisé est respecté. Nécessite le mot de passe actuel pour confirmation.',
        security: [['bearerAuth' => []]],
        tags: ['Profile']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['pseudo', 'password'],
            properties: [
                new OA\Property(property: 'pseudo', type: 'string', minLength: 3, maxLength: 30, example: 'new_pseudo_2026'),
                new OA\Property(property: 'password', type: 'string', format: 'password', description: 'Mot de passe actuel de l\'utilisateur')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Pseudo mis à jour',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'pseudo-updated')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: 'Action interdite (Délai de changement non expiré)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: "Vous ne pouvez changer votre pseudo qu'à partir du 15/05/2026.")
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Erreur de validation (Pseudo déjà pris ou format invalide)',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                new OA\Property(property: 'errors', type: 'object')
            ]
        )
    )]
    public function updatePseudo(Request $request)
    {
        $user = Auth::user();

        if (! $user->canChangePseudo()) {
            $allowedAt = $user->nextPseudoChangeAllowedAt()->format('d/m/Y');
            return back()->withErrors([
                'pseudo' => "Vous ne pouvez changer votre pseudo qu'à partir du {$allowedAt}.",
            ]);
        }

        $request->validate([
            'pseudo' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_.-]+$/',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => ['required', 'current_password'],
        ], [
            'pseudo.regex' => 'Le pseudo ne peut contenir que des lettres, chiffres, tirets, points et underscores.',
        ]);

        $oldPseudo = $user->pseudo;

        // Log the change
        HandleHistory::create([
            'user_id'    => $user->id,
            'old_handle' => $oldPseudo,
            'new_handle' => $request->pseudo,
            'fee_charged' => 0.00,
        ]);

        $user->update([
            'pseudo'           => $request->pseudo,
            'pseudo_changed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Pseudo mis à jour avec succès.',
        ]);
    }


    #[OA\Delete(
        path: '/api/profile',
        summary: 'Supprimer le compte utilisateur',
        description: 'Supprime définitivement le compte, l\'avatar et révoque tous les accès. Cette action est irréversible.',
        security: [['bearerAuth' => []]],
        tags: ['Profile']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['password'],
            properties: [
                new OA\Property(property: 'password', type: 'string', format: 'password', description: 'Confirmation du mot de passe pour suppression')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Compte supprimé avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'account-deleted')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: 'Mot de passe incorrect ou non authentifié',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')
            ]
        )
    )]
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = Auth::user();

        Auth::logout();

        if ($user->avatar_url) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Compte supprimé avec succès.',
        ]);
    }

    #[OA\Get(
        path: '/api/profile',
        summary: 'Récupérer les informations de l\'utilisateur connecté',
        description: 'Retourne les données complètes de l\'utilisateur actuellement authentifié via le token Bearer.',
        security: [['bearerAuth' => []]],
        tags: ['Profile']
    )]
    #[OA\Response(
        response: 200,
        description: 'Utilisateur récupéré avec succès',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'status', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: 'User retrieved successfully.'),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'a19e6c7b-25b0-4f8a-9437-b765cfe90ff2'),
                        new OA\Property(property: 'pseudo', type: 'string', example: 'john_doe'),
                        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                        new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                        new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                        new OA\Property(property: 'role', type: 'string', example: 'member'),
                        new OA\Property(property: 'avatar_url', type: 'string', nullable: true, example: 'avatars/default.png')
                    ]
                )
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
    public function getUserWithToken()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'User retrieved successfully.',
            'data' => $user,
        ], 200);
    }
}
