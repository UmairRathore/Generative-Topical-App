import './widgets.css';
import React, { createElement, lazy } from 'react';
import { createRoot } from 'react-dom/client';
import { registry, hasWidget } from './registry.js';
import WidgetHost from './WidgetHost.jsx';

// ── Island bootstrap ─────────────────────────────────────────────────────────
// Any element with [data-widget="<type>"] (+ optional data-config='{…}') becomes
// a mounted React widget. This is the single entry point for the whole
// interactive learning layer; Livewire/Alpine elsewhere is untouched.
//
// window.CambWidgets is the public surface the rest of the app (notes / mistake
// hub / AI tutor) talks to:
//   CambWidgets.mount(el)            → mount a widget on a fresh [data-widget] el
//   CambWidgets.mountAll(root?)      → scan + mount everything not yet mounted
//   CambWidgets.get(el)              → { getState(), setState() } for that widget
//   CambWidgets.render(el, type, cfg)→ build+mount a widget from type+config JSON
//                                       (used by notes to re-instantiate a saved diagram)

const _lazyCache = {};
function componentFor(type) {
    if (!_lazyCache[type]) _lazyCache[type] = lazy(registry[type]);
    return _lazyCache[type];
}

function mount(el) {
    if (!el || el.__cwMounted) return;
    const type = el.dataset.widget;
    if (!hasWidget(type)) { console.warn('[widgets] unknown widget type:', type); return; }

    let config = {};
    try { config = el.dataset.config ? JSON.parse(el.dataset.config) : {}; }
    catch (e) { console.warn('[widgets] bad data-config on', type, e); }

    el.__cwMounted = true;
    const root = createRoot(el);
    el.__cwRoot = root;
    root.render(createElement(WidgetHost, { Comp: componentFor(type), type, config, el }));
}

const CambWidgets = {
    _reg: new Map(),
    _register(el, api) { this._reg.set(el, api); },
    _unregister(el) { this._reg.delete(el); },
    get(el) { return this._reg.get(el) || el.widgetApi || null; },
    mount,
    mountAll(root = document) { root.querySelectorAll('[data-widget]').forEach(mount); },
    // Build a fresh mount point from a saved note {type, config} and mount it live.
    render(container, type, config) {
        const el = document.createElement('div');
        el.dataset.widget = type;
        el.dataset.config = JSON.stringify(config || {});
        container.appendChild(el);
        mount(el);
        return el;
    },
    unmount(el) { if (el.__cwRoot) { el.__cwRoot.unmount(); el.__cwMounted = false; } },
};

window.CambWidgets = CambWidgets;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CambWidgets.mountAll());
} else {
    CambWidgets.mountAll();
}

export default CambWidgets;
