<?php

namespace App\Support;

/**
 * Filesystem locations the updater uses.
 *
 * Everything here deliberately lives OUTSIDE the application directory: a release
 * zip contains `storage/`, so extracting one wipes anything staged under
 * `storage/app/` — including the status file the Updates page is polling at that
 * exact moment.
 *
 * `public/update-status.php` resolves `stateDir()` with its own copy of this logic,
 * because it must answer while the app is in maintenance mode and half-overwritten,
 * i.e. without booting Laravel. Keep the two in step — it is a contract, not logic.
 */
class UpdatePaths
{
    /** Windows ships to %ProgramData%; the fallback keeps this testable off-Windows. */
    public static function stateDir(): string
    {
        $base = PHP_OS_FAMILY === 'Windows'
            ? (getenv('ProgramData') ?: 'C:\\ProgramData')
            : (sys_get_temp_dir().DIRECTORY_SEPARATOR.'aclear-state');

        return $base.DIRECTORY_SEPARATOR.'AClear';
    }

    public static function statusFile(): string
    {
        return self::stateDir().DIRECTORY_SEPARATOR.'update-status.json';
    }

    /** Staged upload waiting to be applied. */
    public static function stagingDir(): string
    {
        return self::stateDir().DIRECTORY_SEPARATOR.'staging';
    }

    /** Pre-extract snapshots of the install, for manual recovery of a bad update. */
    public static function rollbackDir(): string
    {
        return self::stateDir().DIRECTORY_SEPARATOR.'rollback';
    }
}
