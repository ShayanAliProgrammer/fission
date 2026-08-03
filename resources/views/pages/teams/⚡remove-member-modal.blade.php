<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component {
    public Team $team;

    public ?string $memberId = null;

    public string $memberName = '';

    public string $modalName = 'remove-member';

    public function mount(Team $team, ?string $memberId = null, ?string $memberName = null, ?string $modalName = null): void
    {
        $this->team = $team;
        $this->memberId = $memberId;
        $this->memberName = $memberName ?? '';
        $this->modalName = $modalName ?? ($memberId !== null ? 'remove-member-' . $memberId : 'remove-member');
    }

    public function removeMember(): void
    {
        Gate::authorize('removeMember', $this->team);

        $user = User::query()->findOrFail($this->memberId);

        if ($this->memberName === '') {
            $this->memberName = $user->name;
        }

        $this->team
            ->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($this->team)) {
            $fallbackTeam = $user->fallbackTeam($this->team);

            if ($fallbackTeam !== null) {
                $user->switchTeam($fallbackTeam);
            }
        }

        $this->dispatch('close-modal', name: $this->modalName);

        Flux::toast('Member removed.', variant: 'success');

        $this->redirectRoute('teams.edit', $this->team, navigate: true);
    }
};

?>

<flux:modal :name="$modalName" focusable class="max-w-lg">
    <form wire:submit="removeMember" class="space-y-6">
        <div>
            <flux:heading size="lg">Remove Team Member</flux:heading>
            <flux:subheading>Are you sure you want to remove {{ $memberName }} from this team?</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit">Remove Member</flux:button>
        </div>
    </form>
</flux:modal>
