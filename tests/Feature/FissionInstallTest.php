<?php

declare(strict_types=1);

use App\Console\Commands\FissionInstall;
use Laravel\Prompts\Prompt;

beforeEach(function (): void {
    Prompt::fake();
});

it('returns false without erroring when a command fails', function (): void {
    $command = new FissionInstall;
    $command->setLaravel(app());

    $result = new ReflectionMethod($command, 'runTask')
        ->invoke($command, 'Testing task', ['exit 1']);

    expect($result)->toBeFalse();
});

it('reports the task label when all commands succeed', function (): void {
    $command = new FissionInstall;
    $command->setLaravel(app());

    $result = new ReflectionMethod($command, 'runTask')
        ->invoke($command, 'Testing task', ['exit 0']);

    expect($result)->toBeTrue();

    Prompt::assertOutputContains('Testing task');
});
