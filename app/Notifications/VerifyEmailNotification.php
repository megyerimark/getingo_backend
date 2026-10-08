<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Erősítsd meg a Getingo email címed')
            ->view('emails.getingo', [
                'preheader' => 'Már csak egy lépés, és használhatod a teljes Getingo fiókodat.',
                'title' => 'Erősítsd meg az email címed',
                'name' => $notifiable->name,
                'intro' => 'Köszönjük, hogy regisztráltál. A fiókod aktiválásához erősítsd meg az email címed az alábbi gombbal.',
                'buttonText' => 'Email cím megerősítése',
                'buttonUrl' => $verificationUrl,
                'lines' => [
                    'A megerősítő link 60 percig érvényes.',
                    'Ha nem te hoztad létre ezt a fiókot, nincs további teendőd.',
                ],
            ]);
    }
}
