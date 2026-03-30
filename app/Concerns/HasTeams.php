<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Support\TeamPermissions;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasTeams
{
    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function ownedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role')
            ->wherePivot('role', TeamRole::Owner->value)
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function personalTeam(): ?Team
    {
        return $this->teams()
            ->where('is_personal', true)
            ->first();
    }

    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()
            ->where('teams.id', $team->id)
            ->exists();
    }

    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->forceFill([
            'current_team_id' => $team->id,
        ])->save();

        $this->setRelation('currentTeam', $team);

        return true;
    }

    public function teamRole(Team $team): ?TeamRole
    {
        $role = $this->teams()
            ->where('teams.id', $team->id)
            ->first()
            ?->pivot
            ?->role;

        if (! is_string($role)) {
            return null;
        }

        return TeamRole::tryFrom($role);
    }

    public function hasTeamPermission(Team $team, string $permission): bool
    {
        return $this->teamRole($team)?->hasPermission($permission) ?? false;
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding !== null, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByDesc('is_personal')
            ->orderBy('name')
            ->first();
    }

    public function toTeamPermissions(Team $team): TeamPermissions
    {
        return new TeamPermissions(
            canUpdateTeam: $this->hasTeamPermission($team, 'team:update'),
            canDeleteTeam: $this->hasTeamPermission($team, 'team:delete'),
            canAddMember: $this->hasTeamPermission($team, 'member:add'),
            canUpdateMember: $this->hasTeamPermission($team, 'member:update'),
            canRemoveMember: $this->hasTeamPermission($team, 'member:remove'),
            canCreateInvitation: $this->hasTeamPermission($team, 'invitation:create'),
            canCancelInvitation: $this->hasTeamPermission($team, 'invitation:cancel'),
        );
    }

    /**
     * @return Collection<int, Team>
     */
    public function orderedTeams(): Collection
    {
        return $this->teams()
            ->orderByDesc('is_personal')
            ->orderBy('name')
            ->get();
    }
}
