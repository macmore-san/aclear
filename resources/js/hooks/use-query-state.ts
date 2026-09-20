import { useCallback, useState } from 'react';

/**
 * A `useState` that mirrors its value into the URL's query string via
 * `history.replaceState` — no navigation, no Inertia request, no re-render
 * from a route change. Lets filters/selections on a page be bookmarked or
 * shared without turning every keystroke into a server round-trip.
 */
export function useQueryState(key: string, defaultValue: string) {
    const [value, setValue] = useState(() => {
        if (typeof window === 'undefined') return defaultValue;

        return (
            new URLSearchParams(window.location.search).get(key) ?? defaultValue
        );
    });

    const set = useCallback(
        (next: string) => {
            setValue(next);

            const url = new URL(window.location.href);
            if (next === defaultValue || next === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, next);
            }
            window.history.replaceState(window.history.state, '', url);
        },
        [key, defaultValue],
    );

    return [value, set] as const;
}
