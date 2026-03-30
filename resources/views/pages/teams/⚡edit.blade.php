<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Rules\TeamName;
use App\Support\TeamPermissions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component
{
    public Team $team;

    public string $teamName = '';

    /**
     * @var array{id: string, name: string, slug: string, is_personal: bool}
     */
    public array $teamData = [];

    /**
     * @var list<array{id: string, name: string, email: string, role: string, role_label: string}>
     */
    public array $members = [];

    /**
     * @var list<array{code: string, email: string, role: string, role_label: string}>
     */
    public array $invitations = [];

    /**
     * @var list<array{value: string, label: string}>
     */
    public array $availableRoles = [];

    public function mount(Team $team): void
    {
        Gate::authorize('view', $team);

        $this->team = $team;
        $this->teamName = $team->name;

        $this->refreshTeamState();
    }

    public function updateTeam(): void
    {
        Gate::authorize('update', $this->team);

        $validated = $this->validate([
            'teamName' => ['required', 'string', 'max:255', new TeamName],
        ]);

        $team = DB::transaction(function () use ($validated): Team {
            $team = Team::query()->whereKey($this->team->id)->lockForUpdate()->firstOrFail();
            $team->update(['name' => $validated['teamName']]);

            return $team;
        });

        $this->team = $team;
        $this->teamName = $team->name;

        $this->refreshTeamState();

        Flux::toast('Team updated successfully.', variant: 'success');

        $this->redirectRoute('teams.edit', $team, navigate: true);
    }

    public function updateMember(string $userId, string $role): void
    {
        Gate::authorize('updateMember', $this->team);

        $validated = Validator::make(['role' => $role], [
            'role' => ['required', 'string', Rule::enum(TeamRole::class)],
        ])->validate();

        $this->team->memberships()
            ->where('user_id', $userId)
            ->firstOrFail()
            ->update(['role' => TeamRole::from($validated['role'])]);

        $this->refreshTeamState();

        Flux::toast('Member role updated.', variant: 'success');
    }

    public function permissions(): TeamPermissions
    {
        return Auth::user()->toTeamPermissions($this->team);
    }

    private function refreshTeamState(): void
    {
        $team = $this->team->fresh();

        $this->teamData = [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'is_personal' => $team->is_personal,
        ];

        $this->members = $team->members()
            ->orderBy('name')
            ->get()
            ->map(fn ($member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
                'role_label' => TeamRole::from($member->pivot->role)->label(),
            ])
            ->all();

        $this->invitations = $team->invitations()
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('email')
            ->get()
            ->map(fn ($invitation): array => [
                'code' => $invitation->code,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
            ])
            ->all();

        $this->availableRoles = TeamRole::assignable();
    }
};

?>

<div class="space-y-8">
    <flux:card>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ Gate::allows('update', $team) ? 'Manage Team' : 'View Team' }}</flux:heading>
                <flux:subheading>Update team details, roles, and invitations.</flux:subheading>
            </div>

            @if ($this->permissions()->canUpdateTeam)
                <form wire:submit="updateTeam" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <flux:input wire:model="teamName" label="Team Name" type="text" class="flex-1" required />
                    <flux:button type="submit" variant="primary">Save Team</flux:button>
                </form>
            @else
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="md">{{ $teamData['name'] }}</flux:heading>

                    @if ($teamData['is_personal'])
                        <flux:badge color="zinc">Personal</flux:badge>
                    @endif
                </div>
            @endif
        </div>
    </flux:card>

    <flux:card>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="lg">Members</flux:heading>
                    <flux:subheading>Manage who belongs to this team.</flux:subheading>
                </div>

                @if ($this->permissions()->canCreateInvitation)
                    <flux:modal.trigger name="invite-member">
                        <flux:button variant="primary">Invite Member</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>

            <div class="space-y-3">
                @foreach ($members as $member)
                    <flux:card wire:key="member-{{ $member['id'] }}">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="space-y-1">
                                <flux:heading size="md">{{ $member['name'] }}</flux:heading>
                                <flux:text>{{ $member['email'] }}</flux:text>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($member['role'] !== 'owner' && $this->permissions()->canUpdateMember)
                                    <flux:dropdown position="bottom" align="end">
                                        <flux:button variant="ghost" icon-trailing="chevron-down">{{ $member['role_label'] }}</flux:button>

                                        <flux:menu>
                                            @foreach ($availableRoles as $role)
                                                <flux:menu.item wire:click="updateMember('{{ $member['id'] }}', '{{ $role['value'] }}')" class="cursor-pointer">
                                                    {{ $role['label'] }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:badge color="zinc">{{ $member['role_label'] }}</flux:badge>
                                @endif

                                @if ($member['role'] !== 'owner' && $this->permissions()->canRemoveMember)
                                    <flux:modal.trigger name="remove-member-{{ $member['id'] }}">
                                        <flux:button variant="ghost" icon="x-mark">Remove</flux:button>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                        </div>
                    </flux:card>

                    @if ($member['role'] !== 'owner' && $this->permissions()->canRemoveMember)
                        <livewire:pages::teams.remove-member-modal
                            :team="$team"
                            :member-id="$member['id']"
                            :member-name="$member['name']"
                            :modal-name="'remove-member-'.$member['id']"
                            :key="'remove-member-modal-'.$member['id']"
                        />
                    @endif
                @endforeach
            </div>
        </div>
    </flux:card>

    @if ($invitations !== [])
        <flux:card>
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Pending Invitations</flux:heading>
                    <flux:subheading>Invitations that have not been accepted yet.</flux:subheading>
                </div>

                <div class="space-y-3">
                    @foreach ($invitations as $invitation)
                        <flux:card wire:key="invitation-{{ $invitation['code'] }}">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="space-y-1">
                                    <flux:heading size="md">{{ $invitation['email'] }}</flux:heading>
                                    <flux:text>{{ $invitation['role_label'] }}</flux:text>
                                </div>

                                @if ($this->permissions()->canCancelInvitation)
                                    <flux:modal.trigger name="cancel-invitation-{{ $invitation['code'] }}">
                                        <flux:button variant="ghost" icon="x-mark">Cancel</flux:button>
                                    </flux:modal.trigger>
                                @endif
                            </div>
                        </flux:card>

                        @if ($this->permissions()->canCancelInvitation)
                            <livewire:pages::teams.cancel-invitation-modal
                                :team="$team"
                                :invitation-code="$invitation['code']"
                                :invitation-email="$invitation['email']"
                                :modal-name="'cancel-invitation-'.$invitation['code']"
                                :key="'cancel-invitation-modal-'.$invitation['code']"
                            />
                        @endif
                    @endforeach
                </div>
            </div>
        </flux:card>
    @endif

    @if ($this->permissions()->canDeleteTeam && ! $teamData['is_personal'])
        <flux:card>
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Delete Team</flux:heading>
                    <flux:subheading>Permanently delete this team.</flux:subheading>
                </div>

                <flux:modal.trigger name="delete-team">
                    <flux:button variant="danger">Delete Team</flux:button>
                </flux:modal.trigger>
            </div>
        </flux:card>
    @endif

    @if ($this->permissions()->canCreateInvitation)
        <livewire:pages::teams.invite-member-modal :team="$team" />
    @endif

    @if ($this->permissions()->canDeleteTeam && ! $teamData['is_personal'])
        <livewire:pages::teams.delete-team-modal :team="$team" />
    @endif
</div>
