<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

final class RemovesTeamSupport
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ?string $basePath = null,
        private readonly ?string $stubPath = null,
    ) {}

    public function handle(): void
    {
        foreach ($this->directoriesToDelete() as $directory) {
            $this->files->deleteDirectory($this->path($directory));
        }

        foreach ($this->filesToDelete() as $file) {
            $this->files->delete($this->path($file));
        }

        foreach ($this->replacementFiles() as $target => $stub) {
            $this->files->ensureDirectoryExists(dirname($this->path($target)));
            $this->files->copy($this->stub($stub), $this->path($target));
        }
    }

    /**
     * @return list<string>
     */
    private function directoriesToDelete(): array
    {
        return [
            'app/Actions/Teams',
            'app/Notifications/Teams',
            'resources/views/pages/teams',
        ];
    }

    /**
     * @return list<string>
     */
    private function filesToDelete(): array
    {
        return [
            'app/Concerns/GeneratesUniqueTeamSlugs.php',
            'app/Concerns/HasTeams.php',
            'app/Enums/TeamRole.php',
            'app/Models/Membership.php',
            'app/Models/Team.php',
            'app/Models/TeamInvitation.php',
            'app/Policies/TeamPolicy.php',
            'app/Rules/TeamName.php',
            'app/Rules/UniqueTeamInvitation.php',
            'app/Support/TeamPermissions.php',
            'database/factories/TeamFactory.php',
            'database/factories/TeamInvitationFactory.php',
            'database/migrations/2026_03_30_000003_create_teams_table.php',
            'tests/Feature/TeamsTest.php',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function replacementFiles(): array
    {
        return [
            'app/Models/User.php' => 'app/Models/User.php',
            'resources/views/components/⚡navigation.blade.php' => 'resources/views/components/⚡navigation.blade.php',
            'resources/views/pages/auth/⚡register.blade.php' => 'resources/views/pages/auth/⚡register.blade.php',
            'resources/views/pages/profile/⚡index.blade.php' => 'resources/views/pages/profile/⚡index.blade.php',
            'routes/web.php' => 'routes/web.php',
            'tests/Feature/Auth/RegisterTest.php' => 'tests/Feature/Auth/RegisterTest.php',
        ];
    }

    private function path(string $relativePath): string
    {
        return mb_rtrim($this->basePath ?? base_path(), '/').'/'.$relativePath;
    }

    private function stub(string $relativePath): string
    {
        return mb_rtrim($this->stubPath ?? resource_path('stubs/no-teams'), '/').'/'.$relativePath;
    }
}
