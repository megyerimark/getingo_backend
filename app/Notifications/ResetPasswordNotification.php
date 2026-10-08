<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $email = $notifiable->getEmailForPasswordReset();
        $resetUrl = $frontendUrl.'/reset-password?token='.urlencode($this->token).'&email='.urlencode($email);

        return (new MailMessage)
            ->subject('Getingo jelszó visszaállítása')
            ->view('emails.getingo', [
                'preheader' => 'Új jelszót állíthatsz be a Getingo fiókodhoz.',
                'title' => 'Jelszó visszaállítása',
                'name' => $notifiable->name,
                'intro' => 'Jelszó-visszaállítási kérelmet kaptunk a Getingo fiókodhoz.',
                'buttonText' => 'Új jelszó beállítása',
                'buttonUrl' => $resetUrl,
                'lines' => [
                    'A jelszó-visszaállító link 60 percig érvényes.',
                    'Ha nem te kérted a visszaállítást, nincs további teendőd. A jelenlegi jelszavad változatlan marad.',
                ],
            ]);
    }
}
