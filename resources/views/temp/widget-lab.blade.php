<!DOCTYPE html>
<html lang="en" data-mode="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Widget Lab · React island + notes round-trip</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|fraunces:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />

    {{-- App design tokens (brand: --ok, --accent, --bad, --paper …) --}}
    @include('v2.partials.theme')

    {{-- React island runtime (built by Vite). Mounts every [data-widget] on the page. --}}
    @viteReactRefresh
    @vite(['resources/js/widgets/index.jsx'])
</head>
<body>
@verbatim
<style>
    /* cinematic frame tokens the widgets pull from (--ink, --bg-3, --line …) */
    :root{
        --bg:#06110C; --bg-2:#08160F; --bg-3:#0B1E15;
        --panel:rgba(255,255,255,.035); --panel-2:rgba(255,255,255,.055);
        --line:rgba(160,200,175,.14);
        --ink:#EAF6EE; --ink-soft:#A6C4B3; --ink-faint:#68877A;
    }
    html[data-mode="light"]{
        --bg:#EDF4EF; --bg-3:#FFFFFF; --panel:rgba(255,255,255,.78);
        --line:rgba(15,60,35,.12); --ink:#0B2318; --ink-soft:#3C5A49; --ink-faint:#6E8877;
    }
    *{box-sizing:border-box;} html,body{margin:0;padding:0;}
    body{font-family:'Inter',system-ui,sans-serif; background:var(--bg); color:var(--ink); -webkit-font-smoothing:antialiased; line-height:1.6; transition:background .5s;}
    body::before{content:""; position:fixed; inset:0; z-index:0; pointer-events:none; background:radial-gradient(900px 520px at 78% -8%, rgba(52,211,153,.14), transparent 60%), radial-gradient(760px 500px at 6% 6%, rgba(251,191,36,.08), transparent 62%);}
    .shell{position:relative; z-index:1; max-width:1000px; margin:0 auto; padding:44px 22px 100px;}
    .serif{font-family:'Fraunces',serif;}
    .hero{display:flex; align-items:flex-start; gap:16px; margin-bottom:26px; flex-wrap:wrap;}
    .mark{width:50px; height:50px; border-radius:14px; background:linear-gradient(140deg,#34D399,#059669); color:#04150d; display:grid; place-items:center; font-weight:800; box-shadow:0 8px 30px -8px rgba(52,211,153,.5); flex:none;}
    .hero h1{margin:0; font-size:14px; font-weight:600; color:var(--ink-soft);}
    .hero .t{font-family:'Fraunces',serif; font-size:clamp(26px,4vw,38px); font-weight:600; margin:5px 0 8px; letter-spacing:-.015em;}
    .chips{display:flex; gap:8px; flex-wrap:wrap;}
    .chip{font-size:12px; font-weight:600; padding:5px 12px; border-radius:999px; background:var(--panel); border:1px solid var(--line); color:var(--ink-soft);}
    .chip.on{color:#34D399; border-color:rgba(52,211,153,.35); background:rgba(52,211,153,.08);}
    .spacer{flex:1;}
    .mbtn{background:var(--panel); border:1px solid var(--line); color:var(--ink-soft); border-radius:11px; padding:9px 14px; cursor:pointer; font:inherit; font-size:13px; font-weight:600;}
    .sec{margin-top:30px;} .lbl{font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:#34D399; margin-bottom:12px; display:flex; align-items:center; gap:9px;}
    .lbl::before{content:""; width:22px; height:2px; background:linear-gradient(90deg,#34D399,transparent);}
    .card{background:var(--panel); border:1px solid var(--line); border-radius:18px; padding:16px; backdrop-filter:blur(14px);}
    .muted{color:var(--ink-soft); font-size:14.5px;}
    /* notes area */
    .notes-empty{padding:34px 20px; text-align:center; color:var(--ink-faint); border:1px dashed var(--line); border-radius:14px; font-size:14px;}
    .note{border:1px solid var(--line); border-radius:16px; overflow:hidden; margin-bottom:16px; background:var(--panel);}
    .note-head{display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid var(--line); font-size:13px;}
    .note-head .type{font-family:'JetBrains Mono',monospace; color:#34D399; font-weight:600;}
    .note-head .cfg{font-family:'JetBrains Mono',monospace; color:var(--ink-faint); font-size:11.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;}
    .note-head .rm{margin-left:auto; background:none; border:1px solid var(--line); color:var(--ink-soft); border-radius:8px; padding:4px 10px; cursor:pointer; font:inherit; font-size:12px;}
    .note-head .rm:hover{color:#fb7185; border-color:rgba(251,113,133,.4);}
    .note-body{padding:12px;}
</style>

<div class="shell">
    <div class="hero">
        <div class="mark">R</div>
        <div style="min-width:0;">
            <h1>Interactive learning layer · React islands</h1>
            <div class="t serif">Widget Lab</div>
            <div class="chips">
                <span class="chip on">React 18 · Vite island</span>
                <span class="chip">widget registry + config</span>
                <span class="chip">notes round-trip</span>
            </div>
        </div>
        <div class="spacer"></div>
        <button class="mbtn" onclick="toggleMode()"><span id="mi">☀️</span> <span id="mt">Light</span></button>
    </div>

    <div class="sec">
        <div class="lbl">Mounted widget · one line of HTML</div>
        <p class="muted" style="margin-top:0;">This is a real React component mounted into a plain Blade page from
            <code style="font-family:'JetBrains Mono',monospace; color:#34D399;">&lt;div data-widget="photosynthesis_lake" data-config='{…}'&gt;</code>.
            Play with it, then hit <strong>“Save this diagram to my notes”</strong>.</p>
        <div class="card">
            <div data-widget="photosynthesis_lake" data-config='{"sun":80,"clarity":90,"co2":70,"temp":22}'></div>
        </div>
    </div>

    <div class="sec">
        <div class="lbl">Graph explorer · new archetype</div>
        <p class="muted" style="margin-top:0;">Drag the dot; switch value / gradient / area; click an answer to see what it describes on the graph.</p>
        <div class="card">
            <div data-widget="graph_explorer" data-config='{"title":"Speed–time graph","xLabel":"time","xUnit":"s","xMax":10,"yLabel":"speed","yUnit":"m/s","yMax":20,"curve":{"type":"piecewise","points":[[0,0],[4,12],[7,12],[10,0]]},"read":"area","options":[{"label":"average acceleration","correct":false,"enact":"gradient","note":"acceleration is the GRADIENT of the line, not the area."},{"label":"average speed","correct":false,"note":"average speed = area ÷ total time, not the area itself."},{"label":"total distance","correct":true,"enact":"area","note":"area under a speed–time graph = distance travelled."},{"label":"total time","correct":false,"note":"total time is a length on the x-axis, not a 2-D area."}]}'></div>
        </div>
    </div>

    <div class="sec">
        <div class="lbl">My notes · re-mounted live from stored config</div>
        <p class="muted" style="margin-top:0;">Each saved item stores only <code style="font-family:'JetBrains Mono',monospace; color:#34D399;">{ widget, config }</code> JSON
            (in localStorage for this demo). It is re-instantiated as a <em>live, interactive</em> widget — the diagram exactly as you left it, and still fully playable. Reload the page: your notes persist.</p>
        <div id="notesList"><div class="notes-empty" id="notesEmpty">No saved diagrams yet — adjust the widget above and click “Save this diagram to my notes”.</div></div>
    </div>
</div>

<script>
    /* ── host-app side of the notes contract ─────────────────────────────────
       Widgets emit a framework-agnostic `camb:add-to-note` event with {widget,config}.
       Here (the "app") we persist it and re-mount it live via window.CambWidgets. */
    const NOTES_KEY = 'camb_notes_demo';
    const load = () => { try { return JSON.parse(localStorage.getItem(NOTES_KEY) || '[]'); } catch { return []; } };
    const save = (n) => localStorage.setItem(NOTES_KEY, JSON.stringify(n));

    function whenReady(cb, tries = 0) {
        if (window.CambWidgets) return cb();
        if (tries > 60) return console.warn('CambWidgets never loaded');
        setTimeout(() => whenReady(cb, tries + 1), 50);
    }

    function renderNotes() {
        const notes = load(); const list = document.getElementById('notesList');
        list.innerHTML = '';
        if (!notes.length) { list.innerHTML = '<div class="notes-empty">No saved diagrams yet — adjust the widget above and click “Save this diagram to my notes”.</div>'; return; }
        notes.forEach((note, i) => {
            const card = document.createElement('div'); card.className = 'note';
            const cfg = JSON.stringify(note.config);
            card.innerHTML = `<div class="note-head"><span class="type">${note.widget}</span><span class="cfg">${cfg}</span><button class="rm" data-i="${i}">✕ remove</button></div><div class="note-body"></div>`;
            list.appendChild(card);
            window.CambWidgets.render(card.querySelector('.note-body'), note.widget, note.config);   // ← re-mount live
            card.querySelector('.rm').onclick = () => { const n = load(); n.splice(i, 1); save(n); renderNotes(); };
        });
    }

    document.addEventListener('camb:add-to-note', (e) => {
        const notes = load(); notes.push({ widget: e.detail.widget, config: e.detail.config, ts: Date.now() });
        save(notes); renderNotes();
    });

    whenReady(renderNotes);

    function toggleMode() {
        const h = document.documentElement, d = h.getAttribute('data-mode') === 'dark';
        h.setAttribute('data-mode', d ? 'light' : 'dark');
        document.getElementById('mi').textContent = d ? '🌙' : '☀️';
        document.getElementById('mt').textContent = d ? 'Dark' : 'Light';
    }
</script>
@endverbatim
</body>
</html>
