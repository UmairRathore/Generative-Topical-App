import React from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import { useFigureUrl } from '../figureUrls.jsx';
import { BlockBadge } from './ui.jsx';

// ── Question figure block ────────────────────────────────────────────────────
// A REFERENCE to a question's exam diagram — stores question / version / image
// path only, never a URL. The signed URL is minted server-side per view (after
// an access re-check) and delivered through the figure-URL context. If the
// student no longer has access, no URL arrives and the block says so.
//
// props: { questionId, questionVersionId, imagePath, caption }

export const QuestionFigureBlock = createReactBlockSpec(
    {
        type: 'question_figure',
        propSchema: {
            questionId:        { default: 0 },
            questionVersionId: { default: 0 },
            imagePath:         { default: '' },
            caption:           { default: '' },
        },
        content: 'none',
    },
    {
        render: ({ block }) => {
            const url = useFigureUrl(block.props.imagePath);
            const { caption } = block.props;
            return (
                <figure className="nt-figure" contentEditable={false}>
                    <div className="nt-block-head">
                        <BlockBadge icon="▦">Question diagram</BlockBadge>
                    </div>
                    {url ? (
                        <img className="nt-figure-img" src={url} alt={caption || 'question diagram'} loading="lazy" />
                    ) : (
                        <div className="nt-figure-denied">
                            Diagram unavailable — it loads only while you have access to this question.
                        </div>
                    )}
                    {caption && <figcaption className="nt-figure-cap">{caption}</figcaption>}
                </figure>
            );
        },
    },
);
