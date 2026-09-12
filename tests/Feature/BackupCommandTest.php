<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

// No RefreshDatabase: the command never queries the database, and switching the default
// connection to mysql below would make its teardown try to reach a real MySQL server.
class BackupCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'aclear-backup-test-'.uniqid();
        File::ensureDirectoryExists($this->root);

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.password' => 'se"cret\\pw',
            'dtr.backup.path' => $this->root,
            'dtr.backup.keep_days' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_dumps_without_exposing_the_password_and_prunes_only_its_own_old_folders(): void
    {
        Process::fake();
        File::ensureDirectoryExists($this->root.'/2020-01-01_000000'); // old backup
        File::ensureDirectoryExists($this->root.'/Owner Documents');   // not ours

        $this->artisan('app:backup')->assertExitCode(0);

        Process::assertRan(function (PendingProcess $process): bool {
            $command = implode(' ', (array) $process->command);

            return str_contains($command, '--single-transaction')
                && str_contains($command, '--defaults-extra-file=')
                && ! str_contains($command, 'cret');
        });

        $this->assertDirectoryDoesNotExist($this->root.'/2020-01-01_000000');
        $this->assertDirectoryExists($this->root.'/Owner Documents');
        $this->assertCount(2, File::directories($this->root)); // today's backup + the owner's folder
        $this->assertSame([], glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'aclear*.tmp') ?: []);
    }

    public function test_a_failed_dump_leaves_no_partial_backup(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'Access denied', exitCode: 2));

        $this->artisan('app:backup')->assertExitCode(1);

        $this->assertSame([], File::directories($this->root));
    }

    public function test_it_refuses_non_mysql_connections(): void
    {
        config(['database.default' => 'sqlite']);

        $this->artisan('app:backup')->assertExitCode(1);
    }
}
