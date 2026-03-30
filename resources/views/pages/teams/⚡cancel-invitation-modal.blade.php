<?php

use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component
{
    public Team $team;

    public string $invitationCode = '';

    public string $invitationEmail = '';

    public string $modalName = 'cancel-invitation';

    public function mount(Team $team, ?string $invitationCode = null, ?string $invitationEmail = null, ?string $modalName = null): void
    {
        $this->team = $team;
        $this->invitationCode = $invitationCode ?? '';
        $this->invitationEmail = $invitationEmail ?? '';
        $this->modalName = $modalName ?? ($invitationCode !== null ? 'cancel-invitation-'.$invitationCode : 'cancel-invitation');
    }

    public function cancelInvitation(): void
    {
        Gate::authorize('cancelInvitation', $this->team);

        $invitation = $this->team->invitations()
            ->where('code', $this->invitationCode)
            ->firstOrFail();

        if ($this->invitationEmail === '') {
            $this->invitationEmail = $invitation->email;
        }

        $invitation->delete();

        $this->dispatch('close-modal', name: $this->modalName);

        Flux::toast('Invitation cancelled.', variant: 'success');

        $this->redirectRoute('teams.edit', $this->team, navigate: true);
    }
};

?>

<flux:modal :name="$modalName" focusable class="max-w-lg">
    <form wire:submit="cancelInvitation" class="space-y-6">
        <div>
            <flux:heading size="lg">Cancel Invitation</flux:heading>
            <flux:subheading>Are you sure you want to cancel the invitation for {{ $invitationEmail }}?</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Keep Invitation</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit">Cancel Invitation</flux:button>
        </div>
    </form>
</flux:modal>
