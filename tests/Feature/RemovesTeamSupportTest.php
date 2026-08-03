<?php

declare(strict_types=1);

use App\Support\RemovesTeamSupport;
use Illuminate\Filesystem\Filesystem;

test('team support remover deletes team files and restores base stubs', function (): void {
    $filesystem = new Filesystem();
    $basePath = sys_get_temp_dir().'/fission-team-removal-'.bin2hex(random_bytes(8));
    $stubPath = resource_path('stubs/no-teams');

    $filesystem->ensureDirectoryExists($basePath.'/app/Actions/Teams');
    $filesystem->ensureDirectoryExists($basePath.'/app/Notifications/Teams');
    $filesystem->ensureDirectoryExists($basePath.'/resources/views/pages/teams');
    $filesystem->ensureDirectoryExists($basePath.'/app/Models');
    $filesystem->ensureDirectoryExists($basePath.'/resources/views/components');
    $filesystem->ensureDirectoryExists($basePath.'/resources/views/pages/auth');
    $filesystem->ensureDirectoryExists($basePath.'/resources/views/pages/profile');
    $filesystem->ensureDirectoryExists($basePath.'/routes');
    $filesystem->ensureDirectoryExists($basePath.'/database/migrations');

    $filesystem->put($basePath.'/app/Actions/Teams/CreateTeam.php', 'team');
    $filesystem->put($basePath.'/app/Notifications/Teams/TeamInvitation.php', 'team');
    $filesystem->put($basePath.'/resources/views/pages/teams/index.blade.php', 'team');
    $filesystem->put($basePath.'/app/Models/Team.php', 'team');
    $filesystem->put($basePath.'/database/migrations/2026_03_30_000003_create_teams_table.php', 'team');
    $filesystem->put($basePath.'/app/Models/User.php', 'changed');
    $filesystem->put($basePath.'/resources/views/components/⚡navigation.blade.php', 'changed');
    $filesystem->put($basePath.'/resources/views/pages/auth/⚡register.blade.php', 'changed');
    $filesystem->put($basePath.'/resources/views/pages/profile/⚡index.blade.php', 'changed');
    $filesystem->put($basePath.'/routes/web.php', 'changed');

    new RemovesTeamSupport($filesystem, $basePath, $stubPath)->handle();

    expect($filesystem->exists($basePath.'/app/Actions/Teams/CreateTeam.php'))->toBeFalse()
        ->and($filesystem->exists($basePath.'/app/Models/Team.php'))->toBeFalse()
        ->and($filesystem->exists($basePath.'/database/migrations/2026_03_30_000003_create_teams_table.php'))->toBeFalse()
        ->and($filesystem->get($basePath.'/routes/web.php'))->toBe($filesystem->get($stubPath.'/routes/web.php'))
        ->and($filesystem->get($basePath.'/app/Models/User.php'))->toBe($filesystem->get($stubPath.'/app/Models/User.php'))
        ->and($filesystem->get($basePath.'/resources/views/components/⚡navigation.blade.php'))->toBe($filesystem->get($stubPath.'/resources/views/components/⚡navigation.blade.php'));

    $filesystem->deleteDirectory($basePath);
});
