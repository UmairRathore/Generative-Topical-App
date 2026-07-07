import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import WidgetRenderer from '../WidgetRenderer.jsx';
import Markdown from '../lib/markdown.jsx';
import Flashcards from '../studio/Flashcards.jsx';
import Flow from '../studio/Flow.jsx';
import { hasWidget } from '../../widgets/registry.js';

// ── Super-admin · AI Learning-Asset Review ───────────────────────────────────
// Shows the original question + every AI-generated asset (any status) with
// validation warnings and per-asset approve / reject / hide / edit controls.
// Generation is external (Claude Code / workflow); this screen only reviews.

const TYPE_LABEL = {
    worked_solution: 'Worked solution',
    option_explanation: 'Option explanations',
    interactive_widget: 'Interactive widget',
    flashcards: 'Flashcards',
    memcards: 'Memory cards',
    mermaid: 'Solution flow',
    revision_notes: 'Revision notes',
    common_mistakes: 'Common mistakes',
};

const STATUS_META = {
    draft: { label: 'Pending review', cls: 'pending' },
    generated: { label: 'Pending review', cls: 'pending' },
    reviewed: { label: 'Pending review', cls: 'pending' },
    approved: { label: 'Approved', cls: 'ok' },
    edited: { label: 'Edited · approved', cls: 'ok' },
    rejected: { label: 'Rejected', cls: 'bad' },
    hidden: { label: 'Hidden', cls: 'muted' },
    failed_validation: { label: 'Failed validation', cls: 'bad' },
};

const TEXT_TYPES = ['worked_solution', 'mermaid', 'revision_notes', 'common_mistakes'];

function AssetBody({ asset }) {
    const p = asset.payload || {};
    switch (asset.type) {
        case 'worked_solution':
        case 'revision_notes':
        case 'common_mistakes':
            return <div className="ls-prose"><Markdown text={asset.content} /></div>;
        case 'mermaid':
            return <Flow source={asset.content} />;
        case 'flashcards':
        case 'memcards':
            return <Flashcards cards={Array.isArray(p) ? p : []} />;
        case 'option_explanation':
            return (
                <div className="ls-opts">
                    {(p.options || []).map((o) => (
                        <div className={'ls-opt' + (o.correct ? ' is-correct' : '')} key={o.label}>
                            <span className="ls-opt-key">{o.label}</span>
                            <div className="ls-opt-vals"><span className="ls-opt-text">{o.text}</span></div>
                            <div className="ls-opt-why">{o.correct ? <span className="ls-ok">✓ correct</span> : o.why}</div>
                        </div>
                    ))}
                </div>
            );
        case 'interactive_widget': {
            const type = p.widget || p.type;
            if (!hasWidget(type)) return <div className="ar-empty">No component for widget “{type}” yet — it won’t render for students.</div>;
            return <div className="ls-widget-card"><WidgetRenderer type={type} config={p.config} /></div>;
        }
        default:
            return <pre className="ls-code">{JSON.stringify(asset.payload ?? asset.content, null, 2)}</pre>;
    }
}

function AssetCard({ asset }) {
    const [editing, setEditing] = useState(false);
    const [busy, setBusy] = useState(false);
    const isText = TEXT_TYPES.includes(asset.type);
    const [title, setTitle] = useState(asset.title || '');
    const [text, setText] = useState(isText ? (asset.content || '') : JSON.stringify(asset.payload ?? {}, null, 2));
    const meta = STATUS_META[asset.status] || { label: asset.status, cls: 'muted' };

    const act = (action) => { setBusy(true); router.patch(asset.urls.status, { action }, { preserveScroll: true, onFinish: () => setBusy(false) }); };
    const save = () => {
        setBusy(true);
        const body = isText ? { title, content: text } : { title, payload_json: text };
        router.patch(asset.urls.update, body, { preserveScroll: true, onSuccess: () => setEditing(false), onFinish: () => setBusy(false) });
    };

    return (
        <div className={'ar-card ar-' + meta.cls}>
            <div className="ar-head">
                <div className="ar-type">{TYPE_LABEL[asset.type] || asset.type}</div>
                <span className={'ar-badge ar-badge-' + meta.cls}>{meta.label}</span>
                {asset.generatedBy ? <span className="ar-by">by {asset.generatedBy}</span> : null}
                <div className="ar-sp" />
                {!editing && (
                    <div className="ar-actions">
                        <button className="ls-btn ar-approve" disabled={busy} onClick={() => act('approve')}>Approve</button>
                        <button className="ls-btn" disabled={busy} onClick={() => setEditing(true)}>Edit</button>
                        <button className="ls-btn ar-reject" disabled={busy} onClick={() => act('reject')}>Reject</button>
                        <button className="ls-btn" disabled={busy} onClick={() => act('hide')}>Hide</button>
                        {asset.status !== 'draft' && <button className="ls-btn" disabled={busy} onClick={() => act('reset')}>Reset</button>}
                    </div>
                )}
            </div>

            {asset.warnings?.length > 0 && (
                <div className="ar-warnings">
                    {asset.warnings.map((wn, i) => <span className="ar-warn" key={i}>⚠ {wn}</span>)}
                </div>
            )}

            {editing ? (
                <div className="ar-edit">
                    <input className="ar-input" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Title" />
                    <textarea className="ar-textarea" value={text} onChange={(e) => setText(e.target.value)} spellCheck={false}
                        rows={isText ? 10 : 14} />
                    <div className="ar-edit-actions">
                        <span className="ar-edit-hint">{isText ? 'Markdown' : 'JSON payload'} · saving marks it edited & approved</span>
                        <div className="ar-sp" />
                        <button className="ls-btn" disabled={busy} onClick={() => setEditing(false)}>Cancel</button>
                        <button className="ls-btn ar-approve" disabled={busy} onClick={save}>Save</button>
                    </div>
                </div>
            ) : (
                <div className="ar-body"><AssetBody asset={asset} /></div>
            )}
        </div>
    );
}

export default function AssetReview({ question = {}, assets = [], urls = {} }) {
    const pending = assets.filter((a) => ['draft', 'generated', 'reviewed'].includes(a.status)).length;
    const approveAll = () => router.post(urls.approveAll, {}, { preserveScroll: true });

    return (
        <>
            <Head title={`Review · ${question.ref || 'assets'}`} />
            <div className="ls-shell">
                <div className="ls-top">
                    <div className="ls-mark">✦</div>
                    <div className="ls-brand">AI Asset Review<span>{question.ref}</span></div>
                    <div className="sp" />
                    <a className="ls-back" href={urls.back}>← Question bank</a>
                </div>

                {/* the original question */}
                <div className="ar-question">
                    <div className="ls-lbl">The question</div>
                    <div className="ls-stem">{question.stem}</div>
                    {question.images?.length > 0 && (
                        <div className="ls-figures">
                            {question.images.map((im, i) => <figure className="ls-figure" key={i}><img src={im.url} alt="question diagram" loading="lazy" /></figure>)}
                        </div>
                    )}
                    <div className="ar-opts">
                        {(question.options || []).map((o) => (
                            <div className={'ar-opt' + (o.correct ? ' is-correct' : '')} key={o.label}>
                                <span className="ar-opt-key">{o.label}</span>
                                <span>{o.text}</span>
                                {o.correct ? <span className="ar-opt-ans">answer key</span> : null}
                            </div>
                        ))}
                    </div>
                </div>

                {/* review bar */}
                <div className="ar-bar">
                    <div><b>{assets.length}</b> asset{assets.length === 1 ? '' : 's'} · <b>{pending}</b> pending review</div>
                    <div className="ar-sp" />
                    {pending > 0 && <button className="ls-btn ar-approve" onClick={approveAll}>✓ Approve all pending ({pending})</button>}
                </div>

                {assets.length === 0
                    ? <div className="ar-empty">No AI assets generated for this question yet.</div>
                    : assets.map((a) => <AssetCard asset={a} key={a.id} />)}

                <div className="ls-foot"><b>Students see only approved assets.</b> Drafts, rejected and hidden assets never reach the Learning Hub.</div>
            </div>
        </>
    );
}
