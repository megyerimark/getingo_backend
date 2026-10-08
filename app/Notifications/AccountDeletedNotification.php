<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $name) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Getingo fiókod törlésre került')
            ->view('emails.getingo', [
                'preheader' => 'A Getingo fiókod és személyes adataid törlésre kerültek.',
                'title' => 'Fióktörlés visszaigazolása',
                'name' => $this->name,
                'intro' => 'Ezúton visszaigazoljuk, hogy a Getingo fiókod törlésre került.',
                'buttonText' => null,
                'buttonUrl' => null,
                'lines' => [
                    'A fiókhoz tartozó aktív hozzáférések érvénytelenítésre kerültek.',
                    'A jogszabály vagy számviteli kötelezettség alapján megőrzendő bizonylati adatok külön, korlátozott célból megmaradhatnak.',
                    'Ha ezt nem te kérted, válaszolj erre az emailre, hogy kivizsgálhassuk az esetet.',
                ],
            ]);
    }
}
