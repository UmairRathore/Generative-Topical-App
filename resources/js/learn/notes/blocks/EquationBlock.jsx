import React, { useState } from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import { Tex } from '../../lib/tex.jsx';
import { AutoTextarea, BlockBadge, stopKeys } from './ui.jsx';

// ── Equation block ───────────────────────────────────────────────────────────
// Student-authored display maths. Stores raw LaTeX (no delimiters needed) and
// renders it with KaTeX; "Edit" flips to a monospace source editor. mhchem is
// loaded, so chemistry works too: \ce{6CO2 + 6H2O -> C6H12O6 + 6O2}.

export const EquationBlock = createReactBlockSpec(
    {
        type: 'equation',
        propSchema: { latex: { default: '' } },
        content: 'none',
    },
    {
        render: ({ block, editor }) => {
            const [editing, setEditing] = useState(!block.props.latex);
            const { latex } = block.props;
            return (
                <div className="nt-equation" contentEditable={false} onKeyDownCapture={stopKeys}>
                    <div className="nt-block-head">
                        <BlockBadge icon="∑">Equation</BlockBadge>
                        <button type="button" className="nt-mini-btn" onClick={() => setEditing((e) => !e)}>
                            {editing ? 'Preview' : 'Edit'}
                        </button>
                    </div>
                    {editing ? (
                        <AutoTextarea
                            mono
                            value={latex}
                            placeholder={'e.g.  p = \\frac{F}{A}   or   \\ce{H2SO4}'}
                            onChange={(v) => editor.updateBlock(block, { props: { latex: v } })}
                        />
                    ) : (
                        <div className="nt-equation-view">
                            {latex ? <Tex tex={latex} display /> : <span className="nt-muted">Empty equation — click Edit.</span>}
                        </div>
                    )}
                </div>
            );
        },
    },
);
