import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon alt="" className="size-8 shrink-0 rounded-sm" />
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
