import React, { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';

// ── Super Admin · Authoring Review queue ─────────────────────────────────────
// The content-review queue for offline-authored lesson artifacts. Server-side
// catalog supplies safe artifact entries; this page groups them by syllabus
// topic with headings + quick-jump + search, and only filters/links.

const REVIEW_LABEL = {
    pending_review: ['Pending review', 'au-badge-warn'],
    needs_revision: ['Needs revision', 'au-badge-bad'],
    approved: ['Approved', 'au-badge-ok'],
    approval_stale: ['Approval stale', 'au-badge-bad'],
};

// Topic titles per syllabus. Keeps headings human-readable; unknown syllabi or
// topics fall back to "Topic N".
const TOPIC_TITLES = {
    '5054': {
        1: 'Motion, forces and energy',
        2: 'Thermal physics',
        3: 'Waves',
        4: 'Electricity and magnetism',
        5: 'Nuclear physics',
        6: 'Space physics',
    },
};

function topicTitle(syllabusCode, topicCode) {
    if (topicCode == null) return 'Uncategorised';
    const t = TOPIC_TITLES[syllabusCode]?.[Number(topicCode)];
    return t ? `Topic ${topicCode} — ${t}` : `Topic ${topicCode}`;
}

// Natural compare of dotted section codes: "1.5.1" < "1.5.10" < "1.6".
function compareSectionCode(a, b) {
    const pa = String(a || '').split('.').map((n) => parseInt(n, 10));
    const pb = String(b || '').split('.').map((n) => parseInt(n, 10));
    const len = Math.max(pa.length, pb.length);
    for (let i = 0; i < len; i += 1) {
        const x = Number.isNaN(pa[i]) ? -1 : (pa[i] ?? -1);
        const y = Number.isNaN(pb[i]) ? -1 : (pb[i] ?? -1);
        if (x !== y) return x - y;
    }
    return 0;
}

// Lowest LO number on a card ("3.4 #7" → 7); used to order split lessons (A before B).
function minLo(a) {
    let min = Infinity;
    for (const lo of a.target_los || []) {
        const m = /#\s*(\d+)/.exec(String(lo));
        if (m) min = Math.min(min, parseInt(m[1], 10));
    }
    return min;
}

// Section label for a sub-heading, e.g. "3.4 Sound"; falls back to the code alone.
function sectionLabel(a) {
    return a.section || a.section_code || 'Other';
}

function matchesSearch(a, q) {
    if (!q) return true;
    const hay = [
        a.title,
        a.section,
        a.section_code,
        a.subject,
        a.syllabus_code,
        ...(a.target_los || []),
    ].filter(Boolean).join(' ').toLowerCase();
    return q.toLowerCase().split(/\s+/).every((tok) => hay.includes(tok));
}

export default function Queue({ artifacts = [], skipped = [], adminUrl = null }) {
    const [stage, setStage] = useState('all');
    const [status, setStatus] = useState('all');
    const [query, setQuery] = useState('');

    const rows = useMemo(() => artifacts.filter((a) => {
        if (stage !== 'all' && a.stage !== stage) return false;
        if (status === 'pending') { if (!(a.stage !== 'stage_b' || ['pending_review', 'needs_revision', 'approval_stale'].includes(a.human_review))) return false; }
        else if (status === 'approved') { if (a.human_review !== 'approved') return false; }
        else if (status === 'needs_revision') { if (a.human_review !== 'needs_revision') return false; }
        else if (status === 'stale') { if (!(a.human_review === 'approval_stale' || a.approvals?.foundation === 'stale' || a.approvals?.plan === 'stale')) return false; }
        else if (status === 'warnings') { if (!((a.validation?.warnings ?? 0) > 0)) return false; }
        return matchesSearch(a, query);
    }), [artifacts, stage, status, query]);

    // Group filtered rows by syllabus + topic; sort groups and rows naturally.
    const groups = useMemo(() => {
        const byKey = new Map();
        for (const a of rows) {
            const syllabus = a.syllabus_code || '?';
            const topic = a.topic_code ?? null;
            const key = `${syllabus}::${topic ?? 'z'}`;
            if (!byKey.has(key)) {
                byKey.set(key, {
                    key,
                    anchor: `topic-${syllabus}-${topic ?? 'x'}`.replace(/[^a-zA-Z0-9-]/g, ''),
                    syllabus,
                    subject: a.subject,
                    topicCode: topic,
                    heading: topicTitle(syllabus, topic),
                    rows: [],
                });
            }
            byKey.get(key).rows.push(a);
        }
        const out = [...byKey.values()];
        for (const g of out) {
            g.rows.sort((x, y) => {
                const c = compareSectionCode(x.section_code, y.section_code);
                if (c !== 0) return c;
                const lo = minLo(x) - minLo(y);          // split lessons: #1-6 before #7-13
                if (lo !== 0 && Number.isFinite(lo)) return lo;
                return String(y.generated_at || '').localeCompare(String(x.generated_at || ''));
            });
            // Sub-group the (already section-sorted) rows into consecutive sections.
            g.sections = [];
            for (const a of g.rows) {
                const code = a.section_code ?? '~';
                const last = g.sections[g.sections.length - 1];
                if (last && last.code === code) last.cards.push(a);
                else g.sections.push({ code, label: sectionLabel(a), cards: [a] });
            }
        }
        out.sort((g, h) => {
            // Unknown syllabus (?) sinks below the real ones.
            const gs = g.syllabus === '?' ? '￿' : g.syllabus;
            const hs = h.syllabus === '?' ? '￿' : h.syllabus;
            if (gs !== hs) return gs < hs ? -1 : 1;
            const gc = g.topicCode == null ? 999 : Number(g.topicCode);
            const hc = h.topicCode == null ? 999 : Number(h.topicCode);
            return gc - hc;
        });
        return out;
    }, [rows]);

    const renderCard = (a) => {
        const [label, cls] = REVIEW_LABEL[a.human_review] || ['Stage A record', 'au-badge-muted'];
        return (
            <article key={a.id} className="au-card">
                <div className="au-card-main">
                    <div className="au-card-title">
                        <span className={'au-badge ' + (a.stage === 'stage_b' ? 'au-badge-stage' : 'au-badge-muted')}>{a.stage === 'stage_b' ? 'Stage B' : 'Stage A'}</span>
                        {a.section_code && <span className="au-section-code">{a.section_code}</span>}
                        <h2>{a.title}</h2>
                    </div>
                    <div className="au-card-meta">
                        <span>{a.subject} {a.syllabus_code}{a.level ? ` · ${a.level}` : ''}{a.syllabus_version ? ` · ${a.syllabus_version}` : ''}</span>
                        {a.section && <span>· {a.section}</span>}
                    </div>
                    <div className="au-los">
                        {(a.target_los || []).map((lo) => <span key={lo} className="au-lo">{lo}</span>)}
                    </div>
                </div>
                <div className="au-card-side">
                    <span className={'au-badge ' + cls}>{label}</span>
                    {a.stage === 'stage_b' && (
                        <>
                            <span className={'au-badge ' + ((a.validation?.blocking ?? 0) > 0 ? 'au-badge-bad' : 'au-badge-ok')}>
                                {(a.validation?.blocking ?? 0)} blocking · {(a.validation?.warnings ?? 0)} warnings
                            </span>
                            <span className="au-hash" title={a.lesson_sha256}>sha {String(a.lesson_sha256 || '').slice(0, 12)}</span>
                        </>
                    )}
                    {a.generated_at && <span className="au-when">{a.generated_at}</span>}
                    {a.reviewable && a.review_url && (
                        <Link className="au-primary" href={a.review_url}>Review lesson</Link>
                    )}
                </div>
            </article>
        );
    };

    return (
        <div className="au-shell">
            <Head title="Authoring Review" />
            <header className="au-topbar">
                <div>
                    {adminUrl && <a className="au-back" href={adminUrl}>← Admin panel</a>}
                    <h1>Authoring Review</h1>
                    <p className="au-sub">Offline-authored lesson artifacts pending human review. Approval binds the exact artifact hash.</p>
                </div>
                <div className="au-filters">
                    <input
                        type="search"
                        className="au-search"
                        placeholder="Search title, section, LO…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <select value={stage} onChange={(e) => setStage(e.target.value)}>
                        <option value="all">All stages</option>
                        <option value="stage_a">Stage A</option>
                        <option value="stage_b">Stage B</option>
                    </select>
                    <select value={status} onChange={(e) => setStatus(e.target.value)}>
                        <option value="pending">Pending human review</option>
                        <option value="approved">Approved</option>
                        <option value="needs_revision">Needs revision</option>
                        <option value="stale">Stale</option>
                        <option value="warnings">Validation warnings</option>
                        <option value="all">Everything</option>
                    </select>
                </div>
            </header>

            {groups.length > 1 && (
                <nav className="au-jump">
                    {groups.map((g) => (
                        <a key={g.key} className="au-jump-chip" href={`#${g.anchor}`}>
                            {g.topicCode == null ? g.heading : `${g.syllabus} · T${g.topicCode}`}
                            <span className="au-jump-count">{g.rows.length}</span>
                        </a>
                    ))}
                </nav>
            )}

            {groups.length === 0 && <div className="au-empty">Nothing in this queue.</div>}

            {groups.map((g) => (
                <section key={g.key} className="au-topic" id={g.anchor}>
                    <div className="au-topic-head">
                        <h2>{g.heading}</h2>
                        <span className="au-topic-meta">{g.subject ? `${g.subject} ${g.syllabus}` : g.syllabus} · {g.rows.length} lesson{g.rows.length === 1 ? '' : 's'}</span>
                    </div>
                    <div className="au-cards">
                        {(g.sections || []).map((sec) => (
                            sec.cards.length > 1 ? (
                                <div key={sec.code} className="au-section-group">
                                    <div className="au-section-subhead">{sec.label} <span className="au-section-parts">· {sec.cards.length} lessons</span></div>
                                    {sec.cards.map(renderCard)}
                                </div>
                            ) : (
                                <React.Fragment key={sec.code}>{sec.cards.map(renderCard)}</React.Fragment>
                            )
                        ))}
                    </div>
                </section>
            ))}

            {skipped.length > 0 && (
                <details className="au-skipped">
                    <summary>{skipped.length} director{skipped.length === 1 ? 'y' : 'ies'} skipped (unsafe or malformed)</summary>
                    <ul>{skipped.map((s) => <li key={s.id}><code>{s.id}</code> — {s.reason}</li>)}</ul>
                </details>
            )}
        </div>
    );
}
