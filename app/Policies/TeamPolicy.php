<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

final class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'team:update');
    }

    public function addMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'member:add');
    }

    public function updateMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'member:update');
    }

    public function removeMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'member:remove');
    }

    public function inviteMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'invitation:create');
    }

    public function cancelInvitation(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, 'invitation:cancel');
    }

    public function delete(User $user, Team $team): bool
    {
        return ! $team->is_personal && $user->hasTeamPermission($team, 'team:delete');
    }
}
