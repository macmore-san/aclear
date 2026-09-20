import { Head, Link, useHttp } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ClipboardList,
    FileUp,
    Loader2,
    OctagonAlert,
    UploadCloud,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import dtr from '@/routes/dtr';
import PunchImportController from '@/actions/App/Http/Controllers/PunchImportController';
import RegisterHeader from '@/components/register-header';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

const MAX_FILES = 7;

type FileResult = {
    name: string;
    detected_format: string | null;
    date_format_assumed: boolean;
    imported: number;
    duplicates: number;
    skipped_blank: number;
    skipped_no_time: number;
    skipped_unparsable: number;
    skipped: number;
    first_punch: string | null; // 'Y-m-d H:i:s'
    last_punch: string | null;
    error?: string;
};

type UploadResponse = {
    message: string;
    files: FileResult[];
    imported: number;
    skipped: number;
    totalPunches: number;
};

/** 'Y-m-d H:i:s' → 'Y-m-d'. */
const punchDate = (s: string) => s.slice(0, 10);

// Counts up to `target` instead of jumping — the running total is the one
// figure this register exists to move, so its change earns a beat of motion.
function useCountUp(target: number, durationMs = 700) {
    const [display, setDisplay] = useState(target);
    const fromRef = useRef(target);

    useEffect(() => {
        const from = fromRef.current;
        fromRef.current = target;
        if (from === target) {
            setDisplay(target);
            return;
        }
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setDisplay(target);
            return;
        }

        const start = performance.now();
        let raf: number;
        const step = (now: number) => {
            const t = Math.min((now - start) / durationMs, 1);
            const eased = 1 - Math.pow(1 - t, 3);
            setDisplay(Math.round(from + (target - from) * eased));
            if (t < 1) raf = requestAnimationFrame(step);
        };
        raf = requestAnimationFrame(step);
        return () => cancelAnimationFrame(raf);
    }, [target, durationMs]);

    return display;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Upload Punches', href: PunchImportController.index.url() },
];

export default function PunchesIndex({
    totalPunches,
}: {
    totalPunches: number;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [results, setResults] = useState<FileResult[] | null>(null);
    const [dragOver, setDragOver] = useState(false);
    const [total, setTotal] = useState(totalPunches);
    const displayedTotal = useCountUp(total);

    const form = useHttp<{ files: File[] }, UploadResponse>({ files: [] });
    const uploading = form.processing;

    const addFiles = (incoming: File[]) => {
        const selected = incoming.slice(0, MAX_FILES);
        setFiles(selected);
        form.setData('files', selected);
        setResults(null);
        form.clearErrors();
    };

    const openBrowse = () => {
        if (!uploading) fileRef.current?.click();
    };

    const removeFile = (index: number) => {
        const next = files.filter((_, i) => i !== index);
        setFiles(next);
        form.setData('files', next);
        if (fileRef.current) fileRef.current.value = '';
    };

    const handleUpload = () => {
        if (files.length === 0) return;

        void form.post(PunchImportController.store.url(), {
            onSuccess: (response) => {
                setResults(response.files);
                setTotal(response.totalPunches);
                setFiles([]);
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    };

    const networkOrServerError =
        form.errors.files ?? (form.hasErrors ? 'Upload failed.' : null);

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Upload Punches" />

            <RegisterHeader
                title="Upload Punches"
                meta={`${displayedTotal.toLocaleString()} punch record${total === 1 ? '' : 's'} on file`}
            />

            <div className="border-border bg-card rounded-sm border p-5">
                <button
                    type="button"
                    tabIndex={uploading ? -1 : 0}
                    aria-disabled={uploading}
                    onClick={openBrowse}
                    onKeyDown={(e) => {
                        if (uploading) return;
                        if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            openBrowse();
                        }
                    }}
                    onDragOver={(e) => {
                        e.preventDefault();
                        if (!uploading) setDragOver(true);
                    }}
                    onDragLeave={() => setDragOver(false)}
                    onDrop={(e) => {
                        e.preventDefault();
                        setDragOver(false);
                        if (!uploading)
                            addFiles(Array.from(e.dataTransfer.files));
                    }}
                    className={cn(
                        'focus-visible:ring-ring flex cursor-pointer flex-col items-center gap-2 rounded-sm border-2 border-dashed px-6 py-10 text-center transition-colors focus-visible:ring-2 focus-visible:outline-none',
                        uploading && 'cursor-not-allowed opacity-50',
                        dragOver
                            ? 'border-primary bg-primary/5'
                            : 'border-border hover:border-muted-foreground/50',
                    )}
                >
                    <UploadCloud className="text-muted-foreground size-8" />
                    <p className="text-sm font-medium">
                        Drop the biometric export here, or{' '}
                        <span className="text-primary underline underline-offset-2">
                            browse
                        </span>
                    </p>
                    <p className="text-muted-foreground text-xs">
                        .csv / .txt &middot; up to {MAX_FILES} files &middot;
                        already-imported punches are skipped automatically
                    </p>
                </button>
                {/* Sibling, not a child — a <button> can't legally contain an <input>. */}
                <input
                    ref={fileRef}
                    type="file"
                    accept=".csv,.txt"
                    multiple
                    disabled={uploading}
                    onChange={(e) => {
                        addFiles(Array.from(e.target.files ?? []));
                        e.target.value = '';
                    }}
                    className="hidden"
                />

                {files.length > 0 && (
                    <ul className="mt-4 grid gap-1.5">
                        {files.map((f, i) => (
                            <li
                                key={i}
                                className="border-border flex items-center justify-between rounded-sm border border-dashed px-3 py-2 text-sm"
                            >
                                <span className="truncate">{f.name}</span>
                                <button
                                    onClick={() => removeFile(i)}
                                    disabled={uploading}
                                    aria-label={`Remove ${f.name}`}
                                    className="text-muted-foreground hover:text-destructive ml-2 shrink-0 disabled:opacity-50"
                                >
                                    <X className="size-3.5" />
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                <Button
                    onClick={handleUpload}
                    disabled={files.length === 0 || uploading}
                    className="mt-4"
                >
                    {uploading ? (
                        <>
                            <Loader2
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                            Uploading…
                        </>
                    ) : (
                        <>
                            <FileUp className="size-4" />
                            Upload
                            {files.length > 0 ? ` ${files.length} file(s)` : ''}
                        </>
                    )}
                </Button>

                {networkOrServerError && (
                    <div
                        role="alert"
                        aria-live="polite"
                        className="border-destructive/30 bg-destructive/10 text-destructive mt-4 flex items-start gap-2 rounded-sm border px-3 py-2 text-sm"
                    >
                        <OctagonAlert className="mt-0.5 size-4 shrink-0" />
                        <span>{networkOrServerError}</span>
                    </div>
                )}
            </div>

            {results && (
                <div
                    aria-live="polite"
                    className="border-border overflow-hidden rounded-sm border"
                >
                    <div className="border-border bg-secondary/50 border-b px-4 py-2.5 text-xs font-medium tracking-wide uppercase">
                        Import result
                    </div>
                    <div className="divide-border divide-y">
                        {results.map((r, i) =>
                            r.error ? (
                                <div
                                    key={i}
                                    className="text-destructive flex items-start gap-2 px-4 py-3 text-sm"
                                >
                                    <OctagonAlert className="mt-0.5 size-4 shrink-0" />
                                    <span>
                                        <strong className="font-mono text-xs">
                                            {r.name}
                                        </strong>
                                        : {r.error}
                                    </span>
                                </div>
                            ) : (
                                <div key={i} className="space-y-2 px-4 py-3">
                                    <div className="flex items-start gap-2 text-sm">
                                        <CheckCircle2 className="text-status-on mt-0.5 size-4 shrink-0" />
                                        <div className="min-w-0 space-y-0.5">
                                            <div>
                                                <strong className="font-mono text-xs">
                                                    {r.name}
                                                </strong>
                                                :{' '}
                                                <strong>
                                                    {r.imported.toLocaleString()}
                                                </strong>{' '}
                                                imported
                                                {r.duplicates > 0 && (
                                                    <>
                                                        ,{' '}
                                                        {r.duplicates.toLocaleString()}{' '}
                                                        already on file
                                                    </>
                                                )}
                                            </div>
                                            <div className="text-muted-foreground font-mono text-xs">
                                                {r.detected_format && (
                                                    <>
                                                        dates read as{' '}
                                                        {r.detected_format}
                                                    </>
                                                )}
                                                {r.first_punch &&
                                                    r.last_punch && (
                                                        <>
                                                            {' '}
                                                            &middot;{' '}
                                                            {punchDate(
                                                                r.first_punch,
                                                            )}{' '}
                                                            →{' '}
                                                            {punchDate(
                                                                r.last_punch,
                                                            )}
                                                        </>
                                                    )}
                                            </div>
                                        </div>
                                    </div>

                                    {r.date_format_assumed && (
                                        <div className="border-status-off/40 bg-status-off/10 text-status-off flex items-start gap-2 rounded-sm border px-3 py-2 text-xs">
                                            <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                            <span>
                                                The date format in this file was
                                                ambiguous — every row was read
                                                as{' '}
                                                <strong>
                                                    {r.detected_format}
                                                </strong>
                                                . Verify the date range above
                                                before printing DTRs from it.
                                            </span>
                                        </div>
                                    )}

                                    {(r.skipped_no_time > 0 ||
                                        r.skipped_unparsable > 0) && (
                                        <div className="border-status-off/40 bg-status-off/10 text-status-off flex items-start gap-2 rounded-sm border px-3 py-2 text-xs">
                                            <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                            <span>
                                                {r.skipped_no_time > 0 && (
                                                    <>
                                                        {r.skipped_no_time.toLocaleString()}{' '}
                                                        row(s) had a date but no
                                                        time of day.{' '}
                                                    </>
                                                )}
                                                {r.skipped_unparsable > 0 && (
                                                    <>
                                                        {r.skipped_unparsable.toLocaleString()}{' '}
                                                        row(s) had an unreadable
                                                        date.
                                                    </>
                                                )}{' '}
                                                Not imported.
                                            </span>
                                        </div>
                                    )}
                                </div>
                            ),
                        )}
                    </div>
                </div>
            )}

            <div className="border-border bg-card flex items-center justify-between rounded-sm border px-5 py-4">
                <div className="flex items-center gap-2 text-sm">
                    <ClipboardList className="text-muted-foreground size-4" />
                    Next: fix any employee missing a start time, then print
                    DTRs.
                </div>
                <Button variant="secondary" asChild>
                    <Link href={dtr.index().url}>Go to Print DTR</Link>
                </Button>
            </div>
        </div>
    );
}

PunchesIndex.layout = { breadcrumbs };
