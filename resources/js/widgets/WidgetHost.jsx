import React, { Suspense, useCallback, useEffect, useRef } from 'react';

// ── WidgetHost ───────────────────────────────────────────────────────────────
// Wraps every mounted widget with:
//   • an error boundary (a broken widget never takes the page down),
//   • Suspense (widgets are code-split / lazy),
//   • the NOTES CONTRACT: exposes getState()/setState() on the mount element and
//     in window.CambWidgets, and gives the widget an onAddToNote(config) callback
//     that emits a framework-agnostic `camb:add-to-note` DOM event. The host app
//     (mistake hub / notes / AI tutor) decides what to do with that intent.

class ErrorBoundary extends React.Component {
    constructor(p) { super(p); this.state = { err: null }; }
    static getDerivedStateFromError(err) { return { err }; }
    componentDidCatch(err) { console.error('[widget] render error', err); }
    render() {
        if (this.state.err) {
            return <div className="cw-error">This interactive couldn’t load.</div>;
        }
        return this.props.children;
    }
}

export default function WidgetHost({ Comp, type, config, el }) {
    const apiRef = useRef(null);

    const onReady = useCallback((api) => {
        apiRef.current = api;
        el.widgetApi = api;                                  // imperative handle on the DOM node
        if (window.CambWidgets) window.CambWidgets._register(el, api);
    }, [el]);

    const onAddToNote = useCallback((config) => {
        el.dispatchEvent(new CustomEvent('camb:add-to-note', {
            bubbles: true,
            detail: { widget: type, config },
        }));
    }, [el, type]);

    useEffect(() => () => { if (window.CambWidgets) window.CambWidgets._unregister(el); }, [el]);

    return (
        <div className="cw-root" data-widget-type={type}>
            <ErrorBoundary>
                <Suspense fallback={<div className="cw-loading">Loading interactive…</div>}>
                    <Comp config={config} onReady={onReady} onAddToNote={onAddToNote} />
                </Suspense>
            </ErrorBoundary>
        </div>
    );
}
