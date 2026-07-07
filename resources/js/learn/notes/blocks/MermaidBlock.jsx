import React, { useState } from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import Flow from '../../studio/Flow.jsx';
import { AutoTextarea, BlockBadge, stopKeys } from './ui.jsx';

// ── Mermaid block ────────────────────────────────────────────────────────────
// Renders via the Studio's Flow component (lazy mermaid chunk, error → source
// fallback). "Edit" flips to a monospace source editor.

export const MermaidBlock = createReactBlockSpec(
    {
        type: 'mermaid',
        propSchema: { source: { default: '' } },
        content: 'none',
    },
    {
        render: ({ block, editor }) => {
            const [editing, setEditing] = useState(!block.props.source);
            return (
                <div className="nt-mermaid" contentEditable={false} onKeyDownCapture={stopKeys}>
                    <div className="nt-block-head">
                        <BlockBadge icon="⋔">Flow</BlockBadge>
                        <button type="button" className="nt-mini-btn" onClick={() => setEditing((e) => !e)}>
                            {editing ? 'Preview' : 'Edit'}
                        </button>
                    </div>
                    {editing ? (
                        <AutoTextarea
                            mono
                            value={block.props.source}
                            placeholder={'flowchart TD\n  A[Start] --> B{Decision}'}
                            onChange={(source) => editor.updateBlock(block, { props: { source } })}
                        />
                    ) : (
                        <Flow source={block.props.source} />
                    )}
                </div>
            );
        },
    },
);
