import React from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import { BlockBadge } from './ui.jsx';

// ── Mistake block ────────────────────────────────────────────────────────────
// A LIVE reference into the Mistake Bank (by hashid) plus a thin display
// snapshot (stem, your answer vs correct) so the block still reads if the
// mistake is later archived. Deep-links to the Learning Studio.

export const MistakeBlock = createReactBlockSpec(
    {
        type: 'mistake',
        propSchema: {
            mistakeId:    { default: '' },
            questionStem: { default: '' },
            selected:     { default: '' },
            correct:      { default: '' },
            subject:      { default: '' },
            topic:        { default: '' },
            sourcePaper:  { default: '' },
            studioUrl:    { default: '' },
        },
        content: 'none',
    },
    {
        render: ({ block }) => {
            const p = block.props;
            const eyebrow = [p.subject, p.topic].filter(Boolean).join(' · ');
            return (
                <div className="nt-mistake" contentEditable={false}>
                    <div className="nt-block-head">
                        <BlockBadge icon="✕">My mistake</BlockBadge>
                        {eyebrow && <span className="nt-mistake-eyebrow">{eyebrow}</span>}
                        {p.sourcePaper && <span className="nt-mistake-paper">{p.sourcePaper}</span>}
                    </div>
                    {p.questionStem && <div className="nt-mistake-stem">{p.questionStem}</div>}
                    <div className="nt-mistake-row">
                        {p.selected && <span className="nt-chip c-bad">Your answer · {p.selected}</span>}
                        {p.correct && <span className="nt-chip c-ok">Correct · {p.correct}</span>}
                        {p.studioUrl && (
                            <a className="nt-chip c-link" href={p.studioUrl}>
                                Open in Studio →
                            </a>
                        )}
                    </div>
                </div>
            );
        },
    },
);
