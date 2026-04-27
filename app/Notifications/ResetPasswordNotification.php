<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe - Wolplay')
            ->greeting('Bonjour ' . $notifiable->pseudo . ' !')
            ->line('Vous recevez cet email car une demande de réinitialisation de mot de passe a été faite pour votre compte.')
            ->action('Réinitialiser mon mot de passe', $this->url)
            ->line('Ce lien expirera dans 60 minutes.')
            ->line('Si vous n\'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email.')
            ->salutation('L\'équipe Wolplay');
    }
}
