<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-admin', function () {
    $role = Role::where('name', 'Super Admin')->first();

    if ($role === null) {
        $this->error('The Super Admin role does not exist yet. Run `php artisan db:seed --force` first.');

        return 1;
    }

    $input = [
        'name' => $this->ask('Name'),
        'email' => $this->ask('Email'),
        'password' => $this->secret('Password'),
        'password_confirmation' => $this->secret('Confirm password'),
    ];

    // Same rules as creating a user from Access Control → Users.
    $rules = new class
    {
        use PasswordValidationRules, ProfileValidationRules;

        /** @return array<string, mixed> */
        public function all(): array
        {
            return [...$this->profileRules(), 'password' => $this->passwordRules()];
        }
    };

    $validator = Validator::make($input, $rules->all());

    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $message) {
            $this->error($message);
        }

        return 1;
    }

    $user = User::create($validator->safe()->only(['name', 'email', 'password']));
    $user->assignRole($role);

    $this->info("Super Admin {$user->email} created.");

    return 0;
})->purpose('Create a Super Admin account (production install)');

Artisan::command('app:backup', function () {
    /** @var array{driver: string, host: string, port: int|string, database: string, username: string, password: string} $db */
    $db = config('database.connections.'.config('database.default'));

    if ($db['driver'] !== 'mysql') {
        $this->error('app:backup only supports MySQL.');

        return 1;
    }

    $root = rtrim((string) config('dtr.backup.path'), '/\\');
    $dir = $root.DIRECTORY_SEPARATOR.now()->format('Y-m-d_His');
    File::ensureDirectoryExists($dir);

    // Credentials go in a throwaway option file so the password never appears on the command line.
    $quote = fn (int|string $value): string => '"'.addcslashes((string) $value, '"\\').'"';
    $optionFile = (string) tempnam(sys_get_temp_dir(), 'aclear');
    File::put($optionFile, implode("\n", [
        '[client]',
        'user='.$quote($db['username']),
        'password='.$quote($db['password']),
        'host='.$quote($db['host']),
        'port='.$db['port'],
    ])."\n");

    try {
        $result = Process::timeout(900)->run([
            (string) config('dtr.backup.mysqldump'),
            '--defaults-extra-file='.$optionFile,
            '--single-transaction',
            '--routines',
            '--no-tablespaces',
            '--result-file='.$dir.DIRECTORY_SEPARATOR.'database.sql',
            $db['database'],
        ]);
    } finally {
        File::delete($optionFile);
    }

    if ($result->failed()) {
        File::deleteDirectory($dir);
        $this->error('Backup FAILED: '.trim($result->errorOutput()));

        return 1;
    }

    // APP_KEY is required to decrypt two-factor secrets after a restore.
    if (File::exists(base_path('.env'))) {
        File::copy(base_path('.env'), $dir.DIRECTORY_SEPARATOR.'.env');
    }

    $cutoff = now()->subDays((int) config('dtr.backup.keep_days'))->format('Y-m-d_His');

    foreach (File::directories($root) as $old) {
        $name = basename($old);

        // Only prune folders this command created: BACKUP_PATH may be a shared Drive folder.
        if (preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $name) === 1 && $name < $cutoff) {
            File::deleteDirectory($old);
        }
    }

    $this->info("Backup saved to {$dir}");

    return 0;
})->purpose('Dump the MySQL database (plus .env) to BACKUP_PATH and prune old backups');
