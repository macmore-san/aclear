import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div className="w-full max-w-sm">
                {/* The register masthead — the same header block every
                    authenticated page opens on, so the login screen already
                    reads as this system's document, not a generic auth form. */}
                <div className="border-border bg-card rounded-sm border">
                    <div className="border-border flex flex-col items-center gap-3 border-b px-6 py-8 text-center">
                        <Link
                            href={home()}
                            className="text-primary flex flex-col items-center gap-2"
                        >
                            <div className="bg-primary text-primary-foreground flex size-11 items-center justify-center rounded-sm">
                                <AppLogoIcon className="size-6" />
                            </div>
                            <span className="sr-only">AClear</span>
                        </Link>
                        <div className="space-y-1">
                            <h1 className="font-serif text-xl font-semibold">
                                {title}
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                {description}
                            </p>
                        </div>
                    </div>
                    <div className="p-6">{children}</div>
                </div>
            </div>
        </div>
    );
}
