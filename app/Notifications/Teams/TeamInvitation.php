<?php

declare(strict_types=1);

namespace App\Notifications\Teams;

use App\Models\TeamInvitation as TeamInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TeamInvitationModel $invitation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject("You've been invited to join ".$team->name)
            ->line(sprintf('%s has invited you to join the %s team.', $inviter->name, $team->name))
            ->action('Accept invitation', route('teams.invitations.accept', $this->invitation->code));
    }
}
