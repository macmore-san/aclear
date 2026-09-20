import { Head, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    FileUp,
    Loader2,
    OctagonAlert,
    Package,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import UpdateController from '@/actions/App/Http/Controllers/Admin/UpdateController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';

type UpdateStatus = {
    state: 'idle' | 'queued' | 'running' | 'done' | 'failed';
    step?: number;
    total?: number;
    label?: string;
    version?: string;
    error?: string | null;
    backup?: string | null;
    rollback?: string | null;
};

type Props = {
    currentVersion: string;
    lastStatus: UpdateStatus | null;
    statusUrl: string;
    maxUploadMb: number;
};

const IN_PROGRESS = ['queued', 'running'];

export default function Updates({
    currentVersion,
    lastStatus,
    statusUrl,
    maxUploadMb,
}: Props) {
    const can = useCan();
    const fileRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [status, setStatus] = useState<UpdateStatus | null>(lastStatus);

    const form = useForm<{ release: File | null }>({ release: null });

    const running = status !== null && IN_PROGRESS.includes(status.state);

    // Polled against a flat PHP file, not a route: the app is in maintenance mode
    // for the whole update and its files are being replaced, so nothing that boots
    // Laravel can answer. See public/update-status.php.
    useEffect(() => {
        if (!running) return;

        const timer = setInterval(async () => {
            try {
                const response = await fetch(statusUrl, { cache: 'no-store' });
                setStatus(await response.json());
            } catch {
                // A refused connection is expected while the server restarts
                // mid-update — keep polling rather than declaring failure.
            }
        }, 2000);

        return () => clearInterval(timer);
    }, [running, statusUrl]);

    function submit() {
        if (!file) return;

        form.post(UpdateController.store.url(), { forceFormData: true });
    }

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Updates" />

            <Heading
                title="Updates"
                description="Install a new AClear release. The app goes offline for a few minutes while it installs, and the database is backed up first."
            />

            <div className="border-border bg-card/80 shadow-sm backdrop-blur-sm flex items-center gap-3 rounded-lg border px-5 py-4">
                <Package className="text-primary size-5 shrink-0" />
                <div>
                    <p className="text-sm font-medium">
                        Installed version{' '}
                        <span className="font-mono">{currentVersion}</span>
                    </p>
                    <p className="text-muted-foreground text-xs">
                        Your developer sends you an{' '}
                        <span className="font-mono">aclear-vX.Y.Z.zip</span>{' '}
                        file for each new version.
                    </p>
                </div>
            </div>

            {running && <Progress status={status!} />}

            {status?.state === 'done' && (
                <div
                    role="status"
                    aria-live="polite"
                    className="border-status-on/40 bg-status-on/10 text-status-on flex items-start gap-2 rounded-lg border px-4 py-3 text-sm"
                >
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                    <span>
                        Updated to{' '}
                        <strong className="font-mono">{status.version}</strong>.
                        Reload the page to see the new version.
                    </span>
                </div>
            )}

            {status?.state === 'failed' && <Failure status={status} />}

            {can('updates.create') && !running && (
                <div className="border-border bg-card/80 shadow-sm backdrop-blur-sm space-y-4 rounded-lg border p-5">
                    <div>
                        <label
                            htmlFor="release"
                            className="text-sm font-medium"
                        >
                            Release file
                        </label>
                        <p className="text-muted-foreground text-xs">
                            Choose the .zip your developer sent you. This server
                            accepts uploads up to {maxUploadMb} MB.
                        </p>
                    </div>

                    {maxUploadMb > 0 && maxUploadMb < 128 && (
                        <div className="border-status-off/40 bg-status-off/10 text-status-off flex items-start gap-2 rounded-2xl border px-3 py-2 text-xs">
                            <AlertTriangle
                                className="mt-0.5 size-3.5 shrink-0"
                                aria-hidden="true"
                            />
                            <span>
                                This server only accepts {maxUploadMb} MB
                                uploads, and a release is around 40 MB. Ask your
                                developer to raise{' '}
                                <span className="font-mono">
                                    upload_max_filesize
                                </span>{' '}
                                and{' '}
                                <span className="font-mono">post_max_size</span>{' '}
                                in php.ini before uploading.
                            </span>
                        </div>
                    )}

                    <input
                        ref={fileRef}
                        id="release"
                        type="file"
                        accept=".zip"
                        onChange={(e) => {
                            const chosen = e.target.files?.[0] ?? null;
                            setFile(chosen);
                            // Set here, not in submit(): setData is a state
                            // update, so posting straight after it sends stale
                            // data. Same sequencing as the punches uploader.
                            form.setData('release', chosen);
                            form.clearErrors();
                        }}
                        className="text-sm"
                    />

                    <InputError message={form.errors.release} />

                    <Button
                        onClick={submit}
                        disabled={!file || form.processing}
                    >
                        {form.processing ? (
                            <>
                                <Loader2
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                                Uploading…
                            </>
                        ) : (
                            <>
                                <FileUp className="size-4" aria-hidden="true" />
                                Install update
                            </>
                        )}
                    </Button>

                    <p className="text-muted-foreground border-border border-t pt-3 text-xs">
                        Do this at closing time, not mid-shift — nobody can use
                        AClear until it finishes.
                    </p>
                </div>
            )}
        </div>
    );
}

function Progress({ status }: { status: UpdateStatus }) {
    const step = status.step ?? 0;
    const total = status.total ?? 6;

    return (
        <div
            aria-live="polite"
            aria-busy="true"
            className="border-primary/30 bg-primary/5 space-y-3 rounded-lg border px-5 py-4"
        >
            <div className="flex items-center gap-2 text-sm font-medium">
                <Loader2 className="size-4 animate-spin" aria-hidden="true" />
                {status.label ?? 'Working…'}
            </div>
            <div
                className="bg-secondary h-1.5 w-full overflow-hidden rounded-full"
                role="progressbar"
                aria-valuenow={step}
                aria-valuemin={0}
                aria-valuemax={total}
            >
                <div
                    className="bg-primary h-full transition-[width] duration-500"
                    style={{ width: `${(step / total) * 100}%` }}
                />
            </div>
            <p className="text-muted-foreground text-xs">
                Step {step} of {total} — keep this page open and don't turn the
                PC off.
            </p>
        </div>
    );
}

function Failure({ status }: { status: UpdateStatus }) {
    return (
        <div
            role="alert"
            aria-live="polite"
            className="border-destructive/30 bg-destructive/10 space-y-2 rounded-lg border px-4 py-3 text-sm"
        >
            <div className="text-destructive flex items-start gap-2">
                <OctagonAlert className="mt-0.5 size-4 shrink-0" />
                <span>
                    <strong>The update stopped.</strong> {status.error}
                </span>
            </div>
            <div className="text-muted-foreground flex items-start gap-2 text-xs">
                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                <span>
                    Contact support and send them this screen. Nothing was lost
                    — these were saved before anything changed:
                    {status.backup && (
                        <>
                            <br />
                            Database backup:{' '}
                            <span className="font-mono">{status.backup}</span>
                        </>
                    )}
                    {status.rollback && (
                        <>
                            <br />
                            Previous version:{' '}
                            <span className="font-mono">{status.rollback}</span>
                        </>
                    )}
                </span>
            </div>
        </div>
    );
}
