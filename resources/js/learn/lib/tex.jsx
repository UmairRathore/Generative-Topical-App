import React, { useMemo } from 'react';
import katex from 'katex';
import 'katex/contrib/mhchem';   // registers \ce{...} for chemistry
import 'katex/dist/katex.min.css';

// ── LaTeX rendering (KaTeX) ──────────────────────────────────────────────────
// One shared math layer for the whole learning surface: the markdown renderer,
// the equation block, flashcards/memcards. KaTeX output is trusted/sanitised, so
// the ONLY dangerouslySetInnerHTML in this codebase is the math span here — the
// surrounding text stays React-escaped. throwOnError:false → a malformed formula
// degrades to red source text instead of crashing the note.
//
// mhchem gives chemistry: \ce{H2SO4}, \ce{6CO2 + 6H2O -> C6H12O6 + 6O2}.

export function renderTex(tex, displayMode = false) {
    try {
        return katex.renderToString(tex, {
            displayMode,
            throwOnError: false,
            strict: false,
            trust: false,
            output: 'htmlAndMathml',
        });
    } catch {
        return null; // caller falls back to the raw source
    }
}

/** A single formula (used by the equation block preview). */
export function Tex({ tex, display = false }) {
    const html = useMemo(() => renderTex(tex, display), [tex, display]);
    if (html == null) return <code>{tex}</code>;
    return <span className={display ? 'cw-tex-block' : 'cw-tex'} dangerouslySetInnerHTML={{ __html: html }} />;
}

// Split plain text into [text | $inline$ | $$display$$] segments. Order matters:
// match $$…$$ before $…$. Backslash-escaped \$ is treated as a literal dollar.
const MATH_RE = /\$\$([^$]+?)\$\$|(?<!\\)\$([^$\n]+?)(?<!\\)\$/g;

/**
 * Render a string that may contain inline/display math. Non-math runs are passed
 * through `renderText` (default: plain string) so callers can compose it with
 * their own inline formatting.
 */
export function MathText({ text, renderText }) {
    const nodes = useMemo(() => {
        const src = String(text ?? '');
        const out = [];
        let last = 0, m, i = 0;
        MATH_RE.lastIndex = 0;
        while ((m = MATH_RE.exec(src)) !== null) {
            if (m.index > last) out.push({ t: src.slice(last, m.index), k: i++ });
            const display = m[1] != null;
            const tex = (m[1] ?? m[2]).trim();
            const html = renderTex(tex, display);
            out.push({ math: true, html, tex, display, k: i++ });
            last = m.index + m[0].length;
        }
        if (last < src.length) out.push({ t: src.slice(last), k: i++ });
        return out;
    }, [text]);

    return (
        <>
            {nodes.map((n) => (n.math
                ? (n.html == null
                    ? <code key={n.k}>{n.tex}</code>
                    : <span key={n.k} className={n.display ? 'cw-tex-block' : 'cw-tex'} dangerouslySetInnerHTML={{ __html: n.html }} />)
                : <React.Fragment key={n.k}>{renderText ? renderText(n.t, n.k) : n.t}</React.Fragment>
            ))}
        </>
    );
}

/** True if the string contains any $…$ / $$…$$ math. */
export function hasMath(text) {
    MATH_RE.lastIndex = 0;
    return MATH_RE.test(String(text ?? ''));
}
