<?php

/**
 * Update progress, served without booting Laravel.
 *
 * During an update the app is in maintenance mode (every Laravel route returns
 * 503) and its files are being replaced underneath it, so the Updates page cannot
 * poll a normal route for progress. This file deliberately has no dependencies —
 * it opens one JSON file and echoes it.
 *
 * The path below duplicates App\Support\UpdatePaths::stateDir(). That is
 * intentional: this script must keep working when the autoloader, config cache and
 * framework are mid-replacement. Change one, change the other.
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

$base = PHP_OS_FAMILY === 'Windows'
    ? (getenv('ProgramData') ?: 'C:\\ProgramData')
    : (sys_get_temp_dir().DIRECTORY_SEPARATOR.'aclear-state');

$statusFile = $base.DIRECTORY_SEPARATOR.'AClear'.DIRECTORY_SEPARATOR.'update-status.json';

if (! is_readable($statusFile)) {
    echo json_encode(['state' => 'idle']);

    exit;
}

$raw = file_get_contents($statusFile);
$decoded = json_decode((string) $raw, true);

// A partially-written file is normal: the updater rewrites this between steps and
// the page may read mid-write. Report it as still running rather than as an error,
// so one unlucky poll doesn't show the owner a failure that didn't happen.
echo is_array($decoded)
    ? (string) $raw
    : json_encode(['state' => 'running', 'label' => 'Working…']);
