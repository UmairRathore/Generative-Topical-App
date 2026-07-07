import React, { useState } from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import Markdown from '../../lib/markdown.jsx';
import { BlockBadge } from './ui.jsx';

// ── Asset snapshot block ─────────────────────────────────────────────────────
// A worked solution / revision notes / common mistakes asset SNAPSHOTTED into
// the note (markdown kept verbatim; survives the asset being edited or hidden
// later). Rendered with the Studio's safe markdown-lite component. Collapsible
// so long solutions don't drown the page.

const LABELS = {
    worked_solution: { icon: 'Σ', label: 'Worked solution' },
    revision_notes:  { icon: '✎', label: 'Revision notes' },
    common_mistakes: { icon: '⚠️', label: 'Common mistakes' },
};

export const AssetSnapshotBlock = createReactBlockSpec(
    {
        type: 'asset_snapshot',
        propSchema: {
            assetType:  { default: 'worked_solution' },
            questionId: { default: 0 },
            title:      { default: '' },
            markdown:   { default: '' },
        },
        content: 'none',
    },
    {
        render: ({ block }) => {
            const [open, setOpen] = useState(true);
            const meta = LABELS[block.props.assetType] || LABELS.worked_solution;
            return (
                <div className={`nt-snapshot v-${block.props.assetType}`} contentEditable={false}>
                    <div className="nt-block-head">
                        <BlockBadge icon={meta.icon}>{meta.label}</BlockBadge>
                        {block.props.title && <span className="nt-snapshot-title">{block.props.title}</span>}
                        <button type="button" className="nt-mini-btn" onClick={() => setOpen((o) => !o)}>
                            {open ? 'Collapse' : 'Expand'}
                        </button>
                    </div>
                    {open && (
                        <div className="ls-prose nt-snapshot-body">
                            <Markdown text={block.props.markdown} />
                        </div>
                    )}
                </div>
            );
        },
    },
);
