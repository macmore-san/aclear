<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUploadRequest;
use App\Support\UpdatePaths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/updates', [
            'currentVersion' => config('app.version'),
            'lastStatus' => $this->readStatus(),
            // The page polls this flat file rather than a Laravel route: during an
            // update the app is in maintenance mode (503) and its files are being
            // replaced, so nothing that boots the framework can answer.
            'statusUrl' => url('/update-status.php'),
            // Surfaced in the UI because a release zip is ~40MB and PHP silently
            // discards an over-limit upload — the request simply arrives with no
            // file, which reads as a mystery rather than a misconfiguration.
            'maxUploadMb' => $this->maxUploadMb(),
        ]);
    }

    /** Smallest of the two php.ini limits an upload has to clear, in MB. */
    private function maxUploadMb(): int
    {
        $toBytes = function (string $value): float {
            $value = trim($value);
            $unit = strtolower(substr($value, -1));
            $number = (float) $value;

            return match ($unit) {
                'g' => $number * 1024 ** 3,
                'm' => $number * 1024 ** 2,
                'k' => $number * 1024,
                default => $number,
            };
        };

        $limits = array_filter([
            $toBytes((string) ini_get('upload_max_filesize')),
            $toBytes((string) ini_get('post_max_size')),
        ]);

        return $limits === [] ? 0 : (int) floor(min($limits) / 1024 ** 2);
    }

    public function store(UpdateUploadRequest $request): RedirectResponse
    {
        $version = (string) $request->input('resolved_version');

        $staging = UpdatePaths::stagingDir();
        File::ensureDirectoryExists($staging);

        // Only one release may be staged at a time — a leftover zip from an aborted
        // attempt must never be what the updater picks up.
        foreach (File::glob($staging.DIRECTORY_SEPARATOR.'*.zip') as $stale) {
            File::delete($stale);
        }

        $zipPath = $staging.DIRECTORY_SEPARATOR."aclear-v{$version}.zip";
        $request->file('release')->move($staging, basename($zipPath));

        $this->writeStatus([
            'state' => 'queued',
            'step' => 0,
            'total' => 6,
            'label' => 'Starting the updater…',
            'version' => $version,
            'started_at' => now()->toIso8601String(),
        ]);

        $this->launchUpdater($zipPath);

        return to_route('updates.index');
    }

    /**
     * Hand the update to a detached process and return immediately.
     *
     * The updater replaces the very files serving this request, so it cannot run
     * inside it — PHP cannot overwrite what it is executing, and the request would
     * be killed by `artisan down` partway through. scripts/update.bat solves the
     * same problem by re-launching itself from %TEMP%.
     */
    private function launchUpdater(string $zipPath): void
    {
        $script = base_path('run-update.ps1');
        $command = sprintf(
            'powershell -ExecutionPolicy Bypass -WindowStyle Hidden -File "%s" -Zip "%s" -App "%s"',
            $script,
            $zipPath,
            base_path(),
        );

        // `start /B` detaches so the updater outlives this request; popen/pclose
        // rather than Process::run, which would block until it finished.
        $handle = popen('start /B '.$command, 'r');

        if (is_resource($handle)) {
            pclose($handle);
        }
    }

    /** @return array<string, mixed>|null */
    private function readStatus(): ?array
    {
        $file = UpdatePaths::statusFile();

        if (! File::exists($file)) {
            return null;
        }

        $decoded = json_decode((string) File::get($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param  array<string, mixed>  $status */
    private function writeStatus(array $status): void
    {
        File::ensureDirectoryExists(UpdatePaths::stateDir());
        File::put(UpdatePaths::statusFile(), (string) json_encode($status, JSON_PRETTY_PRINT));
    }
}
