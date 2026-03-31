<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Public routes
Route::livewire('/', 'pages::dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Auth routes
Route::middleware('guest')->group(function () {
    Route::livewire('/auth/login', 'pages::auth.login')->name('login');
    Route::livewire('/auth/register', 'pages::auth.register')->name('register');
    Route::livewire('/auth/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/auth/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
});

// Profile routes
Route::middleware(['auth'])->group(function () {
    Route::livewire('/profile', 'pages::profile.index')->name('profile.update');
    Route::livewire('/teams', 'pages::teams.index')->name('teams.index');
    Route::livewire('/teams/{team}', 'pages::teams.edit')->name('teams.edit');
    Route::livewire('/invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('teams.invitations.accept');
});

Route::livewire('/{team}/playground', 'pages::playground')
    ->middleware(['auth', 'verified'])
    ->can('view', 'team')
    ->name('playground');

// Email verification notice route
Route::get('/verify-email', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

// Email verification handler route
Route::get('/verify-email/{id}/{hash}', function () {
    // This will be handled by Laravel's built-in email verification
})->middleware(['auth', 'signed'])->name('verification.verify');
