<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

test('new users can register', function (): void {
    Livewire::test('pages::auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue();
    assertDatabaseHas('users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $team = Team::query()->first();

    expect($team)->not->toBeNull()
        ->and($team?->name)->toBe("Test User's Team")
        ->and($team?->is_personal)->toBeTrue()
        ->and($user->current_team_id)->toBe($team?->id);

    assertDatabaseHas('team_members', [
        'team_id' => $team?->id,
        'user_id' => $user->id,
        'role' => 'owner',
    ]);
});

test('name field is required', function (): void {
    Livewire::test('pages::auth.register')
        ->set('name', '')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['name' => 'required']);
});

test('email field is required', function (): void {
    Livewire::test('pages::auth.register')
        ->set('name', 'Test User')
        ->set('email', '')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['email' => 'required']);
});

test('email must be valid', function (): void {
    Livewire::test('pages::auth.register')
        ->set('name', 'Test User')
        ->set('email', 'not-an-email')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['email']);
});

test('email must be unique', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    Livewire::test('pages::auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasErrors(['email' => 'unique']);
});

test('password field is required', function (): void {
    Livewire::test('pages::auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('register')
        ->assertHasErrors(['password' => 'required']);
});

test('password must be confirmed', function (): void {
    Livewire::test('pages::auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'different-password')
        ->call('register')
        ->assertHasErrors(['password' => 'confirmed']);
});
