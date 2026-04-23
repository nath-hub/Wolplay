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
use Illuminate\View\View;

class ProfileController extends Controller
{
    

    // ── Update general information ─────────────────────────────────────────────

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'firstName'   => ['required', 'string', 'max:100'],
            'lastName'    => ['required', 'string', 'max:100'],
            'public_name' => ['nullable', 'string', 'max:100'],
            'bio'         => ['nullable', 'string', 'max:500'],
            'email'       => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'avatar'      => ['nullable', 'image', 'max:2048'],
        ]);

        // If email changed, require re-verification
        if ($validated['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            if ($user->avatar_url) {
                Storage::disk('public')->delete($user->avatar_url);
            }
            $validated['avatar_url'] = $request->file('avatar')
                ->store('avatars', 'public');
        }

        unset($validated['avatar']);
        $user->fill($validated)->save();

        if (is_null($user->email_verified_at)) {
            $user->sendEmailVerificationNotification();
            return redirect()->route('profile.edit')
                ->with('status', 'profile-updated-verify-email');
        }

        return redirect()->route('profile.edit')
            ->with('status', 'profile-updated');
    }

    // ── Change password ────────────────────────────────────────────────────────

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'password-updated');
    }

    // ── Change pseudo ──────────────────────────────────────────────────────────

    public function updatePseudo(Request $request): RedirectResponse
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
                'required', 'string', 'min:3', 'max:30',
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

        return redirect()->route('profile.edit')
            ->with('status', 'pseudo-updated');
    }

    // ── Delete account ─────────────────────────────────────────────────────────

    public function destroy(Request $request): RedirectResponse
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

        return redirect('/')->with('status', 'account-deleted');
    }
}