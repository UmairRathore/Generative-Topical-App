import React from 'react';

// ── Flashcard / Memcard grid ─────────────────────────────────────────────────
// All cards shown at once (prompt + answer both visible) — no flip, no paging.
// cards = [{ front, back }]. Used for both flashcards and memcards.

export default function Flashcards({ cards = [] }) {
    if (!cards.length) return <p className="lead">No cards yet.</p>;
    return (
        <div className="ls-cards">
            {cards.map((c, i) => (
                <div className="ls-qa" key={i}>
                    <div className="ls-qa-q">{c.front}</div>
                    <div className="ls-qa-a">{c.back}</div>
                </div>
            ))}
        </div>
    );
}
