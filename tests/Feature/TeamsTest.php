<?php

declare(strict_types=1);

use App\Actions\Teams\CreateTeam;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\get;

test('teams page requires authentication', function () {
    get('/teams')->assertRedirect('/auth/login');
});

test('authenticated users can create teams', function () {
    $user = User::factory()->create();

    app(CreateTeam::class)->handle($user, "{$user->name}'s Team", true);

    Livewire::actingAs($user)
        ->test('pages::teams.index')
        ->set('name', 'Acme Studio')
        ->call('createTeam');

    $team = Team::where('name', 'Acme Studio')->first();

    expect($team)->not->toBeNull();
    expect($user->fresh()->current_team_id)->toBe($team?->id);

    assertDatabaseHas('team_members', [
        'team_id' => $team?->id,
        'user_id' => $user->id,
        'role' => TeamRole::Owner->value,
    ]);
});

test('authenticated users can switch teams', function () {
    $user = User::factory()->create();

    $personalTeam = app(CreateTeam::class)->handle($user, "{$user->name}'s Team", true);
    $secondTeam = Team::factory()->create(['name' => 'Client Team']);

    $secondTeam->members()->attach($user->id, [
        'role' => TeamRole::Admin->value,
    ]);

    expect($user->fresh()->current_team_id)->toBe($personalTeam->id);

    Livewire::actingAs($user)
        ->test('pages::teams.index')
        ->call('switchTeam', $secondTeam->id);

    expect($user->fresh()->current_team_id)->toBe($secondTeam->id);
});

test('team owners can invite members', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Owner Team', true);

    Livewire::actingAs($owner)
        ->test('pages::teams.invite-member-modal', ['team' => $team])
        ->set('inviteEmail', 'invitee@example.com')
        ->set('inviteRole', TeamRole::Admin->value)
        ->call('createInvitation');

    $invitation = TeamInvitation::where('email', 'invitee@example.com')->first();

    expect($invitation)->not->toBeNull();
    expect($invitation?->role)->toBe(TeamRole::Admin);

    Notification::assertSentOnDemand(TeamInvitationNotification::class);
});

test('team owners can update member roles', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Owner Team', true);

    $team->members()->attach($member->id, [
        'role' => TeamRole::Member->value,
    ]);

    Livewire::actingAs($owner)
        ->test('pages::teams.edit', ['team' => $team])
        ->call('updateMember', $member->id, TeamRole::Admin->value);

    assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $member->id,
        'role' => TeamRole::Admin->value,
    ]);
});

test('team owners can remove members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Owner Team', true);
    $fallbackTeam = app(CreateTeam::class)->handle($member, 'Fallback Team', true);

    $team->members()->attach($member->id, [
        'role' => TeamRole::Member->value,
    ]);

    $member->switchTeam($team);

    Livewire::actingAs($owner)
        ->test('pages::teams.remove-member-modal', [
            'team' => $team,
            'memberId' => $member->id,
            'memberName' => $member->name,
        ])
        ->call('removeMember');

    assertDatabaseMissing('team_members', [
        'team_id' => $team->id,
        'user_id' => $member->id,
    ]);

    expect($member->fresh()->current_team_id)->toBe($fallbackTeam->id);
});

test('team owners can cancel invitations', function () {
    $owner = User::factory()->create();
    $team = app(CreateTeam::class)->handle($owner, 'Owner Team', true);

    $invitation = $team->invitations()->create([
        'email' => 'invitee@example.com',
        'role' => TeamRole::Member,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(3),
    ]);

    Livewire::actingAs($owner)
        ->test('pages::teams.cancel-invitation-modal', [
            'team' => $team,
            'invitationCode' => $invitation->code,
            'invitationEmail' => $invitation->email,
        ])
        ->call('cancelInvitation');

    assertDatabaseMissing('team_invitations', [
        'id' => $invitation->id,
    ]);
});

test('invited users can accept invitations', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $team = app(CreateTeam::class)->handle($owner, 'Owner Team', true);

    $invitation = $team->invitations()->create([
        'email' => $invitee->email,
        'role' => TeamRole::Admin,
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(3),
    ]);

    Livewire::actingAs($invitee)
        ->test('pages::teams.accept-invitation', ['invitation' => $invitation])
        ->call('acceptInvitation');

    assertDatabaseHas('team_members', [
        'team_id' => $team->id,
        'user_id' => $invitee->id,
        'role' => TeamRole::Admin->value,
    ]);

    expect($invitee->fresh()->current_team_id)->toBe($team->id);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});
