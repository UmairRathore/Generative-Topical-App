<!DOCTYPE html>
<html lang="en" data-mode="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>5090 · Q7 — Limiting Factors in Photosynthesis · V2 Showcase</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|fraunces:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />

    {{-- App design tokens: gives us --ok, --accent, --bad, --paper, --border, --soft-surface, --ok-soft, --sidebar …
         Included so this page participates in the app's theming system (?theme=NAME works). --}}
    @include('v2.partials.theme')

    <script>
        window.MathJax = { tex:{inlineMath:[['\\(','\\)']],displayMath:[['$$','$$']]}, svg:{fontCache:'global'}, startup:{typeset:false} };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>
</head>
<body>
@verbatim
<style>
    /* ══════════════════════════════════════════════════════════════════════
       TOKEN STRATEGY (audit)
       ─────────────────────────────────────────────────────────────────────
       1. BRAND tokens come from @include('v2.partials.theme'):
            --ok      → the app's "correct" green   (used for the answer, accent glow)
            --bad     → the app's error red         (used for wrong-answer / warnings)
            --accent  → the app's brand accent
          These follow the active app palette + ?theme= switching.
       2. FRAME tokens (surfaces / text / lines) are defined here and are
          mode-aware via [data-mode], because the app theme leaves --text and
          --bg UNDEFINED in light mode (only dark mode sets them) — so relying
          on them directly would break light mode. We namespace ours to avoid
          clashing and give every one an explicit fallback.
       3. SCIENCE-SEMANTIC colors (--sun, --leaf, --water, --mud, --o2) are
          FIXED constants, intentionally NOT theme-driven: they encode physical
          meaning (sunlight is gold, chlorophyll is green) that must stay
          constant regardless of brand palette. NB the app's legacy --gold-500
          token is actually mapped to the *accent role* (blue in the default
          'instructure' theme), so it must NOT be used for "gold".
       ════════════════════════════════════════════════════════════════════ */
    :root{
        --bg:#06110C; --bg-2:#08160F; --bg-3:#0B1E15;
        --panel:rgba(255,255,255,.035); --panel-2:rgba(255,255,255,.055);
        --line:rgba(160,200,175,.14);
        --ink:#EAF6EE; --ink-soft:#A6C4B3; --ink-faint:#68877A;
        /* brand accent, pulled from app --ok (green) with a vivid emerald fallback */
        --acc:var(--ok, #34D399); --acc-glow:#34D399; --acc-deep:#059669;
        --good:var(--ok, #22C55E); --warn:var(--bad, #FB7185);
        /* science-semantic (fixed) */
        --sun:#FBBF24; --sun-deep:#D97706; --leaf:#34D399; --water:#3EA7E0; --mud:#8A7355; --o2:#7FE3FF; --slate:#7C93A8;
        --shadow:0 24px 60px -20px rgba(0,0,0,.65); --radius:18px;
    }
    html[data-mode="light"]{
        --bg:#EDF4EF; --bg-2:#E4EFE8; --bg-3:#FFFFFF;
        --panel:rgba(255,255,255,.78); --panel-2:#FFFFFF;
        --line:rgba(15,60,35,.12);
        --ink:#0B2318; --ink-soft:#3C5A49; --ink-faint:#6E8877;
        --acc-deep:#047857;
        --shadow:0 24px 60px -24px rgba(20,60,40,.24);
    }
    *{box-sizing:border-box;} html,body{margin:0;padding:0;}
    body{font-family:'Inter',system-ui,-apple-system,sans-serif; background:var(--bg); color:var(--ink); line-height:1.6; -webkit-font-smoothing:antialiased; overflow-x:hidden; transition:background .5s ease,color .5s ease;}
    body::before{content:""; position:fixed; inset:0; z-index:0; pointer-events:none;
        background:
            radial-gradient(900px 520px at 76% -8%, rgba(52,211,153,.15), transparent 60%),
            radial-gradient(760px 500px at 6% 6%, rgba(251,191,36,.10), transparent 62%),
            radial-gradient(1100px 700px at 50% 118%, rgba(62,167,224,.08), transparent 60%);}
    html[data-mode="light"] body::before{opacity:.5;}
    .serif{font-family:'Fraunces',Georgia,serif;} .mono{font-family:'JetBrains Mono',ui-monospace,monospace;font-feature-settings:"tnum";}
    .shell{position:relative; z-index:1; max-width:1120px; margin:0 auto; padding:0 22px 96px;}

    .hero{position:relative; padding:40px 0 26px;} .hero-row{display:flex; align-items:flex-start; gap:18px; flex-wrap:wrap;}
    .mark{width:52px; height:52px; border-radius:15px; flex:none; background:linear-gradient(140deg,var(--acc-glow),var(--acc-deep)); display:grid; place-items:center; color:#04150d; font-weight:800; font-size:15px; box-shadow:0 0 0 1px rgba(52,211,153,.25),0 8px 40px -8px rgba(52,211,153,.45); letter-spacing:-.02em;}
    .hero h1{margin:2px 0 0; font-size:15px; font-weight:600; letter-spacing:.02em; color:var(--ink-soft);}
    .hero .title{font-family:'Fraunces',serif; font-size:clamp(28px,4vw,42px); font-weight:600; letter-spacing:-.015em; line-height:1.08; margin:6px 0 12px; color:var(--ink);}
    .chips{display:flex; gap:8px; flex-wrap:wrap;}
    .chip{display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px; font-size:12px; font-weight:600; background:var(--panel); border:1px solid var(--line); color:var(--ink-soft);}
    .chip.accent{color:var(--acc); border-color:rgba(52,211,153,.35); background:rgba(52,211,153,.08);}
    .hero .spacer{flex:1;}
    .modebtn{background:var(--panel); border:1px solid var(--line); color:var(--ink-soft); border-radius:11px; padding:9px 14px; cursor:pointer; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:8px; transition:.2s;}
    .modebtn:hover{color:var(--ink); background:var(--panel-2);}

    .tabwrap{position:sticky; top:12px; z-index:30; margin:8px 0 30px;}
    .tabs{position:relative; display:flex; gap:2px; padding:6px; border-radius:15px; background:var(--panel); border:1px solid var(--line); backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px); overflow-x:auto; scrollbar-width:none; box-shadow:var(--shadow);}
    .tabs::-webkit-scrollbar{display:none;}
    .tab{position:relative; z-index:2; border:0; background:transparent; color:var(--ink-soft); font:inherit; font-size:13.5px; font-weight:600; padding:10px 16px; border-radius:10px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:8px; transition:color .25s;}
    .tab:hover{color:var(--ink);} .tab.active{color:var(--ink);}
    .tab .ic{font-size:15px; opacity:.9;}
    .tab-ind{position:absolute; z-index:1; top:6px; height:calc(100% - 12px); border-radius:10px; background:linear-gradient(180deg,var(--panel-2),var(--panel)); border:1px solid var(--line); box-shadow:0 4px 16px -6px rgba(0,0,0,.4); transition:transform .38s cubic-bezier(.34,1.3,.4,1), width .38s cubic-bezier(.34,1.3,.4,1);}
    html[data-mode="dark"] .tab.active{text-shadow:0 0 20px rgba(52,211,153,.35);}

    .panel{display:none; animation:rise .5s cubic-bezier(.2,.7,.2,1);} .panel.show{display:block;}
    @keyframes rise{from{opacity:0; transform:translateY(14px);} to{opacity:1; transform:none;}}

    .card{position:relative; background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); padding:26px 28px; margin-bottom:18px; backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); box-shadow:var(--shadow);}
    .card.flush{padding:0; overflow:hidden;}
    .eyebrow{font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:var(--acc); margin-bottom:12px; display:flex; align-items:center; gap:9px;}
    .eyebrow::before{content:""; width:22px; height:2px; border-radius:2px; background:linear-gradient(90deg,var(--acc),transparent);}
    .card h2{font-family:'Fraunces',serif; font-size:27px; font-weight:600; letter-spacing:-.01em; margin:0 0 6px; color:var(--ink);}
    .lead{font-size:16.5px; color:var(--ink); line-height:1.7;}
    .lead strong{color:var(--ink); font-weight:700; background:linear-gradient(transparent 62%, rgba(52,211,153,.20) 62%);}
    .muted{color:var(--ink-soft);}
    .scene-note{margin-top:20px; display:flex; gap:14px; align-items:center; padding:16px 18px; border-radius:14px; border:1px solid var(--line); background:linear-gradient(100deg, rgba(62,167,224,.10), var(--panel));}
    .scene-note .ic{font-size:26px;}

    .opts{display:grid; gap:11px; margin-top:6px;}
    .opt{display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:16px; padding:15px 18px; border:1px solid var(--line); border-radius:13px; background:var(--panel);}
    .opt .k{width:34px; height:34px; border-radius:9px; display:grid; place-items:center; font-weight:700; font-size:15px; border:1px solid var(--line); color:var(--ink-soft); background:var(--bg-3);}
    .opt .txt{font-size:15.5px; color:var(--ink);}
    .opt .why{font-size:12.5px; color:var(--ink-faint); text-align:right; max-width:200px;}
    .opt.correct{border-color:rgba(34,197,94,.5); background:linear-gradient(100deg,rgba(34,197,94,.12),var(--panel));}
    .opt.correct .k{background:var(--good); color:#03130a; border-color:transparent; box-shadow:0 0 22px -4px rgba(34,197,94,.6);}
    .opt.correct .why{color:var(--good); font-weight:600;}

    .steps{margin-top:8px;}
    .step{position:relative; display:grid; grid-template-columns:auto 1fr; gap:18px; padding:0 0 26px 0;}
    .step:not(:last-child)::after{content:""; position:absolute; left:17px; top:40px; bottom:6px; width:2px; background:linear-gradient(var(--line),transparent);}
    .step .n{width:36px; height:36px; border-radius:11px; background:var(--bg-3); border:1px solid var(--line); display:grid; place-items:center; font-weight:700; font-family:'JetBrains Mono',monospace; color:var(--acc); z-index:1;}
    .step h4{margin:6px 0 8px; font-size:16px; font-weight:700; color:var(--ink);}
    .finalcard{display:flex; align-items:center; gap:20px; margin-top:6px; padding:22px 26px; border-radius:15px; border:1px solid rgba(34,197,94,.4); background:linear-gradient(110deg,rgba(34,197,94,.14),var(--panel));}
    .finalcard .badge{font-family:'Fraunces',serif; font-size:44px; font-weight:600; color:var(--good); width:64px; height:64px; border-radius:16px; display:grid; place-items:center; background:rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.4); flex:none; box-shadow:0 0 30px -6px rgba(34,197,94,.5);}

    /* ── interactive ── */
    .sim-head{padding:26px 28px 0;}
    .sim-grid{display:grid; grid-template-columns:1.55fr 1fr; gap:0; align-items:stretch;}
    @media (max-width:900px){ .sim-grid{grid-template-columns:1fr;} }
    .stage-col{padding:18px 18px 22px 28px; min-width:0;}
    @media (max-width:900px){ .stage-col{padding:18px;} }
    .stage{position:relative; width:100%; aspect-ratio:16/11; border-radius:16px; overflow:hidden; border:1px solid var(--line); box-shadow:inset 0 1px 0 rgba(255,255,255,.05), var(--shadow); background:#04120b;}
    .stage canvas{display:block; width:100%; height:100%;}
    .stage-badge{position:absolute; top:14px; left:14px; z-index:5; font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--acc); background:rgba(6,16,12,.5); border:1px solid rgba(52,211,153,.3); padding:5px 11px; border-radius:999px; backdrop-filter:blur(8px);}
    @media (max-width:560px){ .stage-badge{font-size:9.5px; padding:4px 8px;} }
    html[data-mode="light"] .stage-badge{background:rgba(255,255,255,.7);}

    .ctrl-col{padding:18px 28px 22px 18px; display:flex; flex-direction:column; gap:14px; border-left:1px solid var(--line);}
    @media (max-width:900px){ .ctrl-col{border-left:0; border-top:1px solid var(--line); padding:18px;} }
    .stat{background:var(--bg-3); border:1px solid var(--line); border-radius:13px; padding:14px 16px;}
    html[data-mode="light"] .stat{background:#fff;}
    .stat .lab{font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:var(--ink-faint); margin-bottom:6px;}
    .stat .num{font-family:'JetBrains Mono',monospace; font-size:28px; font-weight:600; color:var(--acc); line-height:1;}
    .stat .num small{font-size:14px; color:var(--ink-faint);}
    .stat .lim{margin-top:8px; font-size:12.5px; color:var(--ink-soft);}
    .stat .lim b{color:var(--sun); font-family:'JetBrains Mono',monospace;}
    .graphbox{background:var(--bg-3); border:1px solid var(--line); border-radius:13px; padding:8px;}
    html[data-mode="light"] .graphbox{background:#fff;}
    .graphbox canvas{display:block; width:100%; height:150px;}

    .slider-row label{display:flex; justify-content:space-between; align-items:baseline; font-size:12.5px; font-weight:600; color:var(--ink-soft); margin-bottom:8px;}
    .slider-row label b{font-family:'JetBrains Mono',monospace; color:var(--c,var(--acc)); font-size:14px;}
    input[type=range].sld{-webkit-appearance:none; appearance:none; width:100%; height:6px; border-radius:6px; background:linear-gradient(90deg,var(--c,var(--acc)) 0%,var(--c,var(--acc)) var(--fill,0%), var(--bg-3) var(--fill,0%)); outline:none; cursor:pointer;}
    input[type=range].sld::-webkit-slider-thumb{-webkit-appearance:none; width:20px; height:20px; border-radius:50%; background:#fff; border:3px solid var(--c,var(--acc)); box-shadow:0 2px 10px rgba(0,0,0,.4); cursor:grab;}
    input[type=range].sld::-moz-range-thumb{width:18px; height:18px; border-radius:50%; background:#fff; border:3px solid var(--c,var(--acc)); cursor:grab;}
    .snaps{display:flex; gap:8px;}
    .snap{flex:1; border:1px solid var(--line); background:var(--bg-3); color:var(--ink-soft); border-radius:10px; padding:10px 6px; font:inherit; font-size:12.5px; font-weight:600; cursor:pointer; transition:.2s;}
    html[data-mode="light"] .snap{background:#fff;}
    .snap:hover{color:var(--acc); border-color:rgba(52,211,153,.45);}
    .snap.mud{color:var(--mud); border-color:rgba(138,115,85,.5);} .snap.mud:hover{background:rgba(138,115,85,.12);}
    .flag{padding:12px 15px; border-radius:12px; font-size:13px; font-weight:600; border:1px solid; line-height:1.45;}
    .flag.ok{background:rgba(34,197,94,.1); color:var(--good); border-color:rgba(34,197,94,.4);}
    .flag.warn{background:rgba(62,167,224,.12); color:var(--water); border-color:rgba(62,167,224,.4);}
    .flag.neutral{background:var(--bg-3); color:var(--ink-soft); border-color:var(--line);}

    .grid2{display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:15px;}
    .flip{perspective:1600px; height:180px; cursor:pointer;}
    .flip-in{position:relative; width:100%; height:100%; transition:transform .7s cubic-bezier(.2,.8,.2,1); transform-style:preserve-3d;}
    .flip.on .flip-in{transform:rotateY(180deg);}
    .face{position:absolute; inset:0; backface-visibility:hidden; border-radius:15px; padding:22px; display:flex; flex-direction:column; justify-content:center; border:1px solid var(--line);}
    .face.front{background:var(--panel);}
    .face.back{background:linear-gradient(140deg,var(--acc-deep),#07231a); color:#eafff3; transform:rotateY(180deg); border-color:transparent;}
    .face .q{font-size:16px; font-weight:600; color:var(--ink);} .face .a{font-size:14.5px; line-height:1.55;}
    .face .hint{position:absolute; bottom:13px; right:16px; font-size:10px; letter-spacing:.1em; text-transform:uppercase; opacity:.5;}
    .mem{border:1px solid var(--line); border-left:3px solid var(--acc); border-radius:13px; padding:17px 19px; background:var(--panel);}
    .mem.blue{border-left-color:var(--water);} .mem.gold{border-left-color:var(--sun);}
    .mem .k{font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-faint); margin-bottom:7px;}
    .mem .t{font-size:15px; font-weight:700; color:var(--ink); margin-bottom:5px;}
    .mem .d{font-size:13.5px; color:var(--ink-soft);}
    .mem .f{font-family:'JetBrains Mono',monospace; font-size:12.5px; background:var(--bg-3); border-radius:7px; padding:7px 11px; margin-top:9px; color:var(--acc); display:inline-block;}
    html[data-mode="light"] .mem .f{background:#eef6f1;}

    .mermaid-box{background:var(--bg-3); border:1px solid var(--line); border-radius:13px; padding:20px; overflow-x:auto; text-align:center;}
    html[data-mode="light"] .mermaid-box{background:#fff;}
    .json-box{background:#04100a; color:#cbe8d8; border-radius:13px; padding:22px; overflow-x:auto; font-family:'JetBrains Mono',monospace; font-size:12.5px; line-height:1.75; border:1px solid var(--line);}
    .json-box .jk{color:#6ee7b7;} .json-box .js{color:#bbf7d0;} .json-box .jn{color:#fcd34d;} .json-box .jb{color:#fda4af;}
    .copy{float:right; background:var(--bg-3); border:1px solid var(--line); color:var(--ink-soft); border-radius:9px; padding:6px 13px; font:inherit; font-size:12px; font-weight:600; cursor:pointer;}
    .copy:hover{color:var(--acc); border-color:rgba(52,211,153,.4);}
</style>

<div class="shell">
    <header class="hero">
        <div class="hero-row">
            <div class="mark">5090</div>
            <div style="min-width:0;">
                <h1>Cambridge O Level Biology · Paper 1 (Multiple Choice)</h1>
                <div class="title serif">Limiting Factors in Photosynthesis</div>
                <div class="chips">
                    <span class="chip accent">◆ Q7 · answer C</span>
                    <span class="chip">2022 Oct/Nov · variant 11</span>
                    <span class="chip">Plant nutrition</span>
                    <span class="chip">Light as a limiting factor</span>
                </div>
            </div>
            <div class="spacer"></div>
            <button class="modebtn" onclick="toggleMode()"><span id="modeIco">☀️</span><span id="modeTxt">Light</span></button>
        </div>
    </header>

    <div class="tabwrap">
        <nav class="tabs" id="tabs">
            <span class="tab-ind" id="tabInd"></span>
            <button class="tab active" data-tab="question"><span class="ic">◈</span> Question</button>
            <button class="tab" data-tab="interactive"><span class="ic">☀</span> Lake Simulator</button>
            <button class="tab" data-tab="solution"><span class="ic">∑</span> Solution</button>
            <button class="tab" data-tab="flashcards"><span class="ic">◑</span> Flashcards</button>
            <button class="tab" data-tab="memcards"><span class="ic">▣</span> Memcards</button>
            <button class="tab" data-tab="mermaid"><span class="ic">⌥</span> Flow</button>
            <button class="tab" data-tab="json"><span class="ic">{ }</span> JSON</button>
        </nav>
    </div>

    <!-- QUESTION -->
    <section class="panel show" id="p-question">
        <div class="card">
            <div class="eyebrow">The problem</div>
            <p class="lead">A small mountain lake has aquatic plants growing under water on the lake bed. Shortly after heavy rainfall, the <strong>mud on the lake bed becomes stirred up</strong> and the <strong>water level rises</strong>. Why does this cause the <strong>rate of photosynthesis</strong> of these plants to fall?</p>
            <div class="scene-note">
                <span class="ic">🏔️</span>
                <span class="muted">Two things change together — the water gets <strong style="color:var(--mud)">murkier</strong> (suspended mud) and <strong style="color:var(--water)">deeper</strong>. Both do the same thing to the light before it reaches the plants on the bed.</span>
            </div>
        </div>
        <div class="card">
            <div class="eyebrow">Choose one</div>
            <div class="opts" id="optList"></div>
        </div>
    </section>

    <!-- INTERACTIVE -->
    <section class="panel" id="p-interactive">
        <div class="card flush">
            <div class="sim-head">
                <div class="eyebrow">Interactive · the muddy lake</div>
                <h2 class="serif">Rate-of-photosynthesis lake</h2>
                <p class="muted" style="margin:2px 0 0; font-size:14.5px;">Sunlight filters down through the water to the pondweed on the bed, which bubbles oxygen faster when photosynthesis is faster. Cloud the water with mud and watch the light — and the bubbling — collapse. The graph shows exactly why: below saturation, <em>light</em> is the limiting factor.</p>
            </div>
            <div class="sim-grid">
                <div class="stage-col">
                    <div class="stage" id="stage">
                        <span class="stage-badge">Live · rate ∝ limiting factor</span>
                        <canvas id="lakeCanvas"></canvas>
                    </div>
                </div>
                <div class="ctrl-col">
                    <div class="stat">
                        <div class="lab">Relative rate of photosynthesis</div>
                        <div class="num" id="rRate">100<small>%</small></div>
                        <div class="lim">Limiting factor right now: <b id="rLim">carbon dioxide</b></div>
                    </div>
                    <div class="graphbox"><canvas id="graphCanvas"></canvas></div>
                    <div class="slider-row" style="--c:var(--sun);"><label>Surface sunlight <b id="vSun">80%</b></label><input type="range" class="sld" id="sSun" min="0" max="100" step="1" value="80" style="--c:var(--sun);--fill:80%;"></div>
                    <div class="slider-row" style="--c:var(--mud);"><label>Water clarity <b id="vClar">clear</b></label><input type="range" class="sld" id="sClar" min="5" max="100" step="1" value="90" style="--c:var(--mud);--fill:90%;"></div>
                    <div class="slider-row" style="--c:var(--acc);"><label>CO₂ available <b id="vCo2">70%</b></label><input type="range" class="sld" id="sCo2" min="0" max="100" step="1" value="70" style="--c:var(--acc);--fill:70%;"></div>
                    <div class="slider-row" style="--c:var(--warn);"><label>Water temperature <b id="vTemp">22°C</b></label><input type="range" class="sld" id="sTemp" min="2" max="45" step="1" value="22" style="--c:var(--warn);--fill:47%;"></div>
                    <div class="snaps">
                        <button class="snap" onclick="setClear()">☀ Clear day</button>
                        <button class="snap mud" onclick="stirMud()">🌧 Stir the mud</button>
                    </div>
                    <div class="flag ok" id="flag"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- SOLUTION -->
    <section class="panel" id="p-solution">
        <div class="card">
            <div class="eyebrow">Worked solution · limiting factors</div>
            <h2 class="serif" style="margin-bottom:18px;">Trace the light, not the gases</h2>
            <div class="steps">
                <div class="step"><div class="n">1</div><div><h4>What actually changed?</h4><p class="muted">Rain stirs up mud (the water turns <strong>turbid</strong>) and raises the level (the plants are now <strong>deeper</strong>). Neither adds or removes carbon dioxide, nitrates or oxygen in a way that matters here.</p></div></div>
                <div class="step"><div class="n">2</div><div><h4>How each change affects light</h4><p class="muted">Suspended mud <strong>scatters and absorbs</strong> light; deeper water means light travels <strong>further</strong> before reaching the bed. Both reduce the <strong>light intensity</strong> that arrives at the plants.</p></div></div>
                <div class="step"><div class="n">3</div><div><h4>Light is a limiting factor</h4><p class="muted">On a bright day the plants are usually limited by CO₂ or temperature — light is plentiful. Cut the light enough and it becomes the <strong>limiting factor</strong>: the rate of photosynthesis now rises and falls with light, so less light → slower photosynthesis.</p></div></div>
                <div class="step"><div class="n">!</div><div><h4>Why the distractors fail</h4><p class="muted"><strong>A</strong> extra CO₂ would <em>raise</em> the rate, not lower it. <strong>B</strong> nitrates affect protein/growth, not the immediate rate of photosynthesis. <strong>D</strong> oxygen is a <em>product</em> — its concentration doesn't limit the reaction here.</p></div></div>
            </div>
            <div class="finalcard">
                <div class="badge serif">C</div>
                <div>
                    <div style="font-weight:700; font-size:17px; color:var(--ink);">Lower light intensity</div>
                    <div class="muted" style="font-size:13.5px; margin-top:2px;">Murkier + deeper water both cut the light reaching the plants, and light is the limiting factor here. ✓</div>
                </div>
            </div>
        </div>
    </section>

    <!-- FLASHCARDS -->
    <section class="panel" id="p-flashcards"><div class="card"><div class="eyebrow">Flashcards · tap to flip</div><h2 class="serif" style="margin-bottom:16px;">Self-test the core ideas</h2><div class="grid2" id="flashGrid"></div></div></section>
    <!-- MEMCARDS -->
    <section class="panel" id="p-memcards"><div class="card"><div class="eyebrow">Memcards · commit to memory</div><h2 class="serif" style="margin-bottom:16px;">The facts behind it</h2><div class="grid2" id="memGrid"></div></div></section>

    <!-- MERMAID -->
    <section class="panel" id="p-mermaid">
        <div class="card"><div class="eyebrow">Flow · the reasoning</div><h2 class="serif" style="margin-bottom:16px;">From rainfall to slower photosynthesis</h2><div class="mermaid-box"><pre class="mermaid" id="mm1">
flowchart TD
    A[Heavy rainfall] --> B[Mud stirred up = turbid water]
    A --> C[Water level rises = plants deeper]
    B --> D[Less light reaches the lake bed]
    C --> D
    D --> E{Is light now the limiting factor?}
    E -->|Yes, below saturation| F[Rate of photosynthesis falls]
    F --> G([Answer C: lower light intensity])
        </pre></div></div>
        <div class="card"><div class="eyebrow">Flow · the concept</div><h2 class="serif" style="margin-bottom:16px;">The three limiting factors</h2><div class="mermaid-box"><pre class="mermaid" id="mm2">
graph LR
    R([Rate of photosynthesis]) --> L[Light intensity]
    R --> C[Carbon dioxide]
    R --> T[Temperature]
    L --> LAW{{Rate set by the factor in shortest supply}}
    C --> LAW
    T --> LAW
    LAW --> P["Raise the limiting one and the rate rises, until another runs short"]
        </pre></div></div>
    </section>

    <!-- JSON -->
    <section class="panel" id="p-json"><div class="card"><div class="eyebrow">JSON · structured model</div><button class="copy" onclick="copyJson()">⧉ Copy</button><h2 class="serif" style="margin-bottom:16px;">Machine-readable question object</h2><div class="json-box" id="jsonOut"></div></div></section>
</div>

<script>
/* ═══════════ DATA ═══════════ */
const DATA={
    id:"5090_w22_qp_11_q7",
    paper:{code:"5090",level:"O Level",subject:"Biology",session:"2022 Oct/Nov",variant:"qp_11",question:7,type:"mcq"},
    topic:{unit:"Plant nutrition",subtopic:"Photosynthesis — limiting factors",skills:["light as a limiting factor","effect of turbidity & depth on light","interpreting an ecological scenario"]},
    stem:"A mountain lake has aquatic plants on the bed. After heavy rainfall the mud is stirred up and the water rises. Why does this cause the rate of photosynthesis to fall?",
    options:[
        {label:"A",text:"extra carbon dioxide",error:"extra CO₂ would raise the rate, not lower it"},
        {label:"B",text:"extra dissolved nitrates",error:"nitrates affect protein/growth, not the immediate rate"},
        {label:"C",text:"lower light intensity",error:null},
        {label:"D",text:"lower oxygen concentration",error:"oxygen is a product; it doesn't limit the reaction"}
    ],
    answer:"C",
    model:{key_idea:"turbid + deeper water lowers the light intensity reaching the plants",limiting_factors:["light intensity","carbon dioxide","temperature"],rule:"rate is set by the factor in shortest supply (Blackman)"},
    solution:{cause:"suspended mud scatters/absorbs light; greater depth attenuates it further",effect:"light becomes the limiting factor, so rate falls",answer:"C"}
};
const FLASH=[
    {q:"Name the three main limiting factors of photosynthesis.",a:"Light intensity, carbon dioxide concentration, and temperature."},
    {q:"What is a \"limiting factor\"?",a:"The factor in shortest supply — it caps the rate. Increasing it raises the rate until another factor runs short."},
    {q:"Why does stirred-up mud lower photosynthesis?",a:"Suspended particles scatter and absorb light, so less light reaches the plants — light becomes limiting."},
    {q:"Why does deeper water lower it too?",a:"Light is absorbed as it passes through water, so at greater depth less light intensity arrives at the lake bed."},
    {q:"Why isn't the answer 'extra CO₂' (A)?",a:"More CO₂ would speed photosynthesis up, not slow it down — and turbidity doesn't add CO₂."},
    {q:"Why isn't it 'lower oxygen' (D)?",a:"Oxygen is a product of photosynthesis, not a reactant, so its level doesn't limit the rate here."}
];
const MEM=[
    {tone:"",k:"Rule",t:"Law of limiting factors",d:"The rate of a process is limited by the factor in shortest supply.",f:"rate ← slowest factor"},
    {tone:"gold",k:"Factor",t:"Light intensity",d:"Provides energy for the light-dependent stage. Below saturation, rate rises with light.",f:"↑ light → ↑ rate (to a plateau)"},
    {tone:"green",k:"Factor",t:"Carbon dioxide",d:"A raw material fixed in photosynthesis; often limiting on a bright day.",f:"6CO₂ + 6H₂O → C₆H₁₂O₆ + 6O₂"},
    {tone:"blue",k:"Factor",t:"Temperature",d:"Enzyme-controlled: rises to an optimum (~25–35 °C) then falls as enzymes denature.",f:"optimum, then denature"},
    {tone:"blue",k:"Water optics",t:"Turbidity & depth cut light",d:"Suspended solids scatter/absorb light; water absorbs it with depth — both lower intensity at the bed.",f:"murkier / deeper → less light"},
    {tone:"gold",k:"Test",t:"Bubbles measure rate",d:"Oxygen bubbles from pondweed are a classic measure of photosynthetic rate.",f:"more bubbles = faster rate"}
];

const $=id=>document.getElementById(id);
$('optList').innerHTML=DATA.options.map(o=>`<div class="opt ${o.label===DATA.answer?'correct':''}"><div class="k">${o.label}</div><div class="txt">${o.text}</div><div class="why">${o.label===DATA.answer?'✓ correct':o.error}</div></div>`).join('');
$('flashGrid').innerHTML=FLASH.map(c=>`<div class="flip"><div class="flip-in"><div class="face front"><div class="q">${c.q}</div><div class="hint">tap to reveal</div></div><div class="face back"><div class="a">${c.a}</div><div class="hint">tap to flip back</div></div></div></div>`).join('');
document.querySelectorAll('.flip').forEach(f=>f.addEventListener('click',()=>f.classList.toggle('on')));
$('memGrid').innerHTML=MEM.map(m=>`<div class="mem ${m.tone}"><div class="k">${m.k}</div><div class="t">${m.t}</div><div class="d">${m.d}</div><div class="f">${m.f}</div></div>`).join('');
function syntaxJson(o){let s=JSON.stringify(o,null,2).replace(/&/g,'&amp;').replace(/</g,'&lt;');return s.replace(/("(\\u[a-fA-F0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,m=>{let c='jn';if(/^"/.test(m))c=/:$/.test(m)?'jk':'js';else if(/true|false|null/.test(m))c='jb';return `<span class="${c}">${m}</span>`;});}
$('jsonOut').innerHTML=syntaxJson(DATA);
function copyJson(){navigator.clipboard.writeText(JSON.stringify(DATA,null,2));}

/* ═══════════ TABS ═══════════ */
const ind=$('tabInd');
function moveInd(b){ ind.style.width=b.offsetWidth+'px'; ind.style.transform=`translateX(${b.offsetLeft-6}px)`; }
function activateTab(b){
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(x=>x.classList.remove('show'));
    b.classList.add('active'); $('p-'+b.dataset.tab).classList.add('show'); moveInd(b);
    if(b.dataset.tab==='mermaid') renderMermaid();
    if(b.dataset.tab==='interactive'){ LAKE.start(); requestAnimationFrame(()=>LAKE.resize()); } else { LAKE.stop(); }
    if(window.MathJax&&window.MathJax.typesetPromise) window.MathJax.typesetPromise();
}
document.querySelectorAll('.tab').forEach(t=>t.addEventListener('click',()=>activateTab(t)));
window.addEventListener('load',()=>moveInd(document.querySelector('.tab.active')));
window.addEventListener('resize',()=>{const a=document.querySelector('.tab.active'); if(a) moveInd(a); LAKE.resize();});

/* ═══════════ MERMAID ═══════════ */
let mmReady=false,mmDone=false,MM=null;
import('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs').then(m=>{MM=m.default; initMermaid(); mmReady=true; if($('p-mermaid').classList.contains('show')) renderMermaid();});
function themeVars(){const s=getComputedStyle(document.documentElement),g=(n,f)=>(s.getPropertyValue(n).trim()||f);return{primaryColor:g('--bg-3','#0B1E15'),primaryBorderColor:g('--acc','#34D399'),primaryTextColor:g('--ink','#EAF6EE'),lineColor:g('--ink-soft','#A6C4B3'),secondaryColor:g('--bg-2','#08160F'),tertiaryColor:g('--panel','#111')};}
function initMermaid(){ if(!MM)return; MM.initialize({startOnLoad:false,theme:'base',themeVariables:{fontFamily:'Inter, sans-serif',...themeVars()}}); }
function renderMermaid(){ if(!mmReady||mmDone) return; mmDone=true; MM.run({nodes:document.querySelectorAll('.mermaid')}); }
document.querySelectorAll('.mermaid').forEach(n=>n.dataset.orig=n.textContent);

/* ═══════════ PHYSIOLOGY MODEL (limiting factors) ═══════════ */
function tempFactor(T){ const f=Math.exp(-Math.pow((T-28)/11,2)); return T>28? f*Math.exp(-Math.max(0,(T-32))/6) : f; }  // optimum ~28°C, denature above
const LIGHT_SAT=0.55;
function model(st){
    const effLight=(st.sun/100)*(st.clarity/100);         // clarity (turbidity+depth proxy) cuts surface light
    const co2=st.co2/100, tf=tempFactor(st.temp);
    const plateau=Math.min(co2,tf);                       // non-light ceiling
    const lightFrac=Math.min(1,effLight/LIGHT_SAT);
    const rate=plateau*lightFrac;
    let lim = (lightFrac<0.995)?'light':(co2<=tf?'carbon dioxide':'temperature');
    return {effLight,co2,tf,plateau,lightFrac,rate,lim};
}

/* ═══════════ CANVAS · LAKE SCENE ═══════════ */
const LAKE=(()=>{
    const cv=$('lakeCanvas'), ctx=cv.getContext('2d'), host=$('stage');
    const W=1000,H=688; let scale=1,ox=0,oy=0,dpr=1,raf=null,last=0;
    let st={sun:80,clarity:90,co2:70,temp:22}, M=model(st);
    const css=(n,f)=>{const v=getComputedStyle(document.documentElement).getPropertyValue(n).trim();return v||f;};
    const bubbles=[], mud=[];
    const WEED=[{x:300,sway:0},{x:430,sway:1.1},{x:540,sway:2.2},{x:670,sway:.6}];
    for(let i=0;i<70;i++) mud.push({x:Math.random()*W,y:150+Math.random()*(H-190),vx:(Math.random()-.5)*.2,vy:(Math.random()-.4)*.3,r:1+Math.random()*2.4,ph:Math.random()*6.28});

    function spawnBubble(){ const w=WEED[(Math.random()*WEED.length)|0]; bubbles.push({x:w.x+(Math.random()-.5)*26, y:H-70, r:2+Math.random()*4, v:.6+Math.random()*.8, wob:Math.random()*6.28}); }

    function resize(){ const w=host.clientWidth,h=host.clientHeight; if(!w||!h)return; dpr=Math.min(window.devicePixelRatio||1,2); cv.width=w*dpr; cv.height=h*dpr; cv.style.width=w+'px'; cv.style.height=h+'px'; scale=Math.min(w/W,h/H); ox=(w-W*scale)/2; oy=(h-H*scale)/2; }

    function frame(time){
        if(!last)last=time; const dt=Math.min(0.05,(time-last)/1000); last=time;
        ctx.setTransform(dpr,0,0,dpr,0,0); ctx.clearRect(0,0,cv.width,cv.height);
        ctx.setTransform(dpr*scale,0,0,dpr*scale,ox*dpr,oy*dpr);

        const light=css('--sun','#FBBF24'), leaf=css('--leaf','#34D399'), o2=css('--o2','#7FE3FF'), mudc=css('--mud','#8A7355'), water=css('--water','#3EA7E0');
        const clar=st.clarity/100, surf=140;

        // ── sky ──
        const sky=ctx.createLinearGradient(0,0,0,surf); sky.addColorStop(0,'#0a2036'); sky.addColorStop(1,'#0f3a55');
        html_light() && (sky.addColorStop(0,'#bfe0f2'), sky.addColorStop(1,'#8fc6e6'));
        ctx.fillStyle=sky; ctx.fillRect(0,0,W,surf);
        // sun
        const sx=150, sy=64, sunB=(st.sun/100);
        ctx.save(); ctx.globalAlpha=.35+sunB*.5; ctx.strokeStyle=light; ctx.lineCap='round';
        for(let i=0;i<10;i++){ const a=i/10*6.283+time*0.0003; ctx.lineWidth=3; ctx.beginPath(); ctx.moveTo(sx+Math.cos(a)*38,sy+Math.sin(a)*38); ctx.lineTo(sx+Math.cos(a)*(52+Math.sin(time*0.003+i)*6),sy+Math.sin(a)*(52+Math.sin(time*0.003+i)*6)); ctx.stroke(); }
        ctx.restore();
        const sg=ctx.createRadialGradient(sx,sy,4,sx,sy,34); sg.addColorStop(0,'#fff7dc'); sg.addColorStop(.55,light); sg.addColorStop(1,css('--sun-deep','#D97706'));
        ctx.fillStyle=sg; ctx.beginPath(); ctx.arc(sx,sy,34,0,6.283); ctx.fill();

        // ── water body ──
        const wg=ctx.createLinearGradient(0,surf,0,H);
        const murk=1-clar;
        wg.addColorStop(0, mixhex('#12496b', mudc, murk*0.6)); wg.addColorStop(1, mixhex('#06202f', mudc, murk*0.75));
        ctx.fillStyle=wg; ctx.fillRect(0,surf,W,H-surf);
        // surface shimmer
        ctx.strokeStyle='rgba(255,255,255,.18)'; ctx.lineWidth=2; ctx.beginPath();
        for(let x=0;x<=W;x+=12){ const y=surf+Math.sin(x*0.04+time*0.002)*3; x?ctx.lineTo(x,y):ctx.moveTo(x,y);} ctx.stroke();

        // ── light shafts (attenuate with clarity/depth) ──
        const reach = surf + (H-surf) * Math.min(1, 0.3 + clar*sunB*0.95);   // how deep light penetrates
        for(let i=0;i<5;i++){
            const gx=sx-40+i*44; const spread=26;
            const g=ctx.createLinearGradient(0,surf,0,reach);
            g.addColorStop(0,`rgba(255,240,180,${(0.18+sunB*0.22)*clar})`); g.addColorStop(1,'rgba(255,240,180,0)');
            ctx.fillStyle=g; ctx.beginPath(); ctx.moveTo(gx,surf); ctx.lineTo(gx+spread,surf); ctx.lineTo(gx+spread+90,reach); ctx.lineTo(gx-30,reach); ctx.closePath(); ctx.fill();
        }
        // light-reaching-bed meter
        const atBed=Math.round(M.effLight*100);
        ctx.fillStyle=`rgba(255,240,180,${0.5+0.5*clar})`; ctx.font='600 15px "JetBrains Mono", monospace'; ctx.textAlign='left';
        ctx.fillText('light reaching plants: '+atBed+'%', 30, surf+34);

        // ── suspended mud (only visible when murky) ──
        if(murk>0.08){ ctx.fillStyle=mudc; for(const p of mud){ p.x+=p.vx; p.y+=p.vy; p.ph+=0.02; if(p.x<0)p.x=W; if(p.x>W)p.x=0; if(p.y>H-30)p.y=surf+10; if(p.y<surf)p.y=H-40; ctx.globalAlpha=murk*(.25+.2*Math.sin(p.ph)); ctx.beginPath(); ctx.arc(p.x,p.y,p.r,0,6.283); ctx.fill(); } ctx.globalAlpha=1; }

        // ── lake bed ──
        const bed=ctx.createLinearGradient(0,H-70,0,H); bed.addColorStop(0,mixhex('#3a2f22',mudc,.5)); bed.addColorStop(1,'#241c14');
        ctx.fillStyle=bed; ctx.beginPath(); ctx.moveTo(0,H-55); for(let x=0;x<=W;x+=40){ ctx.lineTo(x,H-55+Math.sin(x*0.03)*7);} ctx.lineTo(W,H); ctx.lineTo(0,H); ctx.closePath(); ctx.fill();

        // ── pondweed (greener + taller when rate high) ──
        const vigor=0.5+M.rate*0.7;
        WEED.forEach((w,wi)=>{
            const h=90+vigor*90; const segs=7;
            ctx.strokeStyle=mixhex('#1f5a3a',leaf,vigor); ctx.lineWidth=5; ctx.lineCap='round';
            ctx.beginPath(); ctx.moveTo(w.x,H-58);
            for(let s=1;s<=segs;s++){ const t=s/segs; const sway=Math.sin(time*0.0016+w.sway+t*2)*(10+t*16)*(0.5+vigor*0.6); ctx.lineTo(w.x+sway, H-58 - t*h);} ctx.stroke();
            // leaves
            ctx.fillStyle=mixhex('#2a6b45',leaf,vigor);
            for(let s=2;s<=segs;s++){ const t=s/segs; const sway=Math.sin(time*0.0016+w.sway+t*2)*(10+t*16)*(0.5+vigor*0.6); const lx=w.x+sway, ly=H-58-t*h; const dir=(s%2)?1:-1; ctx.beginPath(); ctx.ellipse(lx+dir*9, ly, 11,5, dir*0.5,0,6.283); ctx.fill(); }
        });

        // ── O2 bubbles: spawn rate ∝ photosynthesis rate ──
        if(Math.random() < M.rate*0.9) spawnBubble();
        ctx.strokeStyle=o2; ctx.fillStyle='rgba(127,227,255,.18)';
        for(let i=bubbles.length-1;i>=0;i--){ const b=bubbles[i]; b.y-=b.v*(1+vigor); b.wob+=0.1; b.x+=Math.sin(b.wob)*0.4;
            ctx.lineWidth=1.4; ctx.beginPath(); ctx.arc(b.x,b.y,b.r,0,6.283); ctx.fill(); ctx.stroke();
            if(b.y<surf+4){ // pop at surface
                bubbles.splice(i,1);
            }
        }

        raf=requestAnimationFrame(frame);
    }
    // helpers
    function html_light(){ return document.documentElement.getAttribute('data-mode')==='light'; }
    function hx(h){ h=h.replace('#',''); if(h.length===3)h=h.split('').map(c=>c+c).join(''); return [parseInt(h.slice(0,2),16),parseInt(h.slice(2,4),16),parseInt(h.slice(4,6),16)]; }
    function mixhex(a,b,t){ t=Math.max(0,Math.min(1,t)); const A=hx(a),B=hx(b); return `rgb(${A.map((v,i)=>Math.round(v+(B[i]-v)*t)).join(',')})`; }

    function start(){ if(raf)return; resize(); last=0; raf=requestAnimationFrame(frame); }
    function stop(){ if(raf){cancelAnimationFrame(raf); raf=null;} }
    function set(s){ st=s; M=model(st); }
    return {start,stop,resize,set,getM:()=>M};
})();

/* ═══════════ GRAPH · rate vs light intensity ═══════════ */
const GRAPH=(()=>{
    const cv=$('graphCanvas'), ctx=cv.getContext('2d');
    const css=(n,f)=>{const v=getComputedStyle(document.documentElement).getPropertyValue(n).trim();return v||f;};
    function draw(st){
        const M=model(st); const dpr=Math.min(window.devicePixelRatio||1,2);
        const w=cv.clientWidth||300, h=150; cv.width=w*dpr; cv.height=h*dpr; ctx.setTransform(dpr,0,0,dpr,0,0);
        ctx.clearRect(0,0,w,h);
        const pad={l:34,r:12,t:12,b:26}, gw=w-pad.l-pad.r, gh=h-pad.t-pad.b;
        const ink=css('--ink-soft','#A6C4B3'), line=css('--line','rgba(160,200,175,.14)'), acc=css('--acc','#34D399'), sun=css('--sun','#FBBF24');
        // axes
        ctx.strokeStyle=line; ctx.lineWidth=1; ctx.beginPath(); ctx.moveTo(pad.l,pad.t); ctx.lineTo(pad.l,pad.t+gh); ctx.lineTo(pad.l+gw,pad.t+gh); ctx.stroke();
        ctx.fillStyle=ink; ctx.font='600 10px Inter,sans-serif'; ctx.textAlign='center';
        ctx.fillText('light reaching plants →', pad.l+gw/2, h-6);
        ctx.save(); ctx.translate(11,pad.t+gh/2); ctx.rotate(-Math.PI/2); ctx.fillText('rate →',0,0); ctx.restore();
        // curve: rate(L) = plateau * min(1, L/sat)
        const X=L=>pad.l+L*gw, Y=r=>pad.t+gh-r*gh;
        ctx.strokeStyle=acc; ctx.lineWidth=2.4; ctx.beginPath();
        for(let i=0;i<=60;i++){ const L=i/60; const r=M.plateau*Math.min(1,L/LIGHT_SAT); const x=X(L),y=Y(r); i?ctx.lineTo(x,y):ctx.moveTo(x,y);} ctx.stroke();
        // plateau dashed
        ctx.strokeStyle='rgba(160,200,175,.35)'; ctx.setLineDash([4,4]); ctx.beginPath(); ctx.moveTo(pad.l,Y(M.plateau)); ctx.lineTo(pad.l+gw,Y(M.plateau)); ctx.stroke(); ctx.setLineDash([]);
        // saturation marker
        ctx.strokeStyle='rgba(251,191,36,.4)'; ctx.setLineDash([3,3]); ctx.beginPath(); ctx.moveTo(X(LIGHT_SAT),pad.t); ctx.lineTo(X(LIGHT_SAT),pad.t+gh); ctx.stroke(); ctx.setLineDash([]);
        // operating point
        const opx=X(Math.min(1,M.effLight)), opy=Y(M.rate);
        ctx.fillStyle=sun; ctx.strokeStyle='#fff'; ctx.lineWidth=2; ctx.beginPath(); ctx.arc(opx,opy,6,0,6.283); ctx.fill(); ctx.stroke();
        ctx.strokeStyle='rgba(251,191,36,.5)'; ctx.setLineDash([2,3]); ctx.beginPath(); ctx.moveTo(opx,opy); ctx.lineTo(opx,pad.t+gh); ctx.moveTo(opx,opy); ctx.lineTo(pad.l,opy); ctx.stroke(); ctx.setLineDash([]);
    }
    return {draw};
})();

/* ═══════════ UI ═══════════ */
function updateUI(){
    const st={sun:+$('sSun').value, clarity:+$('sClar').value, co2:+$('sCo2').value, temp:+$('sTemp').value};
    const M=model(st);
    LAKE.set(st); GRAPH.draw(st);
    $('vSun').textContent=st.sun+'%'; $('vCo2').textContent=st.co2+'%'; $('vTemp').textContent=st.temp+'°C';
    $('vClar').textContent = st.clarity>75?'clear' : st.clarity>45?'hazy' : st.clarity>22?'murky' : 'muddy';
    $('sSun').style.setProperty('--fill',st.sun+'%'); $('sCo2').style.setProperty('--fill',st.co2+'%');
    $('sClar').style.setProperty('--fill',st.clarity+'%'); $('sTemp').style.setProperty('--fill',((st.temp-2)/43*100)+'%');
    $('rRate').innerHTML=Math.round(M.rate*100)+'<small>%</small>';
    $('rLim').textContent=M.lim;
    const f=$('flag');
    if(M.lim==='light' && st.clarity<40){ f.className='flag warn'; f.innerHTML='🌧 Murkier / deeper water has lowered the <b>light intensity</b> at the plants — light is now limiting, so the rate falls. That is exam answer <b>C</b>.'; }
    else if(M.lim==='light'){ f.className='flag warn'; f.innerHTML='Light is the limiting factor — raise the light (or clear the water) to speed photosynthesis up.'; }
    else{ f.className='flag ok'; f.innerHTML=`Plenty of light — the rate is now capped by <b>${M.lim}</b>. Stir the mud to make light the limiting factor.`; }
}
function setClear(){ $('sSun').value=80; $('sClar').value=90; $('sCo2').value=70; $('sTemp').value=22; updateUI(); }
function stirMud(){ $('sClar').value=18; updateUI(); }
['sSun','sClar','sCo2','sTemp'].forEach(id=>$(id).addEventListener('input',updateUI));
updateUI();

/* ═══════════ MODE ═══════════ */
function toggleMode(){
    const h=document.documentElement, dark=h.getAttribute('data-mode')==='dark';
    h.setAttribute('data-mode', dark?'light':'dark');
    $('modeIco').textContent=dark?'🌙':'☀️'; $('modeTxt').textContent=dark?'Dark':'Light';
    GRAPH.draw({sun:+$('sSun').value,clarity:+$('sClar').value,co2:+$('sCo2').value,temp:+$('sTemp').value});
    if(MM){ mmDone=false; initMermaid(); if($('p-mermaid').classList.contains('show')){ document.querySelectorAll('.mermaid').forEach(n=>{ if(n.dataset.orig){ n.removeAttribute('data-processed'); n.innerHTML=n.dataset.orig; } }); renderMermaid(); } }
}
</script>
@endverbatim
</body>
</html>
