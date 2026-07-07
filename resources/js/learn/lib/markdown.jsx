import React from 'react';
import { renderTex } from './tex.jsx';

// ── Markdown (lite) ──────────────────────────────────────────────────────────
// Dependency-free markdown → React. Text formatting builds React elements
// directly (no innerHTML). The ONE exception is LaTeX: $$…$$ / $…$ are rendered
// with KaTeX (trusted, sanitised output) so worked-solution maths shows properly
// in the Studio AND in notes (both render through this file). \ce{…} works too.
// Handles: # headings · - / * lists · 1. ordered lists · paragraphs · inline
// **bold**, *italic*, `code`, and $inline$ / $$display$$ maths.

function inline(text, keyBase) {
    const nodes = [];
    // Maths first so $$…$$ / $…$ win over * and `.
    const re = /(\$\$[^$]+?\$\$|\$[^$\n]+?\$|\*\*[^*]+\*\*|`[^`]+`|\*[^*]+\*)/g;
    let last = 0, m, i = 0;
    while ((m = re.exec(text)) !== null) {
        if (m.index > last) nodes.push(text.slice(last, m.index));
        const t = m[0];
        const key = `${keyBase}-${i}`;
        if (t.startsWith('$$') || t.startsWith('$')) {
            const display = t.startsWith('$$');
            const tex = (display ? t.slice(2, -2) : t.slice(1, -1)).trim();
            const html = renderTex(tex, display);
            nodes.push(html == null
                ? <code key={key}>{t}</code>
                : <span key={key} className={display ? 'cw-tex-block' : 'cw-tex'} dangerouslySetInnerHTML={{ __html: html }} />);
        } else if (t.startsWith('**')) nodes.push(<strong key={key}>{t.slice(2, -2)}</strong>);
        else if (t.startsWith('`')) nodes.push(<code key={key}>{t.slice(1, -1)}</code>);
        else nodes.push(<em key={key}>{t.slice(1, -1)}</em>);
        last = m.index + t.length; i++;
    }
    if (last < text.length) nodes.push(text.slice(last));
    return nodes;
}

export default function Markdown({ text }) {
    if (!text) return null;
    const lines = String(text).replace(/\r\n/g, '\n').split('\n');
    const out = [];
    let para = [];
    let list = null; // { type: 'ul' | 'ol', items: [] }
    let key = 0;

    const flushPara = () => {
        if (para.length) { out.push(<p key={key++}>{inline(para.join(' '), `p${key}`)}</p>); para = []; }
    };
    const flushList = () => {
        if (list) {
            const Tag = list.type;
            out.push(<Tag key={key++}>{list.items.map((it, ii) => <li key={ii}>{inline(it, `l${key}-${ii}`)}</li>)}</Tag>);
            list = null;
        }
    };
    const flushAll = () => { flushPara(); flushList(); };

    for (const raw of lines) {
        const line = raw.trimEnd();
        if (line.trim() === '') { flushAll(); continue; }

        const h = line.match(/^(#{1,4})\s+(.*)$/);
        if (h) {
            flushAll();
            const Tag = ['h2', 'h3', 'h4', 'h4'][h[1].length - 1];
            out.push(<Tag key={key++}>{inline(h[2], `h${key}`)}</Tag>);
            continue;
        }

        const ul = line.match(/^\s*[-*]\s+(.*)$/);
        if (ul) {
            flushPara();
            if (!list || list.type !== 'ul') { flushList(); list = { type: 'ul', items: [] }; }
            list.items.push(ul[1]);
            continue;
        }

        const ol = line.match(/^\s*\d+[.)]\s+(.*)$/);
        if (ol) {
            flushPara();
            if (!list || list.type !== 'ol') { flushList(); list = { type: 'ol', items: [] }; }
            list.items.push(ol[1]);
            continue;
        }

        flushList();
        para.push(line);
    }
    flushAll();
    return <>{out}</>;
}
