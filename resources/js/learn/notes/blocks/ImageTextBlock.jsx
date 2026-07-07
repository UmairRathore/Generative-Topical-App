import React, { useRef, useState } from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import { BlockBadge } from './ui.jsx';

// ── Image + text block (two columns) ─────────────────────────────────────────
// License-clean side-by-side layout (no GPL xl-multi-column): an image column
// and a rich-text column. The text side is real BlockNote inline content; the
// image side uploads through the editor's own uploadFile (private notes store).
// side: which side the IMAGE sits on. ratio: image column width % (25-70),
// adjustable by dragging the divider. No floating text wrap - two clean columns.
//
// DOM order is fixed [text | divider | image] and side=left just flips the row
// with CSS row-reverse, so ProseMirror's contentRef node never moves in the DOM.

const clampRatio = (v) => Math.min(70, Math.max(25, Math.round(v)));

export const ImageTextBlock = createReactBlockSpec(
    {
        type: 'image_text',
        propSchema: {
            imageUrl: { default: '' },
            caption:  { default: '' },
            side:     { default: 'left' },  // image column side: 'left' | 'right'
            ratio:    { default: 40 },      // image column width %
        },
        content: 'inline',
    },
    {
        render: ({ block, editor, contentRef }) => {
            const { imageUrl, caption, side, ratio } = block.props;
            const wrapRef = useRef(null);
            const fileRef = useRef(null);
            const [liveRatio, setLiveRatio] = useState(null); // transient, while dragging
            const [busy, setBusy] = useState(false);
            const r = clampRatio(liveRatio ?? ratio);

            const onFile = async (e) => {
                const file = e.target.files?.[0];
                e.target.value = '';
                if (!file || !editor.uploadFile) return;
                setBusy(true);
                try {
                    const res = await editor.uploadFile(file);
                    const url = typeof res === 'string' ? res : res?.props?.url;
                    if (url) editor.updateBlock(block, { props: { imageUrl: url } });
                } finally {
                    setBusy(false);
                }
            };

            // Drag the divider to change the image column's share of the row.
            const startDrag = (e) => {
                e.preventDefault();
                const rect = wrapRef.current.getBoundingClientRect();
                const pctFor = (clientX) => {
                    let pct = ((clientX - rect.left) / rect.width) * 100;
                    if (side === 'right') pct = 100 - pct; // image on the right: measure from that edge
                    return clampRatio(pct);
                };
                const move = (ev) => setLiveRatio(pctFor(ev.clientX));
                const up = (ev) => {
                    window.removeEventListener('pointermove', move);
                    window.removeEventListener('pointerup', up);
                    setLiveRatio(null);
                    editor.updateBlock(block, { props: { ratio: pctFor(ev.clientX) } });
                };
                window.addEventListener('pointermove', move);
                window.addEventListener('pointerup', up);
            };

            return (
                <div className="nt-cols" ref={wrapRef} data-side={side}>
                    <div className="nt-cols-head" contentEditable={false}>
                        <BlockBadge icon="◫">Image + text</BlockBadge>
                        <button
                            type="button"
                            className="nt-mini-btn"
                            onClick={() => editor.updateBlock(block, { props: { side: side === 'left' ? 'right' : 'left' } })}
                        >
                            {side === 'left' ? 'Image → right' : 'Image → left'}
                        </button>
                    </div>

                    <div className="nt-cols-row">
                        <div className="nt-cols-text" ref={contentRef} />
                        <div
                            className="nt-cols-divider"
                            contentEditable={false}
                            onPointerDown={startDrag}
                            title="Drag to resize the columns"
                        />
                        <figure className="nt-cols-img" contentEditable={false} style={{ flexBasis: `${r}%` }}>
                            {imageUrl ? (
                                <>
                                    <img src={imageUrl} alt={caption || 'note image'} draggable={false} />
                                    <input
                                        className="nt-cols-cap"
                                        value={caption}
                                        placeholder="Caption…"
                                        onKeyDown={(e) => e.stopPropagation()}
                                        onChange={(e) => editor.updateBlock(block, { props: { caption: e.target.value } })}
                                    />
                                </>
                            ) : (
                                <button type="button" className="nt-cols-upload" disabled={busy} onClick={() => fileRef.current?.click()}>
                                    {busy ? 'Uploading…' : '🖼 Upload image'}
                                </button>
                            )}
                            <input
                                ref={fileRef}
                                type="file"
                                accept="image/png,image/jpeg,image/gif,image/webp"
                                hidden
                                onChange={onFile}
                            />
                        </figure>
                    </div>
                </div>
            );
        },
    },
);
