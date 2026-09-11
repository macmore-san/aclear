import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import PasswordInput from '@/components/password-input';
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
import type { Paginated, User } from '@/types';
import { index as usersIndex } from '@/routes/users';

type UserRow = User & { roles: { id: number; name: string }[] };

type PageProps = {
    users: Paginated<UserRow>;
    roles: string[];
    filters: { search?: string };
};

type UserForm = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    roles: string[];
};

const emptyForm: UserForm = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
};

export default function Users({ users, roles, filters }: PageProps) {
    const can = useCan();
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState<UserRow | null>(null);

    const form = useForm<UserForm>(emptyForm);

    function openCreate() {
        setEditing(null);
        form.reset();
        form.clearErrors();
        setDialogOpen(true);
    }

    function openEdit(user: UserRow) {
        setEditing(user);
        form.setData({
            name: user.name,
            email: user.email,
            password: '',
            password_confirmation: '',
            roles: user.roles.map((role) => role.name),
        });
        form.clearErrors();
        setDialogOpen(true);
    }

    function submit() {
        const options = { onSuccess: () => setDialogOpen(false) };

        if (editing) {
            form.put(UserController.update.url(editing), options);
        } else {
            form.post(UserController.store.url(), options);
        }
    }

    function submitSearch(value: string) {
        setSearch(value);
        router.get(
            usersIndex.url(),
            { search: value || undefined },
            { preserveState: true, replace: true },
        );
    }

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Users" />

            <div className="flex items-center justify-between">
                <Heading
                    title="Users"
                    description="Accounts are created by admins only — there is no public sign-up."
                />
                {can('users.create') && (
                    <Button onClick={openCreate}>
                        <Plus />
                        New user
                    </Button>
                )}
            </div>

            <Input
                value={search}
                onChange={(e) => submitSearch(e.target.value)}
                placeholder="Search by name or email…"
                className="max-w-sm"
            />

            <div className="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Roles</TableHead>
                            <TableHead className="w-0" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {users.data.map((user) => (
                            <TableRow key={user.id}>
                                <TableCell className="font-medium">
                                    {user.name}
                                </TableCell>
                                <TableCell className="text-muted-foreground font-mono text-xs">
                                    {user.email}
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap gap-1">
                                        {user.roles.map((role) => (
                                            <Badge
                                                key={role.id}
                                                variant="secondary"
                                            >
                                                {role.name}
                                            </Badge>
                                        ))}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="flex justify-end gap-1">
                                        {can('users.edit') && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => openEdit(user)}
                                            >
                                                <Pencil />
                                            </Button>
                                        )}
                                        {can('users.delete') && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    setDeleting(user)
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

            <PaginationLinks paginated={users} />

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogTitle>
                        {editing ? 'Edit user' : 'New user'}
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
                                autoComplete="name"
                            />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                autoComplete="username"
                            />
                            <InputError message={form.errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">
                                {editing
                                    ? 'New password (optional)'
                                    : 'Password'}
                            </Label>
                            <PasswordInput
                                id="password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                                autoComplete="new-password"
                            />
                            <InputError message={form.errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                value={form.data.password_confirmation}
                                onChange={(e) =>
                                    form.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                autoComplete="new-password"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label>Roles</Label>
                            <div className="flex flex-wrap gap-4">
                                {roles.map((role) => (
                                    <label
                                        key={role}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.roles.includes(
                                                role,
                                            )}
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    'roles',
                                                    checked
                                                        ? [
                                                              ...form.data
                                                                  .roles,
                                                              role,
                                                          ]
                                                        : form.data.roles.filter(
                                                              (r) => r !== role,
                                                          ),
                                                )
                                            }
                                        />
                                        {role}
                                    </label>
                                ))}
                            </div>
                            <InputError message={form.errors.roles} />
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
                title="Delete user"
                description={`Delete ${deleting?.name}? This cannot be undone.`}
                onConfirm={() => {
                    if (deleting) {
                        router.delete(UserController.destroy.url(deleting), {
                            onSuccess: () => setDeleting(null),
                        });
                    }
                }}
            />
        </div>
    );
}
