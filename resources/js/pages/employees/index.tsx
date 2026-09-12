import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, FileUp, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import employees from '@/routes/employees';
import punches from '@/routes/punches';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import RegisterHeader from '@/components/register-header';
import StatusSeal from '@/components/status-seal';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { useFlashToast } from '@/hooks/use-flash-toast';
import type { BreadcrumbItem } from '@/types';

type Employee = {
    id: number;
    emp_code: string;
    name: string;
    position: string | null;
    department: string | null;
    start_time: string | null;
    is_active: boolean;
};

type EmployeeForm = {
    emp_code: string;
    name: string;
    position: string;
    department: string;
    start_time: string;
    is_active: boolean;
};

const emptyForm: EmployeeForm = {
    emp_code: '',
    name: '',
    position: '',
    department: '',
    start_time: '',
    is_active: true,
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Employees', href: employees.index().url },
];

export default function EmployeesIndex({
    employees: roster,
}: {
    employees: Employee[];
}) {
    useFlashToast();
    const can = useCan();
    const [search, setSearch] = useState('');
    const [editing, setEditing] = useState<Employee | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState<Employee | null>(null);

    const form = useForm<EmployeeForm>(emptyForm);

    const filtered = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) return roster;
        return roster.filter((e) =>
            [e.emp_code, e.name, e.position, e.department]
                .filter(Boolean)
                .some((v) => v!.toLowerCase().includes(q)),
        );
    }, [roster, search]);

    const missingStartTime = roster.filter(
        (e) => e.is_active && !e.start_time,
    ).length;

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setDialogOpen(true);
    }

    function openEdit(employee: Employee) {
        setEditing(employee);
        form.setData({
            emp_code: employee.emp_code,
            name: employee.name,
            position: employee.position ?? '',
            department: employee.department ?? '',
            start_time: employee.start_time?.slice(0, 5) ?? '',
            is_active: employee.is_active,
        });
        form.clearErrors();
        setDialogOpen(true);
    }

    function submit() {
        const options = { onSuccess: () => setDialogOpen(false) };

        if (editing) {
            form.put(EmployeeController.update.url(editing.id), options);
        } else {
            form.post(EmployeeController.store.url(), options);
        }
    }

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Employees" />

            <RegisterHeader
                title="Employees"
                meta={`${roster.length} on file · ${roster.filter((e) => e.is_active).length} active`}
                actions={
                    can('employees.create') && (
                        <Button onClick={openCreate}>
                            <Plus />
                            New employee
                        </Button>
                    )
                }
            />

            {missingStartTime > 0 && (
                <div className="border-status-off/40 bg-status-off/10 text-status-off flex items-start gap-2 rounded-sm border px-4 py-3 text-sm">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <span>
                        <strong>{missingStartTime}</strong> active employee
                        {missingStartTime === 1 ? '' : 's'} have no start time
                        on file — tardy cannot be computed for them until one is
                        set.
                    </span>
                </div>
            )}

            <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search by code, name, position, department…"
                className="max-w-sm"
            />

            {roster.length === 0 ? (
                <div className="border-border bg-card rounded-sm border px-6 py-12 text-center">
                    <p className="font-serif text-lg font-semibold">
                        No employees on file yet
                    </p>
                    <p className="text-muted-foreground mx-auto mt-1.5 max-w-md text-sm">
                        Employees are added here, or automatically the first
                        time their name appears in an uploaded punch file.
                    </p>
                    <Button asChild className="mt-4">
                        <Link href={punches.index().url}>
                            <FileUp />
                            Go to Upload Punches
                        </Link>
                    </Button>
                </div>
            ) : (
                <div className="border-border overflow-hidden rounded-sm border">
                    <Table>
                        <TableHeader>
                            <TableRow className="border-b-2">
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Start time</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.map((employee) => (
                                <TableRow key={employee.id}>
                                    <TableCell className="font-mono text-xs">
                                        {employee.emp_code}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {employee.name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {employee.position || '—'}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {employee.department || '—'}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {employee.start_time ? (
                                            employee.start_time.slice(0, 5)
                                        ) : (
                                            <span className="text-status-off">
                                                not set
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <StatusSeal
                                            on={employee.is_active}
                                            label={
                                                employee.is_active
                                                    ? 'active'
                                                    : 'inactive'
                                            }
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            {can('employees.edit') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        openEdit(employee)
                                                    }
                                                >
                                                    <Pencil />
                                                </Button>
                                            )}
                                            {can('employees.delete') && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        setDeleting(employee)
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogTitle>
                        {editing ? 'Edit employee' : 'New employee'}
                    </DialogTitle>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="emp_code">Employee code</Label>
                            <Input
                                id="emp_code"
                                value={form.data.emp_code}
                                onChange={(e) =>
                                    form.setData('emp_code', e.target.value)
                                }
                                placeholder="Matches the clock's Ac-No"
                                className="font-mono"
                            />
                            <InputError message={form.errors.emp_code} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Full name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="position">Position</Label>
                            <Input
                                id="position"
                                value={form.data.position}
                                onChange={(e) =>
                                    form.setData('position', e.target.value)
                                }
                            />
                            <InputError message={form.errors.position} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="department">Department</Label>
                            <Input
                                id="department"
                                value={form.data.department}
                                onChange={(e) =>
                                    form.setData('department', e.target.value)
                                }
                            />
                            <InputError message={form.errors.department} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="start_time">Start time</Label>
                            <Input
                                id="start_time"
                                type="time"
                                value={form.data.start_time}
                                onChange={(e) =>
                                    form.setData('start_time', e.target.value)
                                }
                                className="font-mono"
                            />
                            <p className="text-muted-foreground text-xs">
                                The only basis for tardy — leave blank and it's
                                never computed.
                            </p>
                            <InputError message={form.errors.start_time} />
                        </div>

                        <div className="flex items-center gap-2 self-end pb-2">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            <Label htmlFor="is_active" className="font-normal">
                                Active (included in DTR printing)
                            </Label>
                        </div>
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setDialogOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            {editing ? 'Save' : 'Create'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title="Delete employee"
                description={`Delete ${deleting?.name}? Their punch history stays on file, but they will drop off DTR printing.`}
                onConfirm={() => {
                    if (deleting) {
                        router.delete(
                            EmployeeController.destroy.url(deleting.id),
                            { onSuccess: () => setDeleting(null) },
                        );
                    }
                }}
            />
        </div>
    );
}

EmployeesIndex.layout = { breadcrumbs };
