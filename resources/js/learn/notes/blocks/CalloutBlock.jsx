import React from 'react';
import { createReactBlockSpec } from '@blocknote/react';

// ── Callout block ────────────────────────────────────────────────────────────
// Study callouts: definition, formula, exam tip, common mistake, warning…
// Inline content stays editable; clicking the icon cycles the variant.
// Import bridges also use 'correct' / 'wrong' for per-option explanations.

export const CALLOUT_VARIANTS = {
    info:       { icon: 'ℹ️', label: 'Note' },
    definition: { icon: '📖', label: 'Definition' },
    formula:    { icon: 'Σ',  label: 'Formula' },
    tip:        { icon: '💡', label: 'Exam tip' },
    mistake:    { icon: '⚠️', label: 'Common mistake' },
    warn:       { icon: '🚨', label: 'Warning' },
    memory:     { icon: '🧠', label: 'Memory trick' },
    correct:    { icon: '✓',  label: 'Correct' },
    wrong:      { icon: '✕',  label: 'Why it’s wrong' },
};

const CYCLE = ['info', 'definition', 'formula', 'tip', 'mistake', 'warn', 'memory'];

export const CalloutBlock = createReactBlockSpec(
    {
        type: 'callout',
        propSchema: { variant: { default: 'info' } },
        content: 'inline',
    },
    {
        render: ({ block, editor, contentRef }) => {
            const variant = CALLOUT_VARIANTS[block.props.variant] ? block.props.variant : 'info';
            const v = CALLOUT_VARIANTS[variant];
            const cycle = () => {
                const i = CYCLE.indexOf(variant);
                editor.updateBlock(block, { props: { variant: CYCLE[(i + 1) % CYCLE.length] } });
            };
            return (
                <div className={`nt-callout v-${variant}`}>
                    <button
                        type="button"
                        className="nt-callout-ic"
                        contentEditable={false}
                        title={`${v.label} — click to change`}
                        onClick={cycle}
                    >
                        {v.icon}
                    </button>
                    <div className="nt-callout-body">
                        <span className="nt-callout-lbl" contentEditable={false}>{v.label}</span>
                        <div className="nt-callout-text" ref={contentRef} />
                    </div>
                </div>
            );
        },
    },
);
