import type { ImgHTMLAttributes } from 'react';

/**
 * The AClear brand mark — the wave-through-`A` logo on its blue ground.
 *
 * Raster, not a glyph: the source artwork is a JPEG with the blue baked in, so
 * this carries its own background and ignores `currentColor`. Don't wrap it in a
 * tinted tile and don't hand it `fill-*` / `text-*` classes — neither does anything.
 */
export default function AppLogoIcon({
    alt = 'AClear',
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return <img src="/logo-mark.png" alt={alt} {...props} />;
}
