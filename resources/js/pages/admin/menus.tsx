import {
    closestCenter,
    DndContext,
    type DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, router, useForm } from '@inertiajs/react';
import { GripVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import MenuController from '@/actions/App/Http/Controllers/Admin/MenuController';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import { resolveMenuIcon } from '@/lib/icons';
import { cn } from '@/lib/utils';

type MenuRow = {
    id: number;
    parent_id: number | null;
    title: string;
    icon: string | null;
    route: string | null;
    permission: string | null;
    sort: number;
};

type PageProps = {
    items: MenuRow[];
    icons: string[];
    routes: string[];
};

type MenuForm = {
    title: string;
    icon: string;
    route: string;
    permission: string;
    parent_id: string;
};

const emptyForm: MenuForm = {
    title: '',
    icon: '',
    route: '',
    permission: '',
    parent_id: '',
};

// ponytail: drag reorders siblings; moving an item to a different parent is a
// dialog field (Parent), not a drag target. A full projection-based nested
// drag (dnd-kit's sortable-tree recipe) is real complexity for a 2-level menu
// list — add it if flat reordering + a parent field stops being enough.
export default function Menus({
    items: initialItems,
    icons,
    routes,
}: PageProps) {
    const can = useCan();
    const [menus, setMenus] = useState(initialItems);
    const [editing, setEditing] = useState<MenuRow | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [deleting, setDeleting] = useState<MenuRow | null>(null);

    const form = useForm<MenuForm>(emptyForm);

    const topLevel = useMemo(
        () =>
            menus
                .filter((m) => m.parent_id === null)
                .sort((a, b) => a.sort - b.sort),
        [menus],
    );
    const childrenOf = (parentId: number) =>
        menus
            .filter((m) => m.parent_id === parentId)
            .sort((a, b) => a.sort - b.sort);
    const hasChildren = (id: number) => menus.some((m) => m.parent_id === id);

    const sensors = useSensors(
        useSensor(PointerSensor, {
            activationConstraint: { distance: 4 },
        }),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const activeItem = menus.find((m) => m.id === active.id);
        const overItem = menus.find((m) => m.id === over.id);

        if (
            !activeItem ||
            !overItem ||
            activeItem.parent_id !== overItem.parent_id
        ) {
            return;
        }

        const siblings = (
            activeItem.parent_id === null
                ? topLevel
                : childrenOf(activeItem.parent_id)
        ).map((m) => m.id);
        const from = siblings.indexOf(active.id as number);
        const to = siblings.indexOf(over.id as number);
        const reordered = arrayMove(siblings, from, to);

        const items = reordered.map((id, sort) => ({
            id,
            parent_id: activeItem.parent_id,
            sort,
        }));

        setMenus((current) =>
            current.map((m) => {
                const update = items.find((i) => i.id === m.id);
                return update ? { ...m, sort: update.sort } : m;
            }),
        );

        router.put(
            MenuController.reorder.url(),
            { items },
            { preserveScroll: true, preserveState: true },
        );
    }

    function openCreate(parentId: number | null = null) {
        setEditing(null);
        form.reset();
        form.setData({
            ...emptyForm,
            parent_id: parentId ? String(parentId) : '',
        });
        form.clearErrors();
        setDialogOpen(true);
    }

    function openEdit(menu: MenuRow) {
        setEditing(menu);
        form.setData({
            title: menu.title,
            icon: menu.icon ?? '',
            route: menu.route ?? '',
            permission: menu.permission ?? '',
            parent_id: menu.parent_id ? String(menu.parent_id) : '',
        });
        form.clearErrors();
        setDialogOpen(true);
    }

    function submit() {
        const options = { onSuccess: () => setDialogOpen(false) };
        const payload = {
            ...form.data,
            icon: form.data.icon || null,
            route: form.data.route || null,
            permission: form.data.permission || null,
            parent_id: form.data.parent_id || null,
        };

        form.transform(() => payload);

        if (editing) {
            form.put(MenuController.update.url(editing), options);
        } else {
            form.post(MenuController.store.url(), options);
        }
    }

    const editingHasChildren = editing ? hasChildren(editing.id) : false;

    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Menus" />

            <div className="flex items-center justify-between">
                <Heading
                    title="Menus"
                    description="Drag to reorder within a level. Menus nest at most two levels deep."
                />
                {can('menus.create') && (
                    <Button onClick={() => openCreate()}>
                        <Plus />
                        New menu item
                    </Button>
                )}
            </div>

            <div className="rounded-lg border p-2">
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext
                        items={topLevel.map((m) => m.id)}
                        strategy={verticalListSortingStrategy}
                    >
                        <div className="space-y-1">
                            {topLevel.map((item) => (
                                <div key={item.id}>
                                    <MenuRowView
                                        item={item}
                                        onEdit={() => openEdit(item)}
                                        onDelete={() => setDeleting(item)}
                                        onAddChild={() => openCreate(item.id)}
                                        canEdit={can('menus.edit')}
                                        canDelete={can('menus.delete')}
                                        canCreate={can('menus.create')}
                                    />
                                    {childrenOf(item.id).length > 0 && (
                                        <SortableContext
                                            items={childrenOf(item.id).map(
                                                (c) => c.id,
                                            )}
                                            strategy={
                                                verticalListSortingStrategy
                                            }
                                        >
                                            <div className="ml-8 space-y-1 border-l pl-2">
                                                {childrenOf(item.id).map(
                                                    (child) => (
                                                        <MenuRowView
                                                            key={child.id}
                                                            item={child}
                                                            onEdit={() =>
                                                                openEdit(child)
                                                            }
                                                            onDelete={() =>
                                                                setDeleting(
                                                                    child,
                                                                )
                                                            }
                                                            canEdit={can(
                                                                'menus.edit',
                                                            )}
                                                            canDelete={can(
                                                                'menus.delete',
                                                            )}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </SortableContext>
                                    )}
                                </div>
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogTitle>
                        {editing ? 'Edit menu item' : 'New menu item'}
                    </DialogTitle>

                    <div className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={form.data.title}
                                onChange={(e) =>
                                    form.setData('title', e.target.value)
                                }
                            />
                            <InputError message={form.errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Icon</Label>
                            <Select
                                value={form.data.icon || undefined}
                                onValueChange={(value) =>
                                    form.setData('icon', value)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue placeholder="No icon" />
                                </SelectTrigger>
                                <SelectContent>
                                    {icons.map((icon) => {
                                        const Icon = resolveMenuIcon(icon);
                                        return (
                                            <SelectItem key={icon} value={icon}>
                                                <span className="flex items-center gap-2">
                                                    {Icon && (
                                                        <Icon className="size-4" />
                                                    )}
                                                    {icon}
                                                </span>
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.icon} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="route">
                                Route (leave blank for a group)
                            </Label>
                            <Input
                                id="route"
                                list="menu-routes"
                                value={form.data.route}
                                onChange={(e) =>
                                    form.setData('route', e.target.value)
                                }
                                placeholder="e.g. users.index"
                            />
                            <datalist id="menu-routes">
                                {routes.map((route) => (
                                    <option key={route} value={route} />
                                ))}
                            </datalist>
                            <InputError message={form.errors.route} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="permission">
                                Permission prefix (optional)
                            </Label>
                            <Input
                                id="permission"
                                value={form.data.permission}
                                onChange={(e) =>
                                    form.setData('permission', e.target.value)
                                }
                                placeholder="e.g. reports"
                            />
                            <p className="text-muted-foreground text-xs">
                                Creates view/create/edit/delete permissions
                                automatically.
                            </p>
                            <InputError message={form.errors.permission} />
                        </div>

                        <div className="grid gap-2">
                            <Label>Parent</Label>
                            <Select
                                value={form.data.parent_id || 'none'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'parent_id',
                                        value === 'none' ? '' : value,
                                    )
                                }
                                disabled={editingHasChildren}
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        None (top-level)
                                    </SelectItem>
                                    {topLevel
                                        .filter((m) => m.id !== editing?.id)
                                        .map((m) => (
                                            <SelectItem
                                                key={m.id}
                                                value={String(m.id)}
                                            >
                                                {m.title}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {editingHasChildren && (
                                <p className="text-muted-foreground text-xs">
                                    This item has sub-items, so it can&apos;t be
                                    nested under another menu.
                                </p>
                            )}
                            <InputError message={form.errors.parent_id} />
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
                title="Delete menu item"
                description={`Delete "${deleting?.title}"? ${
                    deleting && hasChildren(deleting.id)
                        ? 'Remove its sub-items first.'
                        : 'This cannot be undone.'
                }`}
                onConfirm={() => {
                    if (deleting) {
                        router.delete(MenuController.destroy.url(deleting), {
                            onSuccess: () => setDeleting(null),
                        });
                    }
                }}
            />
        </div>
    );
}

function MenuRowView({
    item,
    onEdit,
    onDelete,
    onAddChild,
    canEdit,
    canDelete,
    canCreate,
}: {
    item: MenuRow;
    onEdit: () => void;
    onDelete: () => void;
    onAddChild?: () => void;
    canEdit: boolean;
    canDelete: boolean;
    canCreate?: boolean;
}) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id: item.id });
    const Icon = resolveMenuIcon(item.icon);

    return (
        <div
            ref={setNodeRef}
            style={{
                transform: CSS.Transform.toString(transform),
                transition,
            }}
            className={cn(
                'bg-card flex items-center gap-2 rounded-md border px-2 py-1.5',
                isDragging && 'z-10 scale-[1.02] shadow-md',
            )}
        >
            <button
                type="button"
                className="text-muted-foreground cursor-grab touch-none active:cursor-grabbing"
                {...attributes}
                {...listeners}
            >
                <GripVertical className="size-4" />
            </button>
            {Icon && <Icon className="text-muted-foreground size-4" />}
            <span className="flex-1 text-sm font-medium">{item.title}</span>
            {item.route && (
                <span className="text-muted-foreground font-mono text-xs">
                    {item.route}
                </span>
            )}
            <div className="flex gap-1">
                {onAddChild && canCreate && (
                    <Button variant="ghost" size="icon" onClick={onAddChild}>
                        <Plus />
                    </Button>
                )}
                {canEdit && (
                    <Button variant="ghost" size="icon" onClick={onEdit}>
                        <Pencil />
                    </Button>
                )}
                {canDelete && (
                    <Button variant="ghost" size="icon" onClick={onDelete}>
                        <Trash2 />
                    </Button>
                )}
            </div>
        </div>
    );
}
