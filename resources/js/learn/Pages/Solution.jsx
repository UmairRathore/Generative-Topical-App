import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import WidgetRenderer from '../WidgetRenderer.jsx';
import Markdown from '../lib/markdown.jsx';
import Flashcards from '../studio/Flashcards.jsx';
import Flow from '../studio/Flow.jsx';
import AddToNotePicker from '../notes/AddToNotePicker.jsx';
import TutorPanel from '../tutor/TutorPanel.jsx';

// ── Learning Studio · one mistake, every angle ───────────────────────────────
// The Mistake Bank's "Worked solution" button lands here. Tabs are built from
// whichever release-gated assets the question has — so as more assets are
// imported, more tabs light up with no code change.

function Figures({ images }) {
    if (!images?.length) return null;
    return (
        <div className="ls-figures">
            {images.map((im, i) => (
                <figure className="ls-figure" key={i}>
                    <img src={im.url} alt={im.caption || 'question diagram'} loading="lazy" />
                </figure>
            ))}
        </div>
    );
}

function QuestionPanel({ question, optionExp }) {
    const cols = optionExp?.payload?.columns || [];
    const opts = optionExp?.payload?.options || [];
    return (
        <div>
            {question?.stem && <div className="ls-stem"><Markdown text={question.stem} /></div>}
            <Figures images={question?.images} />
            {opts.length > 0 && (
                <div className="ls-opts">
                    <div className="ls-opts-lead">Why each answer is right — or wrong</div>
                    {opts.map((o) => {
                        const yours = question?.yourAnswer && o.label === question.yourAnswer;
                        return (
                            <div key={o.label} className={'ls-opt' + (o.correct ? ' is-correct' : yours ? ' is-yours' : '')}>
                                <span className="ls-opt-key">{o.label}</span>
                                <div className="ls-opt-vals">
                                    {o.values?.length
                                        ? o.values.map((v, i) => (<span key={i}><em>{cols[i]}</em>{v}</span>))
                                        : <span className="ls-opt-text">{o.text}</span>}
                                </div>
                                <div className="ls-opt-why">
                                    {o.correct ? <span className="ls-ok">✓ correct</span> : o.why}
                                    {yours && !o.correct ? <span className="ls-yours-tag"> · your answer</span> : null}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

// URL aliases: pretty param values ↔ internal tab keys. We accept both and
// emit the pretty form when syncing the URL.
const TAB_ALIASES = { 'ai-tutor': 'tutor', simulator: 'sim' };
const TAB_EMIT = { tutor: 'ai-tutor', sim: 'simulator' };

export default function Solution({ mistake = {}, question = null, assets = {}, widget = null, backUrl = '/', notes = null, tutor = null, debug = false }) {
    const tabs = useMemo(() => [
        { key: 'question', label: 'Question', icon: '◆', show: !!(question?.stem || assets.option_explanation) },
        { key: 'sim', label: 'Simulator', icon: '▲', show: !!widget?.type },
        { key: 'solution', label: 'Solution', icon: 'Σ', show: !!assets.worked_solution },
        { key: 'flashcards', label: 'Flashcards', icon: '◑', show: !!assets.flashcards },
        { key: 'memcards', label: 'Memcards', icon: '▤', show: !!assets.memcards },
        { key: 'flow', label: 'Flow', icon: '⋔', show: !!assets.mermaid },
        { key: 'tutor', label: 'AI Tutor', icon: '✦', show: !!tutor },
        // Raw-payload inspector: local developer aid only, never for students.
        { key: 'json', label: 'JSON', icon: '{}', show: !!debug },
    ].filter((t) => t.show), [question, assets, widget, tutor, debug]);

    // ?tab= deep-links (e.g. ?tab=solution, ?tab=ai-tutor) - any visible tab.
    const [active, setActive] = useState(() => {
        const raw = typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search).get('tab')
            : null;
        const wanted = TAB_ALIASES[raw] || raw;
        if (wanted && tabs.some((t) => t.key === wanted)) return wanted;
        return tabs[0]?.key || 'question';
    });

    // Keep the URL in sync so reload restores the tab. replaceState (not push):
    // Back should leave the studio, not unwind every tab click.
    const selectTab = (key) => {
        setActive(key);
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', TAB_EMIT[key] || key);
            window.history.replaceState(window.history.state, '', url);
        }
    };
    const eyebrow = [mistake.subject, mistake.topic].filter(Boolean).join(' · ') || 'Worked solution';
    const title = assets.worked_solution?.title || 'Worked solution';

    // Mobile: the section rail lives in a slide-in drawer behind the ☰ button.
    const [navOpen, setNavOpen] = useState(false);

    // ── "Add to notes" bridge ─────────────────────────────────────────────────
    // pick = the pending import payload; widgets ALSO emit the framework-agnostic
    // `camb:add-to-note` event (the notes contract) carrying their live config.
    const [pick, setPick] = useState(null);
    const [added, setAdded] = useState(null);

    useEffect(() => {
        if (!notes) return undefined;
        const onWidgetSave = (e) => setPick({ source: 'widget_state', widget: e.detail.widget, config: e.detail.config });
        document.addEventListener('camb:add-to-note', onWidgetSave);
        return () => document.removeEventListener('camb:add-to-note', onWidgetSave);
    }, [notes]);

    const noteBtn = (label, payload) => (
        <button type="button" className="ls-addnote" onClick={() => setPick(payload)}>＋ {label}</button>
    );
    const TAB_SAVE = {
        question:   assets.option_explanation && ['Save explanations to notes', { source: 'asset', asset_type: 'option_explanation' }],
        sim:        widget?.type && ['Save simulator to notes', { source: 'asset', asset_type: 'interactive_widget' }],
        solution:   assets.worked_solution && ['Save solution to notes', { source: 'asset', asset_type: 'worked_solution' }],
        flashcards: assets.flashcards && ['Save flashcards to notes', { source: 'asset', asset_type: 'flashcards' }],
        memcards:   assets.memcards && ['Save memcards to notes', { source: 'asset', asset_type: 'memcards' }],
        flow:       assets.mermaid && ['Save flow to notes', { source: 'asset', asset_type: 'mermaid' }],
    };

    const jsonView = useMemo(() => JSON.stringify({
        subject: mistake.subject, topic: mistake.topic,
        correctAnswer: question?.correct, yourAnswer: question?.yourAnswer,
        sourcePaper: question?.sourcePaper,
        assets: Object.keys(assets), interactive: widget?.type || null,
    }, null, 2), [mistake, question, assets, widget]);

    return (
        <>
            <Head title="Worked solution" />
            <div className="ls-shell ls-shell--app">
                <div className="ls-top">
                    <button type="button" className="ls-menu-btn" onClick={() => setNavOpen(true)} aria-label="Open sections">☰</button>
                    <div className="ls-mark">◆</div>
                    <div className="ls-brand">Learning Studio<span>{eyebrow}</span></div>
                    <div className="sp" />
                    {notes && (
                        <button type="button" className="ls-back" onClick={() => setPick({ source: 'mistake' })}>
                            ＋ Mistake → notes
                        </button>
                    )}
                    <a className="ls-back" href={backUrl}>← Back to mistake</a>
                </div>

                {/* Chat-app layout: left rail of sections (a slide-in drawer on
                    mobile), main pane on the right. */}
                <div className="ls-app">
                    {navOpen && <div className="ls-side-veil" onClick={() => setNavOpen(false)} />}
                    <aside className={'ls-side' + (navOpen ? ' is-open' : '')}>
                        <div className="ls-side-hero">
                            <div className="ls-eyebrow">{eyebrow}</div>
                            <div className="ls-side-title ls-serif">{title}</div>
                        </div>
                        <nav className="ls-nav" role="tablist" aria-orientation="vertical">
                            {tabs.map((t) => (
                                <button key={t.key} role="tab" aria-selected={active === t.key}
                                    className={'ls-nav-item' + (active === t.key ? ' is-active' : '')}
                                    onClick={() => { selectTab(t.key); setNavOpen(false); }}>
                                    <span className="ls-tab-ic">{t.icon}</span>{t.label}
                                </button>
                            ))}
                        </nav>
                        <div className="ls-side-foot"><b>Learning Studio</b> · built from your own mistake</div>
                    </aside>

                    <div className={'ls-main' + (active === 'tutor' ? ' is-chat' : '')}>
                    {notes && TAB_SAVE[active] && (
                        <div className="ls-panel-actions">{noteBtn(...TAB_SAVE[active])}</div>
                    )}
                    {active === 'question' && <QuestionPanel question={question} optionExp={assets.option_explanation} />}
                    {active === 'sim' && widget?.type && (
                        <div className="ls-widget-card">
                            <WidgetRenderer
                                type={widget.type}
                                config={widget.config}
                                onAddToNote={notes ? (cfg) => setPick({ source: 'widget_state', widget: widget.type, config: cfg }) : undefined}
                            />
                        </div>
                    )}
                    {active === 'solution' && <div><Figures images={question?.images} /><div className="ls-prose"><Markdown text={assets.worked_solution?.content} /></div></div>}
                    {active === 'flashcards' && <Flashcards cards={assets.flashcards?.payload || []} />}
                    {active === 'memcards' && <Flashcards cards={assets.memcards?.payload || []} />}
                    {active === 'flow' && <Flow source={assets.mermaid?.content || ''} />}
                    {active === 'tutor' && tutor && <TutorPanel tutor={tutor} />}
                    {active === 'json' && <pre className="ls-code">{jsonView}</pre>}
                    </div>
                </div>
            </div>

            {pick && notes && (
                <AddToNotePicker
                    notes={notes}
                    payload={pick}
                    suggestedTitle={assets.worked_solution?.title || eyebrow}
                    onClose={() => setPick(null)}
                    onDone={(res) => { setPick(null); setAdded(res); }}
                />
            )}
            {added && (
                <div className="ls-toast">
                    Added to <a href={added.pageUrl}>{added.pageTitle} →</a>
                    <button type="button" onClick={() => setAdded(null)}>✕</button>
                </div>
            )}
        </>
    );
}
