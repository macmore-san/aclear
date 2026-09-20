import type { Auth } from '@/types/auth';
import type { MenuItem } from '@/types/navigation';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            version: string;
            auth: Auth;
            sidebarOpen: boolean;
            menus: MenuItem[];
            [key: string]: unknown;
        };
    }
}
