<?php

declare(strict_types=1);

use App\Console\Commands\FissionInstall;
use Illuminate\Support\Facades\File;
use Laravel\Prompts\Prompt;

beforeEach(function (): void {
    Prompt::fake();
});

/**
 * Build an installer whose base path points at a scratch directory, so the Git
 * commands under test never touch the real project repository.
 */
function installerInScratchRepository(string $directory): FissionInstall
{
    File::ensureDirectoryExists($directory);
    app()->setBasePath($directory);

    putenv('GIT_AUTHOR_NAME=Fission');
    putenv('GIT_AUTHOR_EMAIL=fission@example.com');
    putenv('GIT_COMMITTER_NAME=Fission');
    putenv('GIT_COMMITTER_EMAIL=fission@example.com');

    $command = new FissionInstall;
    $command->setLaravel(app());

    new ReflectionProperty($command, 'initializeGit')->setValue($command, true);

    return $command;
}

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

it('initializes a git repository with an initial commit', function (): void {
    $directory = sys_get_temp_dir().'/fission-git-ok-'.uniqid();
    $command = installerInScratchRepository($directory);

    new ReflectionMethod($command, 'initializeGitRepository')->invoke($command);

    expect(File::isDirectory($directory.'/.git'))->toBeTrue()
        ->and(File::exists($directory.'/.gitignore'))->toBeTrue();

    Prompt::assertStrippedOutputContains('Git repository initialized');

    File::deleteDirectory($directory);
});

it('reports an error instead of claiming success when git init fails', function (): void {
    $directory = sys_get_temp_dir().'/fission-git-fail-'.uniqid();
    $command = installerInScratchRepository($directory);

    // A regular file at .git makes `git init` exit non-zero, deterministically.
    File::put($directory.'/.git', 'not a git directory');

    new ReflectionMethod($command, 'initializeGitRepository')->invoke($command);

    Prompt::assertStrippedOutputContains('Could not initialize a Git repository');
    Prompt::assertStrippedOutputDoesntContain('Git repository initialized');

    expect(File::exists($directory.'/.gitignore'))->toBeFalse();

    File::deleteDirectory($directory);
});
