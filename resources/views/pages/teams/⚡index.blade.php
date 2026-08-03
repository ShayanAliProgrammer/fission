<?php

use App\Actions\Teams\CreateTeam;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public string $name = '';

    /**
     * @return Collection<int, Team>
     */
    public function teams(): Collection
    {
        return Auth::user()->orderedTeams();
    }

    public function createTeam(CreateTeam $createTeam): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', new App\Rules\TeamName()],
        ]);

        $team = $createTeam->handle(Auth::user(), $validated['name']);

        $this->reset('name');

        Flux::toast('Team created successfully.', variant: 'success');

        $this->redirectRoute('teams.edit', $team, navigate: true);
    }

    public function switchTeam(string $teamId): void
    {
        $user = Auth::user();

        abort_unless($user->belongsToTeam($team = Team::query()->findOrFail($teamId)), 403);

        $user->switchTeam($team);

        Flux::toast('Switched teams.', variant: 'success');

        $this->redirectRoute('teams.edit', $team, navigate: true);
    }
};

?>

<div class="space-y-6">
    <flux:card>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Teams</flux:heading>
                <flux:subheading>Switch between teams or create a new workspace.</flux:subheading>
            </div>

            <form wire:submit="createTeam" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                <flux:input wire:model="name" label="New Team" type="text" placeholder="Acme Studio" class="flex-1" />

                <flux:button type="submit" variant="primary">Create Team</flux:button>
            </form>
        </div>
    </flux:card>

    <div class="grid gap-4">
        @foreach ($this->teams() as $team)
            <flux:card wire:key="team-{{ $team->id }}">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="md">{{ $team->name }}</flux:heading>

                            @if ($team->is_personal)
                                <flux:badge color="zinc">Personal</flux:badge>
                            @endif

                            @if (auth()->user()->isCurrentTeam($team))
                                <flux:badge color="green">Current</flux:badge>
                            @endif
                        </div>

                        <flux:text>{{ auth()->user()->teamRole($team) ?->label() ?? 'Member' }}</flux:text>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if (! auth()->user()->isCurrentTeam($team))
                            <flux:button wire:click="switchTeam('{{ $team->id }}')" variant="ghost">Switch Team</flux:button>
                        @endif

                        <flux:button href="{{ route('teams.edit', $team) }}" wire:navigate variant="primary">
                            {{ Gate::allows('update', $team) ? 'Manage Team' : 'View Team' }}
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
