<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

new class extends Component {
    public Team $team;

    public string $deleteName = '';

    public function mount(Team $team): void
    {
        $this->team = $team;
    }

    public function deleteTeam(): void
    {
        Gate::authorize('delete', $this->team);

        $validated = $this->validate([
            'deleteName' => ['required', 'string'],
        ]);

        if ($validated['deleteName'] !== $this->team->name) {
            $this->addError('deleteName', 'The team name does not match.');

            return;
        }

        $user = Auth::user();
        $fallbackTeam = $user->isCurrentTeam($this->team) ? $user->fallbackTeam($this->team) : null;

        DB::transaction(function () use ($user): void {
            User::query()
                ->where('current_team_id', $this->team->id)
                ->where('id', '!=', $user->id)
                ->get()
                ->each(function (User $affectedUser): void {
                    $fallbackTeam = $affectedUser->fallbackTeam($this->team);

                    if ($fallbackTeam !== null) {
                        $affectedUser->switchTeam($fallbackTeam);
                    }
                });

            $this->team->invitations()->delete();
            $this->team->memberships()->delete();
            $this->team->delete();
        });

        if ($fallbackTeam !== null) {
            $user->switchTeam($fallbackTeam);
        }

        Flux::toast('Team deleted.', variant: 'success');

        $this->redirectRoute('teams.index', navigate: true);
    }
};

?>

<flux:modal name="delete-team" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
    <form wire:submit="deleteTeam" class="space-y-6">
        <div>
            <flux:heading size="lg">Delete Team</flux:heading>
            <flux:subheading>This action cannot be undone. Type the team name to confirm.</flux:subheading>
        </div>

        <flux:input wire:model="deleteName" :label="'Type '.$team->name.' to confirm'" required />

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Cancel</flux:button>
            </flux:modal.close>

            <flux:button variant="danger" type="submit">Delete Team</flux:button>
        </div>
    </form>
</flux:modal>
