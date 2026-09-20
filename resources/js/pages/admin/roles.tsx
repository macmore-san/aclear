import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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

const PROTECTED_ROLE = 'Super Admin';

type Role = {
    id: number;
    name: string;
    permissions: { id: number; name: string }[];
};

type PageProps = {
    roles: Role[];
    permissions: string[];
};

type RoleForm = { name: string; permissions: string[] };

/** Group flat "resource.ability" permission names by resource for the checkbox grid. */
function groupPermissions(permissions: string[]) {
    const groups = new Map<string, string[]>();

    for (const permission of permissions) {
        const [resource] = permission.split('.');
        groups.set(resource, [...(groups.get(resource) ?? []), permission]);
    }

    return groups;
}

export default function Roles({ roles, permissions }: PageProps) {
    const can = useCan();
    const [editing, setEditing] = useState<Role | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState<Role | null>(null);
    const groups = groupPermissions(permissions);

    const form = useForm<RoleForm>({ name: '', permissions: [] });

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setDialogOpen(true);
    }

    function openEdit(role: Role) {
        setEditing(role);
        form.setData({
            name: role.name,
            permissions: role.permissions.map((p) => p.name),
        });
        form.clearErrors();
        setDialogOpen(true);
    }

    function submit() {
        const options = { onSuccess: () => setDialogOpen(false) };

        if (editing) {
            form.put(RoleController.update.url(editing), options);
        } else {
            form.post(RoleController.store.url(), options);
        }
    }

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Roles" />

            <div className="flex items-center justify-between">
                <Heading
                    title="Roles"
                    description="Roles bundle permissions together so they can be assigned to users at once."
                />
                {can('roles.create') && (
                    <Button onClick={openCreate}>
                        <Plus />
                        New role
                    </Button>
                )}
            </div>

            <div className="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Permissions</TableHead>
                            <TableHead className="w-0" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {roles.map((role) => {
                            const isProtected = role.name === PROTECTED_ROLE;

                            return (
                                <TableRow key={role.id}>
                                    <TableCell className="font-medium">
                                        {role.name}
                                        {isProtected && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2"
                                            >
                                                built-in
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {isProtected
                                            ? 'All permissions'
                                            : `${role.permissions.length} permission${role.permissions.length === 1 ? '' : 's'}`}
                                    </TableCell>
                                    <TableCell>
                                        {!isProtected && (
                                            <div className="flex justify-end gap-1">
                                                {can('roles.edit') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit ${role.name}`}
                                                        onClick={() =>
                                                            openEdit(role)
                                                        }
                                                    >
                                                        <Pencil />
                                                    </Button>
                                                )}
                                                {can('roles.delete') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${role.name}`}
                                                        onClick={() =>
                                                            setDeleting(role)
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-xl">
                    <DialogTitle>
                        {editing ? 'Edit role' : 'New role'}
                    </DialogTitle>

                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Name</Label>
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
                            <Label>Permissions</Label>
                            <div className="max-h-72 space-y-3 overflow-y-auto rounded-md border p-3">
                                {[...groups.entries()].map(
                                    ([resource, perms]) => (
                                        <div key={resource}>
                                            <p className="mb-1 text-xs font-medium capitalize">
                                                {resource}
                                            </p>
                                            <div className="flex flex-wrap gap-3">
                                                {perms.map((permission) => (
                                                    <label
                                                        key={permission}
                                                        className="flex items-center gap-2 text-sm"
                                                    >
                                                        <Checkbox
                                                            checked={form.data.permissions.includes(
                                                                permission,
                                                            )}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                form.setData(
                                                                    'permissions',
                                                                    checked
                                                                        ? [
                                                                              ...form
                                                                                  .data
                                                                                  .permissions,
                                                                              permission,
                                                                          ]
                                                                        : form.data.permissions.filter(
                                                                              (
                                                                                  p,
                                                                              ) =>
                                                                                  p !==
                                                                                  permission,
                                                                          ),
                                                                )
                                                            }
                                                        />
                                                        {permission.split(
                                                            '.',
                                                        )[1] ?? permission}
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                            <InputError message={form.errors.permissions} />
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
                title="Delete role"
                description={`Delete the "${deleting?.name}" role? Users keep their account but lose these permissions.`}
                onConfirm={() => {
                    if (deleting) {
                        router.delete(RoleController.destroy.url(deleting), {
                            onSuccess: () => setDeleting(null),
                        });
                    }
                }}
            />
        </div>
    );
}
