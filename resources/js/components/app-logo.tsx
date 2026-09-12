import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-sm">
                <AppLogoIcon className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate font-serif leading-tight font-semibold tracking-wide">
                    {name}
                </span>
                <span className="text-sidebar-foreground/60 truncate text-[11px] leading-tight">
                    Daily Time Records
                </span>
            </div>
        </>
    );
}
