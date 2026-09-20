import { Head, useHttp } from '@inertiajs/react';
import { Download, ExternalLink, Loader2, Printer, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import dtr from '@/routes/dtr';
import DtrController from '@/actions/App/Http/Controllers/DtrController';
import RegisterHeader from '@/components/register-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useQueryState } from '@/hooks/use-query-state';
import { cn } from '@/lib/utils';
import { currentCutoff, previousCutoff } from '@/lib/cutoff';
import type { BreadcrumbItem } from '@/types';

type Employee = {
    emp_code: string;
    name: string;
    position: string | null;
    department: string | null;
};

type DtrRow = {
    date: string;
    day_label: string;
    time_in: string | null;
    time_out: string | null;
    out_next_day: boolean;
    work_hrs: string;
    tardy: string;
    undertime: string;
    overtime: string;
    remarks: string;
    is_incomplete: boolean;
};

type DtrResult = {
    employee: {
        emp_code: string;
        full_name: string;
        position: string | null;
        department: string | null;
        start_time: string | null;
    } | null;
    emp_code: string;
    date_from: string;
    date_to: string;
    rows: DtrRow[];
    total_work_hrs: string;
    total_tardy_hrs: string;
    total_ut_hrs: string;
    total_ot_hrs: string;
    incomplete_logs: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Print DTR', href: dtr.index().url },
];

export default function DtrIndex({ employees }: { employees: Employee[] }) {
    const thisCutoff = useMemo(() => currentCutoff(), []);
    const lastCutoff = useMemo(() => previousCutoff(), []);

    const [dateFrom, setDateFrom] = useQueryState('date_from', thisCutoff.from);
    const [dateTo, setDateTo] = useQueryState('date_to', thisCutoff.to);
    const [search, setSearch] = useQueryState('q', '');
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [focus, setFocus] = useQueryState(
        'emp_code',
        employees[0]?.emp_code ?? '',
    );

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return employees;
        return employees.filter((e) =>
            [e.emp_code, e.name, e.position, e.department]
                .filter(Boolean)
                .some((v) => v!.toLowerCase().includes(q)),
        );
    }, [employees, search]);

    const allSelected = selected.size === 0; // empty selection = every active employee, matching the backend

    function toggle(code: string) {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(code)) next.delete(code);
            else next.add(code);
            return next;
        });
    }

    const preview = useHttp<Record<string, never>, DtrResult>({});

    useEffect(() => {
        if (!focus) return;
        preview
            .get(
                DtrController.show.url({
                    query: {
                        emp_code: focus,
                        date_from: dateFrom,
                        date_to: dateTo,
                    },
                }),
            )
            .catch(() => {});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [focus, dateFrom, dateTo]);

    const empCodes = allSelected ? undefined : Array.from(selected);

    function pdfUrl(inline: boolean) {
        return (
            dtr.pdf.url() +
            (() => {
                const params = new URLSearchParams();
                params.set('date_from', dateFrom);
                params.set('date_to', dateTo);
                if (inline) params.set('inline', '1');
                empCodes?.forEach((c) => params.append('emp_codes[]', c));
                return `?${params.toString()}`;
            })()
        );
    }

    const result = preview.response;

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Print DTR" />

            <RegisterHeader
                title="Print DTR"
                meta={`${dateFrom} → ${dateTo}`}
                actions={
                    <>
                        <Button variant="secondary" asChild>
                            <a
                                href={pdfUrl(true)}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink />
                                Open PDF
                            </a>
                        </Button>
                        <Button asChild>
                            <a href={pdfUrl(false)}>
                                <Download />
                                Download PDF
                            </a>
                        </Button>
                    </>
                }
            />

            <div className="grid gap-6 lg:grid-cols-[340px_1fr]">
                <div className="space-y-4">
                    <div className="border-border bg-card space-y-3 rounded-sm border p-4">
                        <Label className="text-muted-foreground text-xs tracking-wide uppercase">
                            Cut-off
                        </Label>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    dateFrom === thisCutoff.from &&
                                    dateTo === thisCutoff.to
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => {
                                    setDateFrom(thisCutoff.from);
                                    setDateTo(thisCutoff.to);
                                }}
                            >
                                This cut-off ({thisCutoff.label})
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    dateFrom === lastCutoff.from &&
                                    dateTo === lastCutoff.to
                                        ? 'default'
                                        : 'outline'
                                }
                                onClick={() => {
                                    setDateFrom(lastCutoff.from);
                                    setDateTo(lastCutoff.to);
                                }}
                            >
                                Last cut-off ({lastCutoff.label})
                            </Button>
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="grid gap-1">
                                <Label
                                    htmlFor="date_from"
                                    className="text-muted-foreground text-xs"
                                >
                                    From
                                </Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) =>
                                        setDateFrom(e.target.value)
                                    }
                                    className="font-mono text-xs"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label
                                    htmlFor="date_to"
                                    className="text-muted-foreground text-xs"
                                >
                                    To
                                </Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={dateTo}
                                    min={dateFrom}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    className="font-mono text-xs"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="border-border bg-card rounded-sm border">
                        <div className="border-border flex items-center justify-between border-b px-4 py-2.5">
                            <Label className="text-muted-foreground text-xs tracking-wide uppercase">
                                Staff{' '}
                                {allSelected
                                    ? '(all active)'
                                    : `(${selected.size} selected)`}
                            </Label>
                            {!allSelected && (
                                <button
                                    onClick={() => setSelected(new Set())}
                                    className="text-primary text-xs underline underline-offset-2"
                                >
                                    Select all active
                                </button>
                            )}
                        </div>
                        <div className="border-border relative border-b">
                            <Search className="text-muted-foreground absolute top-2.5 left-3 size-3.5" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search staff…"
                                className="w-full bg-transparent py-2 pr-3 pl-9 text-sm outline-none"
                            />
                        </div>
                        <div className="max-h-96 overflow-y-auto">
                            {filtered.map((e) => (
                                <button
                                    key={e.emp_code}
                                    onClick={() => setFocus(e.emp_code)}
                                    className={cn(
                                        'border-border flex w-full items-center gap-2.5 border-b px-3 py-2 text-left text-sm last:border-b-0',
                                        focus === e.emp_code && 'bg-accent',
                                    )}
                                >
                                    <Checkbox
                                        checked={
                                            allSelected ||
                                            selected.has(e.emp_code)
                                        }
                                        onClick={(ev) => ev.stopPropagation()}
                                        onCheckedChange={() =>
                                            toggle(e.emp_code)
                                        }
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {e.name}
                                    </span>
                                    <span className="text-muted-foreground font-mono text-[11px]">
                                        {e.emp_code}
                                    </span>
                                </button>
                            ))}
                            {filtered.length === 0 && (
                                <p className="text-muted-foreground px-3 py-6 text-center text-sm">
                                    No matching staff.
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <div
                    aria-live="polite"
                    aria-busy={preview.processing}
                    className="border-border bg-card rounded-sm border"
                >
                    <div className="border-border flex items-center justify-between border-b px-4 py-2.5">
                        <span className="text-sm font-medium">
                            {result?.employee?.full_name ?? 'Preview'}
                        </span>
                        {preview.processing && (
                            <Loader2
                                className="text-muted-foreground size-4 animate-spin"
                                aria-hidden="true"
                            />
                        )}
                    </div>

                    {!focus ? (
                        <p className="text-muted-foreground p-6 text-center text-sm">
                            No staff on file to preview.
                        </p>
                    ) : preview.processing && !result ? (
                        <div className="space-y-2 p-4">
                            {Array.from({ length: 6 }).map((_, i) => (
                                <Skeleton key={i} className="h-8 w-full" />
                            ))}
                        </div>
                    ) : result?.employee === null ? (
                        <p className="text-muted-foreground p-6 text-center text-sm">
                            This employee has no punches in this date range.
                        </p>
                    ) : result ? (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow className="border-b-2">
                                        <TableHead>Date</TableHead>
                                        <TableHead>Time in</TableHead>
                                        <TableHead>Time out</TableHead>
                                        <TableHead>Work</TableHead>
                                        <TableHead>Tardy</TableHead>
                                        <TableHead>UT</TableHead>
                                        <TableHead>OT</TableHead>
                                        <TableHead>Remarks</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {result.rows.map((row) => (
                                        <TableRow
                                            key={row.date}
                                            className={cn(
                                                row.is_incomplete &&
                                                    'bg-status-off/5',
                                            )}
                                        >
                                            <TableCell className="font-mono text-xs">
                                                {row.day_label}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {row.time_in ?? '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {row.time_out ?? '—'}
                                                {row.out_next_day && (
                                                    <span className="text-primary">
                                                        {' '}
                                                        (+1)
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {row.work_hrs}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {row.tardy}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {row.undertime}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {row.overtime}
                                            </TableCell>
                                            <TableCell className="text-status-off font-mono text-xs">
                                                {row.remarks}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <div className="border-border bg-secondary/40 grid grid-cols-2 gap-x-4 gap-y-1 border-t px-4 py-3 font-mono text-xs sm:grid-cols-5">
                                <span>
                                    Work {result.total_work_hrs || '0:00'}
                                </span>
                                <span>
                                    Tardy {result.total_tardy_hrs || '0:00'}
                                </span>
                                <span>UT {result.total_ut_hrs || '0:00'}</span>
                                <span>OT {result.total_ot_hrs || '0:00'}</span>
                                {result.incomplete_logs > 0 && (
                                    <span className="text-status-off">
                                        {result.incomplete_logs} incomplete
                                    </span>
                                )}
                            </div>
                        </div>
                    ) : null}
                </div>
            </div>

            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                <Printer className="size-4" />
                Printing includes{' '}
                {allSelected
                    ? 'every active employee'
                    : `${selected.size} selected employee(s)`}{' '}
                for {dateFrom} → {dateTo}.
            </div>
        </div>
    );
}

DtrIndex.layout = { breadcrumbs };
