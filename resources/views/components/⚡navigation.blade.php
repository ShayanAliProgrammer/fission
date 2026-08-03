<?php

use App\Actions\Teams\CreateTeam;
use App\Livewire\Actions\Logout;
use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public string $team_name = '';

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    public function createTeam(CreateTeam $createTeam): void
    {
        $validated = $this->validate([
            'team_name' => ['required', 'string', 'max:255'],
        ]);

        $createTeam->handle(Auth::user(), $validated['team_name']);

        $this->reset('team_name');

        Flux::toast('Team created successfully.', variant: 'success');

        $this->redirectRoute('teams.index', navigate: true);
    }

    public function switchTeam(string $teamId): void
    {
        $user = Auth::user();
        $currentTeam = $user->currentTeam;

        abort_unless($user->belongsToTeam($team = Team::query()->findOrFail($teamId)), 403);

        $user->switchTeam($team);

        Flux::toast('Switched teams.', variant: 'success');

        $referer = request()->header('Referer');

        if (is_string($referer) && $currentTeam !== null && str_contains($referer, '/' . $currentTeam->slug . '/playground')) {
            $this->redirect(str_replace('/' . $currentTeam->slug . '/playground', '/' . $team->slug . '/playground', $referer), navigate: true);

            return;
        }

        $this->redirect($referer ?: route('teams.index', absolute: false), navigate: true);
    }
};
?>

<div>
    <flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:brand href="/" logo="/img/logo.svg" name="Fission" class="max-lg:hidden dark:hidden" />
        <flux:brand href="/" logo="/img/logo-dark.svg" name="Fission" class="hidden max-lg:hidden! dark:flex" />

        <flux:navbar class="max-lg:hidden">
            <flux:navbar.item icon="home" href="/" wire:navigate>Home</flux:navbar.item>
            <flux:separator vertical variant="subtle" class="my-2" />
            <flux:navbar.item icon="face-smile" href="{{ route('playground', auth()->user()->currentTeam) }}" wire:navigate>
                Playground
            </flux:navbar.item>
        </flux:navbar>

        <flux:spacer />

        <flux:dropdown position="bottom" align="end">
            <flux:button icon-trailing="chevron-down" variant="ghost">
                {{ auth()->user()->currentTeam?->name ?? 'Select Team' }}
            </flux:button>

            <flux:menu class="min-w-64">
                <flux:menu.heading>Teams</flux:menu.heading>

                @foreach (auth()->user()->orderedTeams() as $team)
                    <flux:menu.item wire:key="desktop-team-{{ $team->id }}" wire:click="switchTeam('{{ $team->id }}')" class="cursor-pointer">
                        <div class="flex w-full items-center justify-between gap-3">
                            <span class="truncate">{{ $team->name }}</span>

                            @if (auth()->user()->isCurrentTeam($team))
                                <flux:icon name="check" class="size-4" />
                            @endif
                        </div>
                    </flux:menu.item>
                @endforeach

                <flux:menu.separator />
                <flux:menu.item href="{{ route('teams.index') }}" wire:navigate icon="users">Manage Teams</flux:menu.item>

                @if (auth()->user()->currentTeam !== null)
                    <flux:menu.item href="{{ route('teams.edit', auth()->user()->currentTeam) }}" wire:navigate icon="cog-6-tooth">
                        Current Team
                    </flux:menu.item>
                @endif

                <flux:modal.trigger name="create-team-from-nav">
                    <flux:menu.item icon="plus" class="cursor-pointer">New Team</flux:menu.item>
                </flux:modal.trigger>
            </flux:menu>
        </flux:dropdown>

        <flux:dropdown position="bottom" align="end">
            <flux:button icon-trailing="chevron-down" variant="ghost">{{ auth()->user()->name }}</flux:button>

            <flux:navmenu>
                <flux:navmenu.item href="{{ route('profile.update') }}" wire:navigate icon="building-storefront">Profile</flux:navmenu.item>
                <flux:navmenu.item wire:click="logout" icon="arrow-right-start-on-rectangle">Logout</flux:navmenu.item>
            </flux:navmenu>
        </flux:dropdown>
    </flux:header>

    <flux:sidebar sticky collapsible="mobile" class="border-r border-zinc-200 bg-zinc-50 lg:hidden dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <flux:sidebar.brand href="/" logo="/img/logo.svg" logo:dark="/img/logo-dark.svg" name="Fission" />
            <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="/" wire:navigate>Home</flux:sidebar.item>
            <flux:sidebar.item icon="face-smile" href="{{ route('playground', auth()->user()->currentTeam) }}" wire:navigate>
                Playground
            </flux:sidebar.item>
        </flux:sidebar.nav>
    </flux:sidebar>

    <flux:modal name="create-team-from-nav" class="min-w-[22rem] space-y-6">
        <form wire:submit="createTeam">
            <div>
                <flux:heading size="lg">Create Team</flux:heading>
                <flux:subheading>Create a new workspace and switch to it right away.</flux:subheading>
            </div>

            <div class="mt-6">
                <flux:input wire:model="team_name" label="Team Name" type="text" placeholder="Acme Studio" required />
            </div>

            <div class="mt-6 flex gap-2">
                <flux:spacer />

                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary">Create Team</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:main container>
        {{ $slot }}
    </flux:main>
</div>
