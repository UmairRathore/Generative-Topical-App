import React, { createContext, useContext } from 'react';

// ── Figure URL context ───────────────────────────────────────────────────────
// question_figure blocks store only a reference (question + image path) — never
// a URL. The server mints fresh, viewer-bound signed URLs per view and hands
// them down here as { imagePath: url }. Blocks look themselves up by path.
// Nothing here is ever written back into the document.

const FigureUrlContext = createContext({});

export function FigureUrlProvider({ value, children }) {
    return <FigureUrlContext.Provider value={value || {}}>{children}</FigureUrlContext.Provider>;
}

export function useFigureUrl(imagePath) {
    const map = useContext(FigureUrlContext);
    return imagePath ? (map[imagePath] || null) : null;
}
