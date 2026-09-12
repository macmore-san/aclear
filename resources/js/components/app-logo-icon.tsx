import type { SVGAttributes } from 'react';

/**
 * AClear mark — a water drop set inside a seal ring, read as a stamped
 * certificate mark rather than a decorative icon (this system's whole
 * register-and-seal visual language in one glyph).
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <circle
                cx="20"
                cy="20"
                r="18.5"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.6"
            />
            <path
                d="M20 8.5c4.8 6.1 8.2 10.9 8.2 14.9a8.2 8.2 0 1 1-16.4 0c0-4 3.4-8.8 8.2-14.9Z"
                fill="currentColor"
            />
        </svg>
    );
}
