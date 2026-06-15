<style>
/* ====================================================================
   V2 responsive layer + shared image containers.
   Loaded in every V2 layout <head>. Pure CSS (no build step needed).
   ==================================================================== */

/* --- Diagrams (question diagrams AND the answer/option-table image) ----
   Centered, borderless, aspect preserved, capped to one consistent box so
   every figure is roughly the same size. White panel only so black line-art
   stays visible in dark mode. */
.v2-figure-wrap { text-align: center; margin: 14px 0; }
.v2-figure-wrap img {
    display: inline-block;
    max-width: min(100%, 360px);   /* consistent, "a bit smaller" */
    max-height: 280px;
    width: auto;
    height: auto;
    object-fit: contain;
    background: #fff;
    border-radius: 10px;
    padding: 4px;                   /* breathing room, no hard border */
}

/* --- MCQ option images: 2x2 grid (A B / C D), bigger, uniform, borderless -- */
.v2-opt-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 4px;
}
.v2-opt-img {
    display: block;
    width: 100%;
    height: 150px;                  /* fixed height + equal cell width => same size */
    object-fit: contain;
    background: #fff;
    border-radius: 8px;             /* no border — not a cropped box */
}

/* --- Below desktop: sidebar out of flow so content gets full width ------ */
@media (max-width: 1023px) {
    aside { position: fixed !important; top: 0; left: 0; height: 100vh; z-index: 40; }
    .fade-in { padding: 20px; }
}

/* --- Phone (priority): stack grids, scroll tables, scale media down ----- */
@media (max-width: 640px) {
    /* Every INLINE css grid collapses to one column. The MCQ grid uses a
       class (no inline grid-template-columns) so it is NOT affected here. */
    [style*="grid-template-columns"] { grid-template-columns: 1fr !important; }

    .fade-in { padding: 14px !important; }

    table { display: block; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }

    .v2-figure-wrap img { max-height: 240px; }
    .v2-opt-img { height: 132px; }

    .fade-in [style*="padding: 22px"],
    .fade-in [style*="padding: 26px"],
    .fade-in [style*="padding: 28px"] { padding: 16px !important; }
}

/* keep the 2-up MCQ grid even on phones; only drop to 1 column when truly tiny */
@media (max-width: 340px) {
    .v2-opt-grid { grid-template-columns: 1fr; }
}
</style>
