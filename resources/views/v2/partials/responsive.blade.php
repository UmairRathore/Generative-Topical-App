<style>
/* ====================================================================
   V2 responsive layer + shared image containers. Pure CSS, no build step.
   ==================================================================== */

/* --- Question diagrams ------------------------------------------------
   Width is set inline, uniform-scaled from each crop's own point size
   (QuestionImage::displayWidth) — every crop shares one 200 DPI, so one scale
   means the internal label text is a CONSTANT size across diagrams. Here we
   only keep them in-column (max-width:100%), borderless and centered. */
.v2-figure-wrap { text-align: center; margin: 14px 0; }
.v2-figure-wrap img {
    display: inline-block;
    max-width: 100%; height: auto;
    background: #fff;
    border-radius: 10px;
    padding: 4px;
}

/* --- Answer / option tables: same treatment, scaled a touch bigger --- */
.v2-table-wrap { text-align: center; margin: 14px 0; }
.v2-table-wrap img {
    display: inline-block;
    max-width: 100%; height: auto;
    background: #fff;
    border-radius: 10px;
    padding: 4px;
}

/* --- MCQ option images: 2-up grid, uniform, borderless ---------------- */
.v2-opt-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 4px; }
.v2-opt-img { display: block; width: 100%; height: 150px; object-fit: contain; background: #fff; border-radius: 8px; }

/* --- A/B/C/D selection indicator: the letter lives INSIDE the circle --- */
.v2-opt-circle {
    width: 26px; height: 26px; flex: none;
    border-radius: 50%;
    border: 1.5px solid var(--border);
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 12px; color: var(--text-soft);
    background: #fff;
}
.v2-opt-circle.is-on,
.v2-opt-circle.is-correct { background: var(--emerald-700); border-color: var(--emerald-700); color: #fff; }
.v2-opt-circle.is-wrong   { background: #ef4444;            border-color: #ef4444;            color: #fff; }

/* --- Below desktop: sidebar out of flow so content gets full width ----- */
@media (max-width: 1023px) {
    aside { position: fixed !important; top: 0; left: 0; height: 100vh; z-index: 40; }
    .fade-in { padding: 20px; }
}

/* --- Phone: stack grids, scroll tables, MCQ to 1 column --------------- */
@media (max-width: 640px) {
    [style*="grid-template-columns"] { grid-template-columns: 1fr !important; }
    .v2-opt-grid { grid-template-columns: 1fr; }   /* A / B / C / D stacked */
    .fade-in { padding: 14px !important; }
    table { display: block; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
    .fade-in [style*="padding: 22px"],
    .fade-in [style*="padding: 26px"],
    .fade-in [style*="padding: 28px"] { padding: 16px !important; }
}
</style>
