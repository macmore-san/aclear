import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PermissionController from '@/actions/App/Http/Controllers/Admin/PermissionController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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

type Permission = { id: number; name: string };

export default function Permissions({
    permissions,
}: {
    permissions: Permission[];
}) {
    const can = useCan();
    const [editing, setEditing] = useState<Permission | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState<Permission | null>(null);

    const form = useForm<{ name: string }>({ name: '' });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setDialogOpen(true);
    }

    function openEdit(permission: Permission) {
        setEditing(permission);
        form.setData({ name: permission.name });
        form.clearErrors();
        setDialogOpen(true);
    }

    function submit() {
        const options = { onSuccess: () => setDialogOpen(false) };

        if (editing) {
            form.put(PermissionController.update.url(editing), options);
        } else {
            form.post(PermissionController.store.url(), options);
        }
    }

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Permissions" />

            <div className="flex items-center justify-between">
                <Heading
                    title="Permissions"
                    description="Menu items generate their own view/create/edit/delete permissions automatically — add standalone ones here."
                />
                {can('permissions.create') && (
                    <Button onClick={openCreate}>
                        <Plus />
                        New permission
                    </Button>
                )}
            </div>

            <div className="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead className="w-0" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {permissions.map((permission) => (
                            <TableRow key={permission.id}>
                                <TableCell className="font-mono text-xs font-medium">
                                    {permission.name}
                                </TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        {can('permissions.edit') && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    openEdit(permission)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                        )}
                                        {can('permissions.delete') && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setDeleting(permission)
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

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogTitle>
                        {editing ? 'Edit permission' : 'New permission'}
                    </DialogTitle>

                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            placeholder="e.g. reports.export"
                        />
                        <InputError message={form.errors.name} />
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
                title="Delete permission"
                description={`Delete "${deleting?.name}"? Any role holding it will lose it.`}
                onConfirm={() => {
                    if (deleting) {
                        router.delete(
                            PermissionController.destroy.url(deleting),
                            { onSuccess: () => setDeleting(null) },
                        );
                    }
                }}
            />
        </div>
    );
}
