import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    CalendarClock,
    ClipboardList,
    UploadCloud,
    Users,
} from 'lucide-react';
import dtrRoutes from '@/routes/dtr';
import employeesRoutes from '@/routes/employees';
import punchesRoutes from '@/routes/punches';
import RegisterHeader from '@/components/register-header';
import StatusSeal from '@/components/status-seal';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type LastUpload = { source_file: string | null; uploaded_at: string } | null;

type Props = {
    activeEmployees: number;
    missingStartTime: number;
    totalPunches: number;
    lastUpload: LastUpload;
    cutoff: { from: string; to: string };
    incompleteThisCutoff: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];

function StatCell({
    label,
    value,
    tone,
}: {
    label: string;
    value: string;
    tone?: 'warn';
}) {
    return (
        <div className="border-border bg-card rounded-sm border p-4">
            <p className="text-muted-foreground text-[11px] tracking-[0.1em] uppercase">
                {label}
            </p>
            <p
                className={
                    tone === 'warn'
                        ? 'text-status-off mt-1 font-mono text-2xl font-semibold'
                        : 'mt-1 font-mono text-2xl font-semibold'
                }
            >
                {value}
            </p>
        </div>
    );
}

export default function Dashboard({
    activeEmployees,
    missingStartTime,
    totalPunches,
    lastUpload,
    cutoff,
    incompleteThisCutoff,
}: Props) {
    const can = useCan();

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Dashboard" />

            <RegisterHeader
                title={`Current cut-off — ${cutoff.from} to ${cutoff.to}`}
                meta="Semi-monthly register"
            />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCell
                    label="Active employees"
                    value={String(activeEmployees)}
                />
                <StatCell
                    label="Missing start time"
                    value={String(missingStartTime)}
                    tone={missingStartTime > 0 ? 'warn' : undefined}
                />
                <StatCell
                    label="Incomplete this cut-off"
                    value={String(incompleteThisCutoff)}
                    tone={incompleteThisCutoff > 0 ? 'warn' : undefined}
                />
                <StatCell
                    label="Punch records on file"
                    value={totalPunches.toLocaleString()}
                />
            </div>

            {lastUpload ? (
                <div className="border-border bg-card flex items-center gap-2 rounded-sm border px-4 py-3 text-sm">
                    <CalendarClock className="text-muted-foreground size-4 shrink-0" />
                    <span>
                        Last upload:{' '}
                        <strong className="font-mono text-xs">
                            {lastUpload.source_file ?? 'unnamed file'}
                        </strong>{' '}
                        <span className="text-muted-foreground">
                            on {lastUpload.uploaded_at}
                        </span>
                    </span>
                </div>
            ) : (
                <div className="border-status-off/40 bg-status-off/10 text-status-off flex items-center gap-2 rounded-sm border px-4 py-3 text-sm">
                    <AlertTriangle className="size-4 shrink-0" />
                    No punch file has been uploaded yet.
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
                <div className="border-border bg-card flex flex-col gap-3 rounded-sm border p-4">
                    <div className="flex items-center gap-2">
                        <UploadCloud className="text-primary size-4" />
                        <span className="text-sm font-medium">
                            1. Upload punches
                        </span>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Drop the biometric export for this cut-off.
                    </p>
                    {can('punches.view') && (
                        <Button
                            variant="secondary"
                            size="sm"
                            asChild
                            className="mt-auto w-fit"
                        >
                            <Link href={punchesRoutes.index().url}>
                                Upload <ArrowRight />
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="border-border bg-card flex flex-col gap-3 rounded-sm border p-4">
                    <div className="flex items-center gap-2">
                        <Users className="text-primary size-4" />
                        <span className="text-sm font-medium">
                            2. Check employees
                        </span>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        {missingStartTime > 0 ? (
                            <>
                                <StatusSeal
                                    on={false}
                                    label={`${missingStartTime} missing start time`}
                                />
                            </>
                        ) : (
                            'Every active employee has a start time on file.'
                        )}
                    </p>
                    {can('employees.view') && (
                        <Button
                            variant="secondary"
                            size="sm"
                            asChild
                            className="mt-auto w-fit"
                        >
                            <Link href={employeesRoutes.index().url}>
                                Employees <ArrowRight />
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="border-border bg-card flex flex-col gap-3 rounded-sm border p-4">
                    <div className="flex items-center gap-2">
                        <ClipboardList className="text-primary size-4" />
                        <span className="text-sm font-medium">
                            3. Print DTRs
                        </span>
                    </div>
                    <p className="text-muted-foreground text-xs">
                        Generate this cut-off's payroll-ready DTR PDF.
                    </p>
                    {can('dtr.view') && (
                        <Button size="sm" asChild className="mt-auto w-fit">
                            <Link href={dtrRoutes.index().url}>
                                Print DTR <ArrowRight />
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

Dashboard.layout = { breadcrumbs };
