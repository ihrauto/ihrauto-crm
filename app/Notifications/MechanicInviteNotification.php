<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MechanicInviteNotification extends Notification
{
    use Queueable;

    protected string $inviteUrl;
    protected string $tenantName;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $inviteUrl, string $tenantName)
    {
        $this->inviteUrl = $inviteUrl;
        $this->tenantName = $tenantName;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been invited to join {$this->tenantName}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("You've been invited to join **{$this->tenantName}** on IHR Auto CRM.")
            ->line('Click the button below to set up your password and activate your account.')
            ->action('Set Up My Account', $this->inviteUrl)
            ->line('This invitation link will expire in 48 hours.')
            ->line('If you did not expect this invitation, you can ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invite_url' => $this->inviteUrl,
            'tenant_name' => $this->tenantName,
        ];
    }
}
