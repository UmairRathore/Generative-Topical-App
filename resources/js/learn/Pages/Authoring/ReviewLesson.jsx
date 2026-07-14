import React, { useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import LessonRenderer from '../../lesson/LessonRenderer.jsx';

// ── Super Admin · visual Stage B lesson review ───────────────────────────────
// Renders the EXACT authored artifact through the shared LessonRenderer and
// wraps it with review capabilities: Student Preview ↔ Reviewer Mode toggle,
// validation/provenance panels, hash drawer, review history, and the two human
// actions (Approve Exact Artifact / Needs Revision). The lesson payload is the
// student-safe document; check evaluation is backed here by the reviewer-only
// `reviewer.evaluations` payload this Super Admin route provides.

function evaluateAgainst(evaluation, response) {
    if (!evaluation) return null;
    const d = evaluation.data || {};
    if (evaluation.mode === 'manual_review') {
        return { correct: null, feedback: d.guidance_md || 'This response is reviewed manually.' };
    }
    if (d.correct_option != null && response.option != null) {
        const ok = response.option === d.correct_option;
        return { correct: ok, feedback: ok ? d.feedback_correct_md : (d.option_feedback_md?.[response.option] || d.feedback_incorrect_md) };
    }
    if (Array.isArray(d.correct_options) && Array.isArray(response.options)) {
        const a = [...d.correct_options].sort().join('|'), b = [...response.options].sort().join('|');
        const ok = a === b;
        return { correct: ok, feedback: ok ? d.feedback_correct_md : d.feedback_incorrect_md };
    }
    if (d.value != null && response.value !== undefined) {
        const v = parseFloat(response.value);
        const valueOk = Number.isFinite(v) && Math.abs(v - d.value) <= (d.tolerance ?? 0);
        const unitOk = d.unit ? response.unit === d.unit : true;
        const feedback = valueOk && unitOk ? d.feedback_correct_md : (valueOk && !unitOk ? (d.feedback_wrong_unit_md || d.feedback_incorrect_md) : d.feedback_incorrect_md);
        return { correct: valueOk && unitOk, feedback };
    }
    if (Array.isArray(d.accepted_answers) && response.text !== undefined) {
        const norm = (s) => String(s).toLowerCase().replace(/\s+/g, ' ').trim();
        const ok = d.accepted_answers.some((a) => norm(a) === norm(response.text));
        return { correct: ok, feedback: ok ? d.feedback_correct_md : d.feedback_incorrect_md };
    }
    return null;
}

const GATE = { valid: ['VALID', 'au-badge-ok'], stale: ['STALE', 'au-badge-bad'], none: ['NONE', 'au-badge-warn'] };

function Gate({ label, state }) {
    const [text, cls] = GATE[state?.status] || ['?', 'au-badge-muted'];
    return (
        <div className="au-gate">
            <span className="au-gate-label">{label}</span>
            <span className={'au-badge ' + cls}>{text}{state?.reason ? ` · ${state.reason}` : ''}</span>
            {state?.reviewer && <span className="au-gate-who">{state.reviewer} · {state.signed_at}</span>}
        </div>
    );
}

function Sha({ label, value }) {
    return value ? <div className="au-sha"><span>{label}</span><code title={value}>{value.slice(0, 16)}…</code></div> : null;
}

export default function ReviewLesson({ lesson, meta, validation, approvals, reviewer, history, feedbackCategories, urls }) {
    const { props } = usePage();
    const flash = props.flash?.status;
    const errors = props.errors || {};
    const [mode, setMode] = useState('student');
    const [panel, setPanel] = useState(null); // validation | foundation | plan | artifact | evidence | references | history
    const [confirming, setConfirming] = useState(false);
    const [revising, setRevising] = useState(false);
    const [widgetErrors, setWidgetErrors] = useState([]);

    const evaluateCheck = useMemo(() => (checkId, response) => evaluateAgainst(reviewer?.evaluations?.[checkId], response), [reviewer]);

    const blocking = validation?.blocking || [];
    const warnings = validation?.warnings || [];
    const canApprove = blocking.length === 0
        && approvals?.foundation?.status === 'valid'
        && approvals?.plan?.status === 'valid'
        && approvals?.lesson?.status !== 'valid';

    // Needs Revision form state
    const [note, setNote] = useState('');
    const [severity, setSeverity] = useState('minor');
    const [items, setItems] = useState([]);
    const phases = lesson?.phases || [];
    const blockIds = phases.flatMap((p) => (p.blocks || []).map((b) => ({ id: b.block_id, phase: p.phase_id })));

    const submitApproval = () => {
        router.post(urls.approve, { lesson_sha256: meta.hashes.lesson_sha256 }, { preserveScroll: true, onFinish: () => setConfirming(false) });
    };
    const submitRevision = () => {
        router.post(urls.feedback, { severity, overall_note: note, items }, { preserveScroll: true, onSuccess: () => { setRevising(false); setNote(''); setItems([]); } });
    };

    return (
        <div className="au-shell au-review">
            <Head title={`Review · ${meta.title}`} />

            <header className="au-review-head">
                <div className="au-review-title">
                    <Link href={urls.back} className="au-back">← Queue</Link>
                    <h1>{meta.title}</h1>
                    <div className="au-chips">
                        <span className="au-chip">{meta.subject}</span>
                        <span className="au-chip">{meta.syllabus_code}</span>
                        {meta.level && <span className="au-chip">{meta.level}</span>}
                        <span className="au-chip">syllabus {meta.syllabus_version}</span>
                        {meta.section && <span className="au-chip">{meta.section.section_code} {meta.section.title}</span>}
                        {(meta.target_los || []).map((lo) => <span key={lo} className="au-lo">{lo}</span>)}
                        <span className="au-chip au-chip-dim">{meta.schema}</span>
                    </div>
                </div>
                <div className="au-review-state">
                    <button className={'au-badge ' + (blocking.length ? 'au-badge-bad' : 'au-badge-ok')} onClick={() => setPanel('validation')}>
                        {blocking.length} blocking · {warnings.length} warnings
                    </button>
                    <Gate label="Lesson approval" state={approvals?.lesson} />
                    <details className="au-identity">
                        <summary>identity</summary>
                        <Sha label="lesson" value={meta.hashes?.lesson_sha256} />
                        <Sha label="Stage A plan" value={meta.hashes?.plan_sha256} />
                        <Sha label="foundation" value={meta.hashes?.foundation_fingerprint} />
                        <Sha label="packet" value={meta.hashes?.packet_sha256} />
                        <Sha label="source PDF" value={meta.hashes?.source_pdf_sha256} />
                        <div className="au-sha"><span>authored</span><code>{meta.authoring_mode || '—'} · {meta.authoring_model || '—'}</code></div>
                        <div className="au-sha"><span>generated</span><code>{meta.generated_at || '—'}</code></div>
                    </details>
                </div>
            </header>

            {flash && <div className="au-flash au-flash-ok">{flash}</div>}
            {(errors.approve || errors.feedback) && <div className="au-flash au-flash-bad">{errors.approve || errors.feedback}</div>}
            {widgetErrors.length > 0 && (
                <div className="au-flash au-flash-bad">Widget render failures: {widgetErrors.map((w) => `${w.key} @ ${w.blockId}`).join(', ')}</div>
            )}

            <div className="au-review-body">
                <main className="au-lesson-col">
                    <div className="au-mode">
                        <button className={mode === 'student' ? 'au-mode-on' : ''} onClick={() => setMode('student')}>Student Preview</button>
                        <button className={mode === 'reviewer' ? 'au-mode-on' : ''} onClick={() => setMode('reviewer')}>Reviewer Mode</button>
                    </div>
                    <LessonRenderer lesson={lesson} mode={mode} evaluateCheck={evaluateCheck}
                                    onWidgetError={(blockId, key, e) => setWidgetErrors((w) => [...w, { blockId, key, message: String(e) }])} />
                </main>

                <aside className="au-panel-col">
                    <nav className="au-panel-tabs">
                        {['validation', 'foundation', 'plan', 'artifact', 'evidence', 'references', 'history'].map((p) => (
                            <button key={p} className={panel === p ? 'au-mode-on' : ''} onClick={() => setPanel(panel === p ? null : p)}>{p}</button>
                        ))}
                    </nav>
                    {panel === 'validation' && (
                        <div className="au-panel">
                            <h3>Deterministic validation</h3>
                            {blocking.length === 0 && <div className="au-ok-line">0 blocking violations.</div>}
                            {blocking.map((b, i) => <div key={i} className="au-line au-line-bad">✗ {b}</div>)}
                            <h4>{warnings.length} warnings</h4>
                            {warnings.map((w, i) => <div key={i} className="au-line au-line-warn">! {w}</div>)}
                        </div>
                    )}
                    {panel === 'foundation' && (
                        <div className="au-panel">
                            <h3>Academic foundation</h3>
                            <Gate label="Foundation sign-off" state={approvals?.foundation} />
                            <Sha label="fingerprint" value={meta.hashes?.foundation_fingerprint} />
                            <h4>Direct handles</h4>
                            <div className="au-handles">{(reviewer?.reference_handles?.direct || []).map((h) => <code key={h}>{h}</code>)}</div>
                            <h4>Supporting handles</h4>
                            <div className="au-handles">{(reviewer?.reference_handles?.supporting || []).map((h) => <code key={h}>{h}</code>)}</div>
                        </div>
                    )}
                    {panel === 'plan' && (
                        <div className="au-panel">
                            <h3>Stage A plan</h3>
                            <Gate label="Plan approval" state={approvals?.plan} />
                            <Sha label="plan sha" value={meta.hashes?.plan_sha256} />
                            <h4>{reviewer?.stage_a_plan?.lesson_title}</h4>
                            <p className="au-spine">{reviewer?.stage_a_plan?.conceptual_spine}</p>
                            <ol className="au-plan-phases">
                                {(reviewer?.stage_a_plan?.phases || []).map((p, i) => (
                                    <li key={i}><b>{p.title}</b><span>{p.purpose}</span></li>
                                ))}
                            </ol>
                        </div>
                    )}
                    {panel === 'artifact' && (
                        <div className="au-panel">
                            <h3>Stage B artifact</h3>
                            <Sha label="lesson" value={meta.hashes?.lesson_sha256} />
                            <Sha label="packet" value={meta.hashes?.packet_sha256} />
                            <Sha label="Stage A plan" value={meta.hashes?.plan_sha256} />
                            <Sha label="source PDF" value={meta.hashes?.source_pdf_sha256} />
                            <div className="au-sha"><span>schema</span><code>{meta.schema}</code></div>
                            <div className="au-sha"><span>file</span><code>{meta.lesson_file}</code></div>
                            <div className="au-sha"><span>generated</span><code>{meta.generated_at || '—'}</code></div>
                            <div className="au-sha"><span>authoring</span><code>{meta.authoring_mode || '—'} · {meta.authoring_model || '—'}</code></div>
                        </div>
                    )}
                    {panel === 'evidence' && (
                        <div className="au-panel">
                            <h3>Assessment evidence</h3>
                            {(reviewer?.evidence || []).map((q) => (
                                <div key={q.evidence_id} className="au-line">
                                    <b>#{q.evidence_id}</b> {q.question} · {q.year} · {q.classification}
                                    {q.compatibility_scope && <span className="au-dim"> · scope {q.compatibility_scope}</span>}
                                </div>
                            ))}
                            <p className="au-dim">Answer keys are not shown here.</p>
                        </div>
                    )}
                    {panel === 'references' && (
                        <div className="au-panel">
                            <h3>Packet misconceptions</h3>
                            {(reviewer?.misconceptions || []).map((m) => (
                                <div key={m.id} className="au-line"><code>{m.id}</code> <span className="au-dim">({m.priority})</span><br />{m.statement}</div>
                            ))}
                        </div>
                    )}
                    {panel === 'history' && (
                        <div className="au-panel">
                            <h3>Review history</h3>
                            {(history || []).length === 0 && <div className="au-dim">No events yet.</div>}
                            {(history || []).map((e, i) => (
                                <div key={i} className="au-line">
                                    <b>{e.event}</b>{e.stale ? <span className="au-badge au-badge-bad">stale</span> : null}
                                    <br /><span className="au-dim">{e.at}</span> — {e.detail}
                                </div>
                            ))}
                        </div>
                    )}
                </aside>
            </div>

            <footer className="au-actionbar">
                <div className="au-action-status">
                    <Gate label="Foundation" state={approvals?.foundation} />
                    <Gate label="Stage A plan" state={approvals?.plan} />
                </div>
                <div className="au-action-buttons">
                    <button className="au-danger" onClick={() => setRevising(true)}>Needs Revision</button>
                    <button className="au-primary" disabled={!canApprove}
                            title={canApprove ? 'Approve this exact artifact hash' : 'Approval requires 0 blocking violations, VALID foundation + plan approvals, and no existing valid approval'}
                            onClick={() => setConfirming(true)}>
                        Approve Exact Artifact
                    </button>
                </div>
            </footer>

            {confirming && (
                <div className="au-modal-backdrop" onClick={() => setConfirming(false)}>
                    <div className="au-modal" onClick={(e) => e.stopPropagation()}>
                        <h3>Approve exact artifact</h3>
                        <p>You are approving, as the authenticated Super Admin:</p>
                        <Sha label="this exact lesson artifact" value={meta.hashes?.lesson_sha256} />
                        <Sha label="against Stage A plan" value={meta.hashes?.plan_sha256} />
                        <Sha label="against foundation" value={meta.hashes?.foundation_fingerprint} />
                        <p>for {meta.subject} {meta.syllabus_code}@{meta.syllabus_version}. Any lesson-content change will make this approval stale.</p>
                        <div className="au-modal-actions">
                            <button onClick={() => setConfirming(false)}>Cancel</button>
                            <button className="au-primary" onClick={submitApproval}>Approve</button>
                        </div>
                    </div>
                </div>
            )}

            {revising && (
                <div className="au-modal-backdrop" onClick={() => setRevising(false)}>
                    <div className="au-modal au-modal-wide" onClick={(e) => e.stopPropagation()}>
                        <h3>Request revision</h3>
                        <label className="au-field">Overall note
                            <textarea rows={3} value={note} onChange={(e) => setNote(e.target.value)} placeholder="What must change before this lesson can be approved?" />
                        </label>
                        <label className="au-field">Severity
                            <select value={severity} onChange={(e) => setSeverity(e.target.value)}>
                                <option value="minor">minor</option>
                                <option value="major">major</option>
                            </select>
                        </label>
                        <h4>Feedback items</h4>
                        {items.map((it, i) => (
                            <div key={i} className="au-feedback-item">
                                <select value={it.category} onChange={(e) => setItems(items.map((x, j) => j === i ? { ...x, category: e.target.value } : x))}>
                                    {feedbackCategories.map((c) => <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>)}
                                </select>
                                <select value={it.severity} onChange={(e) => setItems(items.map((x, j) => j === i ? { ...x, severity: e.target.value } : x))}>
                                    <option value="minor">minor</option>
                                    <option value="major">major</option>
                                </select>
                                <select value={it.block_id || ''} onChange={(e) => {
                                    const b = blockIds.find((x) => x.id === e.target.value);
                                    setItems(items.map((x, j) => j === i ? { ...x, block_id: e.target.value || null, phase_id: b?.phase || null } : x));
                                }}>
                                    <option value="">whole lesson</option>
                                    {blockIds.map((b) => <option key={b.id} value={b.id}>{b.id}</option>)}
                                </select>
                                <input value={it.note} placeholder="what is wrong here" onChange={(e) => setItems(items.map((x, j) => j === i ? { ...x, note: e.target.value } : x))} />
                                <button onClick={() => setItems(items.filter((_, j) => j !== i))}>✕</button>
                            </div>
                        ))}
                        <button className="au-ghost" onClick={() => setItems([...items, { category: feedbackCategories[0], severity: 'minor', block_id: null, phase_id: null, note: '' }])}>+ add item</button>
                        <div className="au-modal-actions">
                            <button onClick={() => setRevising(false)}>Cancel</button>
                            <button className="au-danger" disabled={!note.trim() || items.some((i) => !i.note.trim())} onClick={submitRevision}>Submit — Needs Revision</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
