<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LifeRegenNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['database']; // ou ['mail'] si tu veux l'envoyer par email
    }

    public function toArray($notifiable)
    {
        return [
            'message' => 'Une vie a été régénérée ! ❤️',
        ];
    }
}
