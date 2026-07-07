import React from 'react';
import { Head } from '@inertiajs/react';
import Calculator from '../../widgets/widgets/Calculator.jsx';
import PhotosynthesisLake from '../../widgets/widgets/PhotosynthesisLake.jsx';

// ── Learning Studio · Home ────────────────────────────────────────────────────
// First real Inertia page. Proves the whole model end-to-end:
//   • a React page served by Laravel (no islands, no Blade wrapper per widget);
//   • ONE Calculator component rendering three different exam questions from
//     three configs across three subjects;
//   • an existing widget (the photosynthesis sim) dropped straight in.

const CALCULATOR_QUESTIONS = [
    {
        subject: 'Chemistry · 9701',
        prompt: 'What amount, in moles, is present in 8.0 g of sodium hydroxide, NaOH? (Mᵣ = 40)',
        symbol: 'n',
        formula: 'mass / mr',
        inputs: [
            { key: 'mass', label: 'Mass', unit: 'g', value: 8.0, min: 0, max: 40, step: 0.5, editable: true },
            { key: 'mr', label: 'Mᵣ (NaOH)', value: 40, editable: false },
        ],
        result: { label: 'Amount of substance', unit: 'mol', precision: 3 },
        options: [
            { label: '0.10 mol', value: 0.10 },
            { label: '0.20 mol', value: 0.20 },
            { label: '0.40 mol', value: 0.40 },
            { label: '5.0 mol', value: 5.0 },
        ],
        answer: 1,
    },
    {
        subject: 'Physics · 9702',
        prompt: 'A lamp of power 60 W is switched on for 5.0 minutes (= 300 s). How much energy does it transfer?',
        symbol: 'E',
        formula: 'power * time',
        inputs: [
            { key: 'power', label: 'Power', unit: 'W', value: 60, min: 0, max: 200, step: 5, editable: true },
            { key: 'time', label: 'Time', unit: 's', value: 300, min: 0, max: 600, step: 10, editable: true },
        ],
        result: { label: 'Energy transferred', unit: 'J', precision: 3 },
        options: [
            { label: '12 J', value: 12 },
            { label: '300 J', value: 300 },
            { label: '18 000 J', value: 18000 },
            { label: '1 080 000 J', value: 1080000 },
        ],
        answer: 2,
    },
    {
        subject: 'Biology · 9700',
        prompt: 'A photomicrograph of a cell measures 30 mm across. The actual cell is 0.060 mm wide. What is the magnification?',
        symbol: 'M',
        formula: 'image / actual',
        inputs: [
            { key: 'image', label: 'Image size', unit: 'mm', value: 30, min: 0, max: 60, step: 1, editable: true },
            { key: 'actual', label: 'Actual size', unit: 'mm', value: 0.06, min: 0.01, max: 0.2, step: 0.005, editable: true },
        ],
        result: { label: 'Magnification', unit: '×', precision: 3 },
        options: [
            { label: '× 50', value: 50 },
            { label: '× 500', value: 500 },
            { label: '× 5000', value: 5000 },
            { label: '× 0.002', value: 0.002 },
        ],
        answer: 1,
    },
];

export default function Home({ appName = 'CambPast' }) {
    return (
        <>
            <Head title="Learning Studio" />
            <div className="ls-shell">
                <div className="ls-top">
                    <div className="ls-mark">◆</div>
                    <div className="ls-brand">
                        Learning Studio
                        <span>{appName} · interactive learning layer</span>
                    </div>
                    <div className="sp" />
                    <a className="ls-back" href="/">← Back to dashboard</a>
                </div>

                <div className="ls-hero">
                    <div className="ls-eyebrow">React · Inertia · one archetype, every question</div>
                    <h1 className="ls-serif">Learn from every mistake, interactively.</h1>
                    <p>
                        This is the React learning zone — a real Inertia app served by Laravel, separate from the
                        Livewire Mistake Bank you came from. Below, a single <em>Calculator</em> component renders
                        three different exam questions across three subjects, driven entirely by config.
                    </p>
                </div>

                <section className="ls-sec">
                    <div className="ls-lbl">Calculator archetype</div>
                    <h2 className="ls-serif">One component · three questions · zero bespoke code</h2>
                    <p className="lead">
                        Each card below is the same <code>&lt;Calculator /&gt;</code> fed a different config. Drag a
                        slider to explore “what if?”, watch the working recompute, and see which option your value
                        lands on. This family alone covers ~31% of all interactive questions.
                    </p>
                    <div className="ls-stack">
                        {CALCULATOR_QUESTIONS.map((cfg, i) => (
                            <div className="ls-widget-card" key={i}>
                                <Calculator config={cfg} />
                            </div>
                        ))}
                    </div>
                </section>

                <section className="ls-sec">
                    <div className="ls-lbl">Interactive simulation</div>
                    <h2 className="ls-serif">The same widget files, rendered natively in React</h2>
                    <p className="lead">
                        The photosynthesis limiting-factors sim is imported straight into this Inertia page — no
                        island mounting, no Blade wrapper. Every widget in the bank works both ways.
                    </p>
                    <div className="ls-widget-card">
                        <PhotosynthesisLake config={{ sun: 80, clarity: 90, co2: 70, temp: 22 }} />
                    </div>
                </section>

                <div className="ls-foot">
                    <b>Stack:</b> React 18.3 · Inertia v2 · Vite · Laravel. The Mistake Bank stays Livewire;
                    this studio is the React-first learning section it hands off to.
                </div>
            </div>
        </>
    );
}
