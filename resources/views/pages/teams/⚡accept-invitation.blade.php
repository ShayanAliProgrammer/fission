<?php

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public TeamInvitation $invitation;

    public function mount(TeamInvitation $invitation): void
    {
        $this->invitation = $invitation;
    }

    public function acceptInvitation(): void
    {
        $user = Auth::user();

        $this->validateInvitation($user, $this->invitation);

        DB::transaction(function () use ($user): void {
            $team = $this->invitation->team;

            $team->memberships()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => $this->invitation->role],
            );

            $this->invitation->update(['accepted_at' => now()]);

            $user->switchTeam($team);
        });

        Flux::toast('Invitation accepted.', variant: 'success');

        $this->redirectRoute('teams.edit', $this->invitation->team, navigate: true);
    }

    private function validateInvitation(User $user, TeamInvitation $invitation): void
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation has already been accepted.'],
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation has expired.'],
            ]);
        }

        if (Str::lower($invitation->email) !== Str::lower($user->email)) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation was sent to a different email address.'],
            ]);
        }
    }
};

?>

<div class="mx-auto flex max-w-xl flex-col gap-6 py-12">
    <flux:card>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Accept Team Invitation</flux:heading>
                <flux:subheading>
                    Join {{ $invitation->team->name }} as {{ $invitation->role->label() }}.
                </flux:subheading>
            </div>

            @error('invitation')
                <flux:callout color="red" icon="exclamation-triangle">{{ $message }}</flux:callout>
            @enderror

            <div class="flex justify-end">
                <flux:button wire:click="acceptInvitation" variant="primary">Accept Invitation</flux:button>
            </div>
        </div>
    </flux:card>
</div>
