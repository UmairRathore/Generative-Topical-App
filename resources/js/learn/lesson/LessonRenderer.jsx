import React, { useState } from 'react';
import WidgetRenderer from '../WidgetRenderer.jsx';
import { hasWidget } from '../../widgets/registry.js';

// ── LessonRenderer ────────────────────────────────────────────────────────────
// Reusable presentation renderer for `topicaled.stage-b-lesson.v1` documents.
// This is the SAME renderer a future student Course lesson screen will mount:
// it has no Super Admin dependencies, no approval controls, and no access to
// answer keys. Evaluation is injected: `evaluateCheck(checkId, response)`
// returns { correct: true|false|null, feedback } (or null when no evaluator is
// available). The review shell backs it with reviewer-only server data; a
// student build will back it with an API call — the renderer cannot leak what
// it is never given.
//
// props: {
//   lesson,                        student-safe lesson document
//   mode: 'student' | 'reviewer',  reviewer mode adds collapsible provenance overlays
//   evaluateCheck,                 (checkId, response) => result | null
//   onWidgetError,                 optional (blockId, widgetKey, error) callback
// }

/* Minimal inline formatter: paragraphs, **bold**, *italic*, `code`. No HTML injection. */
function inline(text, keyBase = 'i') {
    const out = [];
    const re = /(\*\*[^*]+\*\*|\*[^*]+\*|`[^`]+`)/g;
    let last = 0, m, k = 0;
    while ((m = re.exec(text)) !== null) {
        if (m.index > last) out.push(text.slice(last, m.index));
        const t = m[0];
        if (t.startsWith('**')) out.push(<strong key={`${keyBase}-${k++}`}>{t.slice(2, -2)}</strong>);
        else if (t.startsWith('`')) out.push(<code key={`${keyBase}-${k++}`}>{t.slice(1, -1)}</code>);
        else out.push(<em key={`${keyBase}-${k++}`}>{t.slice(1, -1)}</em>);
        last = m.index + t.length;
    }
    if (last < text.length) out.push(text.slice(last));
    return out;
}

export function Md({ text, className = '' }) {
    if (!text) return null;
    return (
        <div className={`lr-md ${className}`}>
            {String(text).split(/\n{2,}/).map((para, i) => {
                const lines = para.split('\n');
                if (lines.every((l) => l.trim().startsWith('- '))) {
                    return <ul key={i}>{lines.map((l, j) => <li key={j}>{inline(l.trim().slice(2), `${i}-${j}`)}</li>)}</ul>;
                }
                return <p key={i}>{lines.map((l, j) => <React.Fragment key={j}>{j > 0 && <br />}{inline(l, `${i}-${j}`)}</React.Fragment>)}</p>;
            })}
        </div>
    );
}

/* Widget render failures must be loud for reviewers, never silent. */
class WidgetBoundary extends React.Component {
    constructor(props) { super(props); this.state = { error: null }; }

    static getDerivedStateFromError(error) { return { error }; }

    componentDidCatch(error) { this.props.onError && this.props.onError(error); }

    render() {
        if (this.state.error) {
            return this.props.mode === 'reviewer' ? (
                <div className="lr-widget-failed">
                    <strong>Widget failed to render</strong>
                    <div>widget: <code>{this.props.widgetKey}</code> · block: <code>{this.props.blockId}</code></div>
                    <div className="lr-widget-failed-msg">{String(this.state.error?.message || this.state.error)}</div>
                </div>
            ) : (
                <div className="lr-widget-failed lr-widget-failed-student">This interactive is unavailable.</div>
            );
        }
        return this.props.children;
    }
}

function Overlay({ title, rows }) {
    const filled = rows.filter(([, v]) => v != null && v !== '' && !(Array.isArray(v) && v.length === 0));
    if (!filled.length) return null;
    return (
        <details className="lr-overlay">
            <summary>{title}</summary>
            <dl>
                {filled.map(([k, v]) => (
                    <React.Fragment key={k}>
                        <dt>{k}</dt>
                        <dd>{Array.isArray(v) ? v.map((x, i) => <code key={i}>{String(x)}</code>) : typeof v === 'object' ? <pre>{JSON.stringify(v, null, 2)}</pre> : String(v)}</dd>
                    </React.Fragment>
                ))}
            </dl>
        </details>
    );
}

function Explanation({ block, mode }) {
    return (
        <div className="lr-block lr-explanation">
            {block.heading && <h4>{block.heading}</h4>}
            <Md text={block.body_md} />
            {mode === 'reviewer' && <Overlay title="Explanation metadata" rows={[['grounding', block.grounding_reference_handles]]} />}
        </div>
    );
}

function ConceptSummary({ block, mode }) {
    return (
        <div className="lr-block lr-summary">
            <div className="lr-summary-tag">{block.heading || 'In short'}</div>
            <ul>{(block.points_md || []).map((p, i) => <li key={i}>{inline(p, `s${i}`)}</li>)}</ul>
            {mode === 'reviewer' && <Overlay title="Summary metadata" rows={[['grounding', block.grounding_reference_handles]]} />}
        </div>
    );
}

function InteractiveWidget({ block, mode, onWidgetError }) {
    const key = block.widget_key;
    return (
        <div className="lr-block lr-widget">
            {block.student_instruction && <div className="lr-widget-instruction"><Md text={block.student_instruction} /></div>}
            {hasWidget(key) ? (
                <WidgetBoundary widgetKey={key} blockId={block.block_id} mode={mode} onError={(e) => onWidgetError && onWidgetError(block.block_id, key, e)}>
                    <div className="ls-widget-card"><WidgetRenderer type={key} config={block.config} /></div>
                </WidgetBoundary>
            ) : (
                <div className="lr-widget-failed">
                    <strong>Widget failed to render</strong>
                    <div>widget: <code>{key}</code> · block: <code>{block.block_id}</code></div>
                    <div className="lr-widget-failed-msg">No component registered for this widget key.</div>
                </div>
            )}
            {mode === 'reviewer' && (
                <Overlay title="Widget placement metadata" rows={[
                    ['widget', key],
                    ['target LOs', block.target_lo_refs],
                    ['demonstrates', block.reference_handles_demonstrated],
                    ['purpose', block.pedagogical_purpose],
                    ['manipulation', block.student_manipulation],
                    ['expected observation', block.expected_observation],
                    ['intended inference', block.intended_inference],
                    ['config', block.config],
                ]} />
            )}
        </div>
    );
}

function GuidedExample({ block, mode }) {
    const [revealed, setRevealed] = useState(1);
    const steps = block.steps || [];
    return (
        <div className="lr-block lr-example">
            <div className="lr-example-tag">Guided example</div>
            {block.heading && <h4>{block.heading}</h4>}
            <Md text={block.setup_md} className="lr-example-setup" />
            <ol className="lr-example-steps">
                {steps.slice(0, revealed).map((s, i) => (
                    <li key={i}><Md text={typeof s === 'string' ? s : s.step_md} /></li>
                ))}
            </ol>
            {revealed < steps.length ? (
                <button className="lr-btn" onClick={() => setRevealed(revealed + 1)}>Next step</button>
            ) : (
                block.final_answer && (
                    <div className="lr-example-answer">
                        <span>Answer</span> <b>{block.final_answer.value}</b> {block.final_answer.unit}
                    </div>
                )
            )}
            {mode === 'reviewer' && (
                <Overlay title="Example metadata" rows={[
                    ['teaching purpose', block.teaching_purpose],
                    ['target LOs', block.target_lo_refs],
                    ['relationships used', block.relationships_used],
                    ['grounding', block.grounding_reference_handles],
                ]} />
            )}
        </div>
    );
}

function Misconception({ block, mode }) {
    return (
        <div className="lr-block lr-misconception">
            <div className="lr-misconception-tag">Watch out</div>
            <Md text={block.trigger_context} className="lr-misconception-trigger" />
            <div className="lr-misconception-wrong"><span>The tempting (wrong) reading:</span> <Md text={block.incorrect_reasoning} /></div>
            <Md text={block.correction_md} />
            {mode === 'reviewer' && (
                <Overlay title="Misconception provenance" rows={[
                    ['id', block.misconception_id || '(unverified proposal)'],
                    ['proposal', block.unverified_proposal],
                    ['grounding', block.grounding_reference_handles],
                ]} />
            )}
        </div>
    );
}

function FormativeCheck({ block, mode, evaluateCheck }) {
    const [response, setResponse] = useState({ option: null, options: [], value: '', unit: '', text: '' });
    const [result, setResult] = useState(null);
    const [done, setDone] = useState(false);
    const rt = block.response_type;

    const submit = (payload) => {
        const r = evaluateCheck ? evaluateCheck(block.check_id, payload) : null;
        setResult(r || { correct: null, feedback: 'Evaluation is not available in this preview.' });
        setDone(true);
    };

    return (
        <div className="lr-block lr-check">
            <div className="lr-check-tag">Check yourself{block.command_word ? ` · ${block.command_word}` : ''}</div>
            <Md text={block.prompt_md} />
            {rt === 'single_choice' && (
                <div className="lr-check-options">
                    {(block.options || []).map((o) => (
                        <button key={o.id} disabled={done}
                                className={'lr-option' + (done && response.option === o.id ? ' lr-option-picked' : '')}
                                onClick={() => { setResponse({ ...response, option: o.id }); submit({ option: o.id }); }}>
                            <span className="lr-option-id">{o.id}</span> {inline(o.label_md || o.label || '', o.id)}
                        </button>
                    ))}
                </div>
            )}
            {rt === 'multiple_choice' && (
                <div className="lr-check-options">
                    {(block.options || []).map((o) => (
                        <label key={o.id} className="lr-option lr-option-multi">
                            <input type="checkbox" disabled={done} checked={response.options.includes(o.id)}
                                   onChange={(e) => setResponse({ ...response, options: e.target.checked ? [...response.options, o.id] : response.options.filter((x) => x !== o.id) })} />
                            <span className="lr-option-id">{o.id}</span> {inline(o.label_md || o.label || '', o.id)}
                        </label>
                    ))}
                    {!done && <button className="lr-btn" onClick={() => submit({ options: response.options })}>Check</button>}
                </div>
            )}
            {(rt === 'numeric' || rt === 'numeric_with_unit') && (
                <div className="lr-check-numeric">
                    <input type="number" step="any" inputMode="decimal" disabled={done} value={response.value} placeholder="value"
                           onChange={(e) => setResponse({ ...response, value: e.target.value })} />
                    {rt === 'numeric_with_unit' && (
                        <select disabled={done} value={response.unit} onChange={(e) => setResponse({ ...response, unit: e.target.value })}>
                            <option value="" disabled>unit…</option>
                            {(block.unit_options || []).map((u) => <option key={u} value={u}>{u}</option>)}
                        </select>
                    )}
                    {!done && <button className="lr-btn" disabled={response.value === '' || (rt === 'numeric_with_unit' && !response.unit)}
                                      onClick={() => submit({ value: response.value, unit: response.unit })}>Check</button>}
                </div>
            )}
            {rt === 'short_text' && (
                <div className="lr-check-numeric">
                    <input type="text" disabled={done} value={response.text} placeholder="your answer"
                           onChange={(e) => setResponse({ ...response, text: e.target.value })} />
                    {!done && <button className="lr-btn" disabled={!response.text.trim()} onClick={() => submit({ text: response.text })}>Check</button>}
                </div>
            )}
            {rt === 'sketch_manual' && (
                <div className="lr-check-sketch">
                    <div className="lr-check-sketch-note">Sketch this on paper — it is not auto-marked.</div>
                    {!done && <button className="lr-btn" onClick={() => submit({ sketch: true })}>I have sketched it — show what to look for</button>}
                </div>
            )}
            {done && result && (
                <div className={'lr-check-result ' + (result.correct === true ? 'lr-ok' : result.correct === false ? 'lr-bad' : 'lr-neutral')}>
                    {result.correct === true && <b>Correct. </b>}
                    {result.correct === false && <b>Not quite. </b>}
                    <Md text={result.feedback} />
                    <button className="lr-btn lr-btn-ghost" onClick={() => { setDone(false); setResult(null); setResponse({ option: null, options: [], value: '', unit: '', text: '' }); }}>Try again</button>
                </div>
            )}
            {mode === 'reviewer' && (
                <Overlay title="Check metadata" rows={[
                    ['check id', block.check_id],
                    ['target LOs', block.target_lo_refs],
                    ['response type', rt],
                    ['diagnostic intent', block.diagnostic_intent],
                    ['evidence ids', block.evidence_question_ids],
                    ['evidence independence', block.evidence_independence_reason],
                    ['evaluation mode', block.evaluation?.mode],
                ]} />
            )}
        </div>
    );
}

function Transition({ block }) {
    return <div className="lr-block lr-transition"><Md text={block.text_md} /></div>;
}

function TutorCheckpoint({ block, mode }) {
    return (
        <div className="lr-block lr-tutor">
            <div className="lr-tutor-row">
                <span className="lr-tutor-icon">✳</span>
                <div>
                    <div className="lr-tutor-title">Stuck or curious? Ask the AI Tutor</div>
                    <div className="lr-tutor-sub">{block.student_prompt_md || 'You can ask about the ideas in this section.'}</div>
                </div>
                <button className="lr-btn" disabled title="Preview only — the Tutor is not live in review">Ask the Tutor (preview)</button>
            </div>
            {mode === 'reviewer' && (
                <Overlay title="Tutor checkpoint grounding" rows={[
                    ['checkpoint id', block.checkpoint_id],
                    ['current concept', block.current_concept],
                    ['target LOs', block.target_lo_refs],
                    ['grounding', block.grounding_reference_handles],
                    ['permitted scope', block.permitted_scope],
                    ['useful question types', block.useful_question_types],
                ]} />
            )}
        </div>
    );
}

const BLOCKS = {
    explanation: Explanation,
    concept_summary: ConceptSummary,
    interactive_widget: InteractiveWidget,
    guided_example: GuidedExample,
    misconception_intervention: Misconception,
    formative_check: FormativeCheck,
    transition: Transition,
    tutor_checkpoint: TutorCheckpoint,
};

export default function LessonRenderer({ lesson, mode = 'student', evaluateCheck = null, onWidgetError = null }) {
    if (!lesson) return null;
    return (
        <div className="lr-lesson">
            {(lesson.phases || []).map((phase, pi) => (
                <section key={phase.phase_id || pi} className="lr-phase" id={phase.phase_id}>
                    <header className="lr-phase-head">
                        <span className="lr-phase-num">Phase {pi + 1}</span>
                        <h3>{phase.title}</h3>
                        {mode === 'reviewer' && (
                            <Overlay title="Phase metadata" rows={[
                                ['stage A phase', phase.stage_a_phase_title],
                                ['purpose', phase.purpose],
                                ['advances LOs', phase.advances_los],
                                ['supporting review', phase.supporting_review ? 'yes' : null],
                                ['direct references', phase.direct_reference_handles],
                                ['supporting references', phase.supporting_reference_handles],
                            ]} />
                        )}
                    </header>
                    {(phase.blocks || []).map((block) => {
                        const Cmp = BLOCKS[block.type];
                        if (!Cmp) {
                            return mode === 'reviewer'
                                ? <div key={block.block_id} className="lr-widget-failed"><strong>Unsupported block type</strong> <code>{String(block.type)}</code> · block <code>{block.block_id}</code></div>
                                : null;
                        }
                        return <Cmp key={block.block_id} block={block} mode={mode} evaluateCheck={evaluateCheck} onWidgetError={onWidgetError} />;
                    })}
                </section>
            ))}
        </div>
    );
}
