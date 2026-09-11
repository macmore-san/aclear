import { usePage } from '@inertiajs/react';

/**
 * Client-side permission check for hiding controls (create/edit/delete
 * buttons). The server enforces access independently via the
 * CheckMenuPermission middleware — this hook only controls what's rendered.
 */
export function useCan() {
    const { auth } = usePage().props;

    return (permission: string): boolean =>
        auth.isSuperAdmin || auth.permissions.includes(permission);
}
