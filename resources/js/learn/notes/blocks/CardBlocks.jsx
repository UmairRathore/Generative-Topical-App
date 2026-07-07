import React, { useState } from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import { MathText } from '../../lib/tex.jsx';
import { AutoTextarea, BlockBadge, stopKeys } from './ui.jsx';

// ── Flashcard / Memcard blocks ───────────────────────────────────────────────
// One card per block: front + back live in props (mirrors the learning-asset
// payload [{front, back}]), so imports map 1:1 and cards stay individually
// movable/duplicable. Same look as the Studio's ls-qa cards.

function makeCardBlock(type, label, icon) {
    return createReactBlockSpec(
        {
            type,
            propSchema: {
                front: { default: '' },
                back:  { default: '' },
            },
            content: 'none',
        },
        {
            render: ({ block, editor }) => {
                const { front, back } = block.props;
                // New card → author; imported/filled card → show rendered (maths too).
                const [editing, setEditing] = useState(!front && !back);
                return (
                    <div className={`nt-card nt-${type}`} onKeyDownCapture={stopKeys}>
                        <div className="nt-block-head">
                            <BlockBadge icon={icon}>{label}</BlockBadge>
                            <button type="button" className="nt-mini-btn" onClick={() => setEditing((e) => !e)}>
                                {editing ? 'Done' : 'Edit'}
                            </button>
                        </div>
                        {editing ? (
                            <>
                                <AutoTextarea
                                    className="nt-card-q"
                                    value={front}
                                    placeholder={type === 'memcard' ? 'Cue — formula / term / fact' : 'Front — the prompt'}
                                    onChange={(v) => editor.updateBlock(block, { props: { front: v } })}
                                />
                                <AutoTextarea
                                    className="nt-card-a"
                                    value={back}
                                    placeholder={type === 'memcard' ? 'Recall — what must come to mind' : 'Back — the answer'}
                                    onChange={(v) => editor.updateBlock(block, { props: { back: v } })}
                                />
                            </>
                        ) : (
                            <>
                                <div className="nt-card-q"><MathText text={front} /></div>
                                <div className="nt-card-a"><MathText text={back} /></div>
                            </>
                        )}
                    </div>
                );
            },
        },
    );
}

export const FlashcardBlock = makeCardBlock('flashcard', 'Flashcard', '◑');
export const MemcardBlock = makeCardBlock('memcard', 'Memcard', '▤');
