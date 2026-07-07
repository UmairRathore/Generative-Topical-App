import React from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import { stopKeys } from './ui.jsx';

// ── Page break block ─────────────────────────────────────────────────────────
// Book-style divider inside one continuous note: students split a note into
// "pages" without separate documents. The optional label names the section
// ("Forces", "Mock 2 revision") and is what the import picker shows when
// choosing WHERE to insert content (sections are split on these blocks).

export const PageBreakBlock = createReactBlockSpec(
    {
        type: 'page_break',
        propSchema: { label: { default: '' } },
        content: 'none',
    },
    {
        render: ({ block, editor }) => (
            <div className="nt-pagebreak" contentEditable={false} onKeyDownCapture={stopKeys}>
                <span className="nt-pagebreak-line" />
                <span className="nt-pagebreak-pill">
                    <span className="nt-pagebreak-ic">⤓</span>
                    <input
                        className="nt-pagebreak-label"
                        value={block.props.label}
                        placeholder="Page break — label it"
                        onKeyDown={stopKeys}
                        onChange={(e) => editor.updateBlock(block, { props: { label: e.target.value } })}
                    />
                </span>
                <span className="nt-pagebreak-line" />
            </div>
        ),
    },
);
