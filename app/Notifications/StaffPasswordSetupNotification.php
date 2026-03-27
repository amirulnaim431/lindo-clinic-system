<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class StaffPasswordSetupNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name', 'Lindo Clinic');

        return (new MailMessage)
            ->subject('Set up your '.$appName.' access')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An administrator has created or updated your internal staff access.')
            ->line('Use the button below to create your password and complete sign-in setup.')
            ->action('Set up password', $this->resetUrl($notifiable))
            ->line('If you did not expect this email, please contact your administrator.');
    }
}
