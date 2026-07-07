import React, { useMemo } from 'react';
import { createReactBlockSpec } from '@blocknote/react';
import WidgetRenderer from '../../WidgetRenderer.jsx';
import { BlockBadge } from './ui.jsx';

// ── Widget block ─────────────────────────────────────────────────────────────
// A live interactive widget inside a note. Persists only {widgetType, config}
// (the notes contract: config is exactly what `camb:add-to-note` emits) and
// re-mounts through the SAME registry/components as the island runtime, so a
// saved diagram is the widget as the student left it — still fully playable.
// The whole block is contentEditable=false: ProseMirror never touches the
// widget's DOM; React owns it via WidgetRenderer (lazy, Suspense-wrapped).

export const WidgetBlock = createReactBlockSpec(
    {
        type: 'widget',
        propSchema: {
            widgetType: { default: '' },
            config:     { default: '{}' }, // JSON string (BlockNote props are primitives)
        },
        content: 'none',
    },
    {
        render: ({ block }) => {
            const { widgetType, config } = block.props;
            const parsed = useMemo(() => {
                try { return JSON.parse(config || '{}'); } catch { return null; }
            }, [config]);

            if (!widgetType || parsed === null) {
                return (
                    <div className="nt-widget nt-widget-broken" contentEditable={false}>
                        <BlockBadge icon="▲">Interactive</BlockBadge>
                        <p className="nt-muted">This widget can’t be shown ({widgetType || 'unknown type'}).</p>
                    </div>
                );
            }

            const prettyType = widgetType.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
            return (
                <div className="nt-widget" contentEditable={false}>
                    <div className="nt-block-head">
                        <BlockBadge icon="▲">Interactive · {prettyType}</BlockBadge>
                    </div>
                    <div className="nt-widget-body">
                        <WidgetRenderer type={widgetType} config={parsed} />
                    </div>
                </div>
            );
        },
    },
);
