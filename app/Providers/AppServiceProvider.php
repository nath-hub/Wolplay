<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {


        VerifyEmail::toMailUsing(function (object $notifiable) {
            // 1. Générer l'URL signée officielle de Laravel
            $verifyUrl = URL::temporarySignedRoute(
                'verification.verify', // Nom de votre route
                now()->addMinutes(60), // Expiration
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            // 2. Envoyer le mail
            return (new MailMessage)
                ->subject(Lang::get('Vérifiez votre adresse e-mail - Wolplay'))
                ->greeting('Bonjour ' . $notifiable->pseudo . ' !')
                ->line(Lang::get('Merci de vous être inscrit sur Wolplay, la maison numérique des créateurs d\'univers miniatures.'))
                ->action(Lang::get('Confirmer mon inscription'), $verifyUrl) // On utilise l'url signée ici
                ->line(Lang::get('Si vous n\'avez pas créé de compte, aucune action n\'est requise.'))
                ->salutation('L\'équipe Wolplay');
        });
    }
}
