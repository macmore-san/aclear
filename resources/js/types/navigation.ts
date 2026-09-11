import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

/** A node in the DB-driven, permission-filtered sidebar tree (see Menu::tree()). */
export type MenuItem = {
    id: number;
    title: string;
    icon: string | null;
    href: string | null;
    children: MenuItem[];
};
