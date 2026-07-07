// ── Learning Studio · Inertia (React) entry ──────────────────────────────────
// The "actual learning section" is a real React app served by Laravel via
// Inertia (NOT islands): mistake-review, solutions, notes, interactive widgets,
// AI tutor. The rest of the site (auth, admin, the Mistake Bank list) stays
// Livewire; a gateway button hands off into here. Widgets are imported directly
// as components — the same files the island runtime uses.
import '../widgets/widgets.css';
import './learn.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    title: (title) => (title && title !== 'Learning Studio' ? `${title} · Learning Studio` : 'Learning Studio'),
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx');
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) throw new Error(`Inertia page not found: ${name}`);
        return page();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#34D399' },
});
