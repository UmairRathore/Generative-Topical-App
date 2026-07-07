import { BlockNoteSchema, defaultBlockSpecs } from '@blocknote/core';
import { insertOrUpdateBlockForSlashMenu } from '@blocknote/core/extensions';
import { CalloutBlock } from './blocks/CalloutBlock.jsx';
import { FlashcardBlock, MemcardBlock } from './blocks/CardBlocks.jsx';
import { MermaidBlock } from './blocks/MermaidBlock.jsx';
import { EquationBlock } from './blocks/EquationBlock.jsx';
import { ImageTextBlock } from './blocks/ImageTextBlock.jsx';
import { PageBreakBlock } from './blocks/PageBreakBlock.jsx';
import { WidgetBlock } from './blocks/WidgetBlock.jsx';
import { AssetSnapshotBlock } from './blocks/AssetSnapshotBlock.jsx';
import { MistakeBlock } from './blocks/MistakeBlock.jsx';
import { QuestionFigureBlock } from './blocks/QuestionFigureBlock.jsx';

// ── Notes schema ─────────────────────────────────────────────────────────────
// BlockNote's standard set (minus raw file embeds for MVP) + the platform
// blocks. widget / asset_snapshot / mistake are created by the import bridge;
// callout / flashcard / memcard / mermaid also insert from the slash menu.

const { audio, video, file, ...standard } = defaultBlockSpecs;

// createReactBlockSpec returns a FACTORY (options) => BlockSpec in v0.51 —
// each custom block must be invoked when building the schema.
export const schema = BlockNoteSchema.create({
    blockSpecs: {
        ...standard,
        callout:        CalloutBlock(),
        flashcard:      FlashcardBlock(),
        memcard:        MemcardBlock(),
        mermaid:        MermaidBlock(),
        equation:       EquationBlock(),
        image_text:     ImageTextBlock(),
        page_break:     PageBreakBlock(),
        widget:          WidgetBlock(),
        asset_snapshot:  AssetSnapshotBlock(),
        mistake:         MistakeBlock(),
        question_figure: QuestionFigureBlock(),
    },
});

/** Extra slash-menu entries for the study blocks students create by hand. */
export function studySlashItems(editor) {
    const insert = (type, props = {}) => () => insertOrUpdateBlockForSlashMenu(editor, { type, props });

    return [
        {
            title: 'Callout',
            subtext: 'Definition, formula, exam tip, common mistake…',
            aliases: ['callout', 'definition', 'formula', 'tip', 'warning', 'mistake'],
            group: 'Study blocks',
            icon: <span className="nt-slash-ic">💡</span>,
            onItemClick: insert('callout'),
        },
        {
            title: 'Flashcard',
            subtext: 'Front / back recall card',
            aliases: ['flashcard', 'card', 'recall'],
            group: 'Study blocks',
            icon: <span className="nt-slash-ic">◑</span>,
            onItemClick: insert('flashcard'),
        },
        {
            title: 'Memcard',
            subtext: 'Short memory cue — formula, term, key fact',
            aliases: ['memcard', 'mnemonic', 'memory'],
            group: 'Study blocks',
            icon: <span className="nt-slash-ic">▤</span>,
            onItemClick: insert('memcard'),
        },
        {
            title: 'Mermaid diagram',
            subtext: 'Flowchart from mermaid source',
            aliases: ['mermaid', 'flow', 'diagram', 'flowchart'],
            group: 'Study blocks',
            icon: <span className="nt-slash-ic">⋔</span>,
            onItemClick: insert('mermaid'),
        },
        {
            title: 'Equation',
            subtext: 'LaTeX / chemistry maths (KaTeX)',
            aliases: ['equation', 'latex', 'math', 'formula', 'katex', 'chem'],
            group: 'Study blocks',
            icon: <span className="nt-slash-ic">∑</span>,
            onItemClick: insert('equation'),
        },
        {
            title: 'Image + text',
            subtext: 'Two columns — diagram beside your notes',
            aliases: ['image', 'columns', 'side', 'layout', 'two', 'diagram'],
            group: 'Study blocks',
            icon: <span className="nt-slash-ic">◫</span>,
            onItemClick: insert('image_text'),
        },
        {
            title: 'Page break',
            subtext: 'Split this note into book-style pages',
            aliases: ['page', 'break', 'divider', 'section', 'chapter'],
            group: 'Study blocks',
            icon: <span className="nt-slash-ic">⤓</span>,
            onItemClick: insert('page_break'),
        },
    ];
}
