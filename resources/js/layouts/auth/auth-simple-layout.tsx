import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="bg-background flex min-h-svh flex-col items-center justify-center gap-6 bg-[radial-gradient(ellipse_at_bottom_left,color-mix(in_oklab,var(--primary)_35%,transparent),transparent_60%),linear-gradient(100deg,color-mix(in_oklab,var(--primary)_55%,white)_0%,color-mix(in_oklab,var(--primary)_12%,white)_45%,white_75%)] p-6 md:p-10 dark:bg-none">
            <div className="w-full max-w-sm">
                {/* The register masthead — the same header block every
                    authenticated page opens on, so the login screen already
                    reads as this system's document, not a generic auth form. */}
                <div className="border-border bg-card/80 shadow-sm backdrop-blur-sm rounded-2xl border">
                    <div className="border-border flex flex-col items-center gap-3 border-b px-6 py-8 text-center">
                        <Link
                            href={home()}
                            className="text-primary flex flex-col items-center gap-2"
                        >
                            {/* The full lockup, not the square mark: this page has
                                the room for the wordmark, and nothing else here
                                spells out the station's name on screen. */}
                            <img
                                src="/logo-full.png"
                                alt=""
                                width={600}
                                height={578}
                                className="h-20 w-auto rounded-md"
                            />
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
