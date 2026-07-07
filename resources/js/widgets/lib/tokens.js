// ── Design tokens for the interactive widget layer ───────────────────────────
// Brand/theme colours are read from the host page's CSS custom properties at
// runtime (so widgets follow the app palette + ?theme= switching). Science-
// semantic colours are FIXED constants — their meaning is physical, not brand.

export const SCI = {
    sun:     '#FBBF24',
    sunDeep: '#D97706',
    leaf:    '#34D399',
    water:   '#3EA7E0',
    mud:     '#8A7355',
    o2:      '#7FE3FF',
    slate:   '#7C93A8',
};

// Read a host CSS variable with a fallback (SSR-safe).
export function cssVar(name, fallback) {
    if (typeof window === 'undefined') return fallback;
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
}

// hex → [r,g,b]
function hx(h) {
    h = h.replace('#', '');
    if (h.length === 3) h = h.split('').map((c) => c + c).join('');
    return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
}

// mix two hex colours → rgb() string
export function mixHex(a, b, t) {
    t = Math.max(0, Math.min(1, t));
    const A = hx(a), B = hx(b);
    return `rgb(${A.map((v, i) => Math.round(v + (B[i] - v) * t)).join(',')})`;
}
