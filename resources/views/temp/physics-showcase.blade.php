<!DOCTYPE html>
<html lang="en" data-mode="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>9702 · Q13 — Equilibrium of a Plank · V2 Showcase</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|fraunces:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />

    {{-- MathJax (solution equations) --}}
    <script>
        window.MathJax = {
            tex: { inlineMath: [['\\(', '\\)']], displayMath: [['$$', '$$']] },
            svg: { fontCache: 'global' },
            startup: { typeset: false }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>

    {{-- Three.js + OrbitControls (3D beam simulator) --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
</head>
<body>
@verbatim
<style>
    /* ═══════════════════ DESIGN TOKENS ═══════════════════ */
    :root{
        --bg:#080B14; --bg-2:#0B1120; --bg-3:#0E1626;
        --panel:rgba(255,255,255,.035); --panel-2:rgba(255,255,255,.055);
        --line:rgba(148,168,200,.14); --line-2:rgba(148,168,200,.09);
        --ink:#EAF1FB; --ink-soft:#9DB0CC; --ink-faint:#647691;
        --cyan:#38BDF8; --cyan-2:#22D3EE; --cyan-deep:#0EA5E9;
        --green:#4ADE80; --green-deep:#22C55E;
        --amber:#FBBF24; --red:#FB7185; --violet:#A78BFA;
        --glow-cyan:0 0 0 1px rgba(56,189,248,.25), 0 8px 40px -8px rgba(56,189,248,.45);
        --shadow:0 24px 60px -20px rgba(0,0,0,.65);
        --radius:18px;
    }
    html[data-mode="light"]{
        --bg:#EEF2F8; --bg-2:#E7ECF4; --bg-3:#FFFFFF;
        --panel:rgba(255,255,255,.75); --panel-2:#FFFFFF;
        --line:rgba(15,40,80,.12); --line-2:rgba(15,40,80,.07);
        --ink:#0C1B32; --ink-soft:#41556F; --ink-faint:#7387A0;
        --cyan-deep:#0284C7; --green-deep:#16A34A;
        --shadow:0 24px 60px -24px rgba(20,40,80,.28);
    }

    *{box-sizing:border-box;}
    html,body{margin:0;padding:0;}
    body{
        font-family:'Inter',system-ui,-apple-system,sans-serif;
        background:var(--bg); color:var(--ink); line-height:1.6;
        -webkit-font-smoothing:antialiased; overflow-x:hidden;
        transition:background .5s ease, color .5s ease;
    }
    /* cinematic backdrop glows */
    body::before{
        content:""; position:fixed; inset:0; z-index:0; pointer-events:none;
        background:
            radial-gradient(900px 520px at 78% -8%, rgba(56,189,248,.16), transparent 60%),
            radial-gradient(760px 500px at 8% 8%, rgba(167,139,250,.11), transparent 62%),
            radial-gradient(1100px 700px at 50% 118%, rgba(34,211,238,.07), transparent 60%);
    }
    html[data-mode="light"] body::before{ opacity:.5; }
    .serif{font-family:'Fraunces',Georgia,serif;}
    .mono{font-family:'JetBrains Mono',ui-monospace,monospace;font-feature-settings:"tnum";}
    .shell{position:relative; z-index:1; max-width:1120px; margin:0 auto; padding:0 22px 96px;}

    /* ═══════════════════ HERO ═══════════════════ */
    .hero{ position:relative; padding:40px 0 26px; }
    .hero-row{display:flex; align-items:flex-start; gap:18px; flex-wrap:wrap;}
    .mark{
        width:52px; height:52px; border-radius:15px; flex:none;
        background:linear-gradient(140deg,var(--cyan),var(--cyan-deep));
        display:grid; place-items:center; color:#04121f; font-weight:800; font-size:15px;
        box-shadow:var(--glow-cyan); letter-spacing:-.02em;
    }
    .hero h1{ margin:2px 0 0; font-size:15px; font-weight:600; letter-spacing:.02em; color:var(--ink-soft); }
    .hero .title{ font-family:'Fraunces',serif; font-size:clamp(29px,4vw,42px); font-weight:600; letter-spacing:-.015em; line-height:1.08; margin:6px 0 12px; color:var(--ink);}
    .chips{display:flex; gap:8px; flex-wrap:wrap;}
    .chip{ display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px; font-size:12px; font-weight:600; letter-spacing:.02em; background:var(--panel); border:1px solid var(--line); color:var(--ink-soft);}
    .chip.accent{ color:var(--cyan); border-color:rgba(56,189,248,.35); background:rgba(56,189,248,.08);}
    .hero .spacer{flex:1;}
    .modebtn{ background:var(--panel); border:1px solid var(--line); color:var(--ink-soft); border-radius:11px; padding:9px 14px; cursor:pointer; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:8px; transition:.2s;}
    .modebtn:hover{ color:var(--ink); border-color:var(--line); background:var(--panel-2);}

    /* ═══════════════════ SEGMENTED TABS ═══════════════════ */
    .tabwrap{ position:sticky; top:12px; z-index:30; margin:8px 0 30px;}
    .tabs{ position:relative; display:flex; gap:2px; padding:6px; border-radius:15px; background:var(--panel); border:1px solid var(--line); backdrop-filter:blur(18px); -webkit-backdrop-filter:blur(18px); overflow-x:auto; scrollbar-width:none; box-shadow:var(--shadow);}
    .tabs::-webkit-scrollbar{display:none;}
    .tab{ position:relative; z-index:2; border:0; background:transparent; color:var(--ink-soft); font:inherit; font-size:13.5px; font-weight:600; padding:10px 16px; border-radius:10px; cursor:pointer; white-space:nowrap; display:inline-flex; align-items:center; gap:8px; transition:color .25s;}
    .tab:hover{color:var(--ink);}
    .tab.active{color:var(--ink);}
    .tab .ic{font-size:15px; opacity:.9;}
    .tab-ind{ position:absolute; z-index:1; top:6px; height:calc(100% - 12px); border-radius:10px; background:linear-gradient(180deg,var(--panel-2),var(--panel)); border:1px solid var(--line); box-shadow:0 4px 16px -6px rgba(0,0,0,.4); transition:transform .38s cubic-bezier(.34,1.3,.4,1), width .38s cubic-bezier(.34,1.3,.4,1);}
    html[data-mode="dark"] .tab.active{ text-shadow:0 0 20px rgba(56,189,248,.35);}

    .panel{display:none; animation:rise .5s cubic-bezier(.2,.7,.2,1);}
    .panel.show{display:block;}
    @keyframes rise{from{opacity:0; transform:translateY(14px);} to{opacity:1; transform:none;}}

    /* ═══════════════════ CARDS ═══════════════════ */
    .card{ position:relative; background:var(--panel); border:1px solid var(--line); border-radius:var(--radius); padding:26px 28px; margin-bottom:18px; backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px); box-shadow:var(--shadow);}
    .card.flush{padding:0; overflow:hidden;}
    .eyebrow{ font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:var(--cyan); margin-bottom:12px; display:flex; align-items:center; gap:9px;}
    .eyebrow::before{content:""; width:22px; height:2px; border-radius:2px; background:linear-gradient(90deg,var(--cyan),transparent);}
    .card h2{ font-family:'Fraunces',serif; font-size:27px; font-weight:600; letter-spacing:-.01em; margin:0 0 6px; color:var(--ink);}
    .lead{ font-size:16.5px; color:var(--ink); line-height:1.7;}
    .lead strong{ color:var(--ink); font-weight:700; background:linear-gradient(transparent 62%, rgba(56,189,248,.20) 62%);}
    .muted{color:var(--ink-soft);}

    .qfig{ margin-top:22px; border-radius:14px; border:1px solid var(--line); background:linear-gradient(180deg,var(--bg-3),var(--bg-2)); padding:22px;}
    html[data-mode="light"] .qfig{ background:linear-gradient(180deg,#fff,#f4f7fc);}

    /* options */
    .opts{display:grid; gap:11px; margin-top:6px;}
    .opt{ display:grid; grid-template-columns:auto 1fr auto; align-items:center; gap:16px; padding:15px 18px; border:1px solid var(--line); border-radius:13px; background:var(--panel); transition:.25s;}
    .opt .k{ width:34px; height:34px; border-radius:9px; display:grid; place-items:center; font-weight:700; font-size:15px; border:1px solid var(--line); color:var(--ink-soft); background:var(--bg-3);}
    .opt .vals{display:flex; gap:26px; font-size:15.5px;}
    .opt .vals b{font-family:'JetBrains Mono',monospace; font-weight:600; color:var(--ink);}
    .opt .vals span{color:var(--ink-faint); font-size:12.5px; display:block; margin-bottom:1px;}
    .opt .why{font-size:12.5px; color:var(--ink-faint); text-align:right; max-width:150px;}
    .opt.correct{ border-color:rgba(74,222,128,.5); background:linear-gradient(100deg,rgba(74,222,128,.12),var(--panel));}
    .opt.correct .k{ background:var(--green-deep); color:#03130a; border-color:transparent; box-shadow:0 0 22px -4px rgba(74,222,128,.6);}
    .opt.correct .why{color:var(--green); font-weight:600;}

    /* solution steps */
    .steps{counter-reset:s; margin-top:8px;}
    .step{ position:relative; display:grid; grid-template-columns:auto 1fr; gap:18px; padding:0 0 26px 0;}
    .step:not(:last-child)::after{ content:""; position:absolute; left:17px; top:40px; bottom:6px; width:2px; background:linear-gradient(var(--line),transparent);}
    .step .n{ width:36px; height:36px; border-radius:11px; background:var(--bg-3); border:1px solid var(--line); display:grid; place-items:center; font-weight:700; font-family:'JetBrains Mono',monospace; color:var(--cyan); z-index:1;}
    .step h4{margin:6px 0 8px; font-size:16px; font-weight:700; color:var(--ink);}
    .eqbox{ background:var(--bg-3); border:1px solid var(--line); border-radius:11px; padding:10px 18px; margin:10px 0; color:var(--ink); text-align:center;}
    html[data-mode="light"] .eqbox{background:#f4f7fc;}
    .eqbox mjx-container{ max-width:100%; margin:0!important;}
    .eqbox mjx-container svg{ max-width:100%; height:auto;}
    .finalcard{ display:flex; align-items:center; gap:20px; margin-top:6px; padding:22px 26px; border-radius:15px; border:1px solid rgba(74,222,128,.4); background:linear-gradient(110deg, rgba(74,222,128,.14), var(--panel));}
    .finalcard .badge{ font-family:'Fraunces',serif; font-size:44px; font-weight:600; color:var(--green); width:64px; height:64px; border-radius:16px; display:grid; place-items:center; background:rgba(74,222,128,.12); border:1px solid rgba(74,222,128,.4); flex:none; box-shadow:0 0 30px -6px rgba(74,222,128,.5);}

    /* ═══════════════════ INTERACTIVE — 3D SIM ═══════════════════ */
    .sim-head{padding:26px 28px 0;}
    .sim-grid{ display:grid; grid-template-columns:1.55fr 1fr; gap:0; align-items:stretch;}
    @media (max-width:900px){ .sim-grid{grid-template-columns:1fr;} }
    .stage-col{ padding:18px 18px 22px 28px; min-width:0;}
    @media (max-width:900px){ .stage-col{padding:18px;} }
    .stage{ position:relative; width:100%; aspect-ratio:16/11; border-radius:16px; overflow:hidden; background:radial-gradient(120% 120% at 50% 0%, #10203a 0%, #070c16 70%); border:1px solid var(--line); box-shadow:inset 0 1px 0 rgba(255,255,255,.05), var(--shadow);}
    html[data-mode="light"] .stage{ background:radial-gradient(120% 120% at 50% 0%, #dbe7f7 0%, #b9cbe6 75%);}
    .stage canvas{display:block; width:100%!important; height:100%!important;}
    .stage-badge{ position:absolute; top:14px; left:14px; z-index:5; font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--cyan); background:rgba(8,14,26,.55); border:1px solid rgba(56,189,248,.3); padding:5px 11px; border-radius:999px; backdrop-filter:blur(8px);}
    .stage-hint{ position:absolute; bottom:12px; left:50%; transform:translateX(-50%); z-index:5; font-size:11.5px; color:var(--ink-soft); background:rgba(8,14,26,.5); border:1px solid var(--line); padding:5px 13px; border-radius:999px; backdrop-filter:blur(8px); white-space:nowrap; max-width:calc(100% - 20px);}
    @media (max-width:560px){ .stage-hint{font-size:10px; padding:4px 10px;} .stage-badge{font-size:9.5px; padding:4px 8px;} }
    html[data-mode="light"] .stage-hint, html[data-mode="light"] .stage-badge{background:rgba(255,255,255,.7);}
    .stage-tools{ position:absolute; top:12px; right:12px; z-index:5; display:flex; gap:6px;}
    .stool{ width:34px; height:34px; border-radius:9px; display:grid; place-items:center; cursor:pointer; font-size:15px; color:var(--ink-soft); background:rgba(8,14,26,.55); border:1px solid var(--line); backdrop-filter:blur(8px); transition:.2s;}
    .stool:hover{color:var(--cyan); border-color:rgba(56,189,248,.4);}
    html[data-mode="light"] .stool{background:rgba(255,255,255,.75);}

    .ctrl-col{ padding:18px 28px 22px 18px; display:flex; flex-direction:column; gap:14px; border-left:1px solid var(--line);}
    @media (max-width:900px){ .ctrl-col{border-left:0; border-top:1px solid var(--line); padding:18px;} }
    .readout{ display:grid; grid-template-columns:1fr 1fr; gap:10px;}
    .stat{ background:var(--bg-3); border:1px solid var(--line); border-radius:13px; padding:13px 15px;}
    html[data-mode="light"] .stat{background:#fff;}
    .stat.wide{grid-column:1 / -1;}
    .stat .lab{font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:var(--ink-faint); margin-bottom:5px; display:flex; align-items:center; gap:6px;}
    .dot{width:8px; height:8px; border-radius:50%; flex:none;}
    .stat .num{font-family:'JetBrains Mono',monospace; font-size:24px; font-weight:600; color:var(--ink); letter-spacing:-.01em; line-height:1;}
    .stat .num small{font-size:13px; color:var(--ink-faint); font-weight:500;}
    .stat.cyan .num{color:var(--cyan);} .stat.green .num{color:var(--green);}

    /* reaction split bar */
    .splitwrap{margin-top:4px;}
    .splitcap{display:flex; justify-content:space-between; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-faint); margin-bottom:7px;}
    .splitbar{height:30px; border-radius:9px; overflow:hidden; display:flex; border:1px solid var(--line); background:var(--bg-3);}
    .splitbar > i{ display:flex; align-items:center; justify-content:center; font-style:normal; font-family:'JetBrains Mono',monospace; font-size:12px; font-weight:600; color:#04121f; transition:flex-basis .35s cubic-bezier(.34,1.2,.4,1); overflow:hidden; white-space:nowrap;}
    .seg-fx{background:linear-gradient(180deg,var(--cyan),var(--cyan-deep));}
    .seg-ry{background:linear-gradient(180deg,var(--green),var(--green-deep));}

    .slider-row label{ display:flex; justify-content:space-between; align-items:baseline; font-size:12.5px; font-weight:600; color:var(--ink-soft); margin-bottom:8px;}
    .slider-row label b{font-family:'JetBrains Mono',monospace; color:var(--cyan); font-size:14px;}
    input[type=range].sld{ -webkit-appearance:none; appearance:none; width:100%; height:6px; border-radius:6px; background:linear-gradient(90deg,var(--cyan) 0%,var(--cyan) var(--fill,0%), var(--bg-3) var(--fill,0%)); outline:none; cursor:pointer;}
    input[type=range].sld::-webkit-slider-thumb{ -webkit-appearance:none; width:20px; height:20px; border-radius:50%; background:#fff; border:3px solid var(--cyan); box-shadow:0 2px 10px rgba(56,189,248,.6); cursor:grab;}
    input[type=range].sld::-moz-range-thumb{ width:18px; height:18px; border-radius:50%; background:#fff; border:3px solid var(--cyan); cursor:grab;}
    .snaps{display:flex; gap:8px;}
    .snap{ flex:1; border:1px solid var(--line); background:var(--bg-3); color:var(--ink-soft); border-radius:10px; padding:9px 6px; font:inherit; font-size:12.5px; font-weight:600; cursor:pointer; transition:.2s;}
    html[data-mode="light"] .snap{background:#fff;}
    .snap:hover{color:var(--cyan); border-color:rgba(56,189,248,.45);}
    .liveeq{ font-family:'JetBrains Mono',monospace; font-size:12.5px; color:var(--ink-soft); background:var(--bg-3); border:1px solid var(--line); border-radius:11px; padding:12px 14px; line-height:1.7;}
    html[data-mode="light"] .liveeq{background:#fff;}
    .liveeq b{color:var(--cyan);} .liveeq .g{color:var(--green);}
    .flag{ padding:12px 15px; border-radius:12px; font-size:13px; font-weight:600; border:1px solid; display:flex; gap:9px; align-items:flex-start; line-height:1.45;}
    .flag.ok{background:rgba(74,222,128,.1); color:var(--green); border-color:rgba(74,222,128,.4);}
    .flag.warn{background:rgba(251,191,36,.1); color:var(--amber); border-color:rgba(251,191,36,.4);}
    .flag.neutral{background:var(--bg-3); color:var(--ink-soft); border-color:var(--line);}

    /* ═══════════════════ FLASH / MEM ═══════════════════ */
    .grid2{display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:15px;}
    .flip{perspective:1600px; height:180px; cursor:pointer;}
    .flip-in{position:relative; width:100%; height:100%; transition:transform .7s cubic-bezier(.2,.8,.2,1); transform-style:preserve-3d;}
    .flip.on .flip-in{transform:rotateY(180deg);}
    .face{position:absolute; inset:0; backface-visibility:hidden; border-radius:15px; padding:22px; display:flex; flex-direction:column; justify-content:center; border:1px solid var(--line);}
    .face.front{background:var(--panel);}
    .face.back{background:linear-gradient(140deg,var(--cyan-deep),#0b2036); color:#eaf6ff; transform:rotateY(180deg); border-color:transparent;}
    .face .q{font-size:16px; font-weight:600; color:var(--ink);}
    .face .a{font-size:14.5px; line-height:1.55;}
    .face .hint{position:absolute; bottom:13px; right:16px; font-size:10px; letter-spacing:.1em; text-transform:uppercase; opacity:.5;}
    .mem{border:1px solid var(--line); border-left:3px solid var(--cyan); border-radius:13px; padding:17px 19px; background:var(--panel);}
    .mem.green{border-left-color:var(--green);} .mem.amber{border-left-color:var(--amber);}
    .mem .k{font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-faint); margin-bottom:7px;}
    .mem .t{font-size:15px; font-weight:700; color:var(--ink); margin-bottom:5px;}
    .mem .d{font-size:13.5px; color:var(--ink-soft);}
    .mem .f{font-family:'JetBrains Mono',monospace; font-size:12.5px; background:var(--bg-3); border-radius:7px; padding:7px 11px; margin-top:9px; color:var(--cyan); display:inline-block;}
    html[data-mode="light"] .mem .f{background:#f0f4fa;}

    .mermaid-box{background:var(--bg-3); border:1px solid var(--line); border-radius:13px; padding:20px; overflow-x:auto; text-align:center;}
    html[data-mode="light"] .mermaid-box{background:#fff;}
    .json-box{ background:#060a12; color:#c9d6e8; border-radius:13px; padding:22px; overflow-x:auto; font-family:'JetBrains Mono',monospace; font-size:12.5px; line-height:1.75; border:1px solid var(--line);}
    .json-box .jk{color:#7cc7ff;} .json-box .js{color:#9ae6b4;} .json-box .jn{color:#f0a868;} .json-box .jb{color:#ff8091;}
    .copy{float:right; background:var(--bg-3); border:1px solid var(--line); color:var(--ink-soft); border-radius:9px; padding:6px 13px; font:inherit; font-size:12px; font-weight:600; cursor:pointer;}
    .copy:hover{color:var(--cyan); border-color:rgba(56,189,248,.4);}
</style>

<div class="shell">

    <!-- ═══════════ HERO ═══════════ -->
    <header class="hero">
        <div class="hero-row">
            <div class="mark">9702</div>
            <div style="min-width:0;">
                <h1>Cambridge A Level Physics · Paper 1 (Multiple Choice)</h1>
                <div class="title serif">Equilibrium of a Loaded Plank</div>
                <div class="chips">
                    <span class="chip accent">◆ Q13 · answer D</span>
                    <span class="chip">2025 Feb/Mar · variant 12</span>
                    <span class="chip">Forces &amp; Moments</span>
                    <span class="chip">Principle of moments</span>
                </div>
            </div>
            <div class="spacer"></div>
            <button class="modebtn" onclick="toggleMode()"><span id="modeIco">☀️</span><span id="modeTxt">Light</span></button>
        </div>
    </header>

    <!-- ═══════════ TABS ═══════════ -->
    <div class="tabwrap">
        <nav class="tabs" id="tabs">
            <span class="tab-ind" id="tabInd"></span>
            <button class="tab active" data-tab="question"><span class="ic">◈</span> Question</button>
            <button class="tab" data-tab="interactive"><span class="ic">▲</span> 3D Simulator</button>
            <button class="tab" data-tab="solution"><span class="ic">∑</span> Solution</button>
            <button class="tab" data-tab="flashcards"><span class="ic">◑</span> Flashcards</button>
            <button class="tab" data-tab="memcards"><span class="ic">▣</span> Memcards</button>
            <button class="tab" data-tab="mermaid"><span class="ic">⌥</span> Flow</button>
            <button class="tab" data-tab="json"><span class="ic">{ }</span> JSON</button>
        </nav>
    </div>

    <!-- ═══════════ QUESTION ═══════════ -->
    <section class="panel show" id="p-question">
        <div class="card">
            <div class="eyebrow">The problem</div>
            <p class="lead">A uniform plank <strong>XY</strong> of length <strong>4.0&nbsp;m</strong> and weight <strong>300&nbsp;N</strong> rests on fixed supports at its ends X and Y. A child of weight <strong>600&nbsp;N</strong> stands at different positions along the plank. The support at end X exerts a force <strong>F</strong> vertically upwards. What is the magnitude of <strong>F</strong> when the child stands at X, and when the child stands at Y?</p>

            <div class="qfig">
                <svg viewBox="0 0 680 250" width="100%" style="max-width:680px; display:block; margin:0 auto;" font-family="Inter, sans-serif">
                    <defs>
                        <marker id="ar" markerWidth="10" markerHeight="10" refX="5" refY="5" orient="auto"><path d="M2,2 L8,5 L2,8" fill="none" stroke="#9DB0CC" stroke-width="1.4"/></marker>
                        <linearGradient id="pk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c9a36b"/><stop offset="1" stop-color="#9c7844"/></linearGradient>
                    </defs>
                    <rect x="95" y="150" width="490" height="15" rx="3" fill="url(#pk)" stroke="rgba(0,0,0,.3)"/>
                    <polygon points="95,169 78,202 112,202" fill="#1a2740" stroke="#38BDF8" stroke-width="1.5"/>
                    <polygon points="585,169 568,202 602,202" fill="#1a2740" stroke="#4ADE80" stroke-width="1.5"/>
                    <text x="82" y="140" font-size="17" font-weight="700" fill="#EAF1FB">X</text>
                    <text x="580" y="140" font-size="17" font-weight="700" fill="#EAF1FB">Y</text>
                    <g stroke="#EAF1FB" stroke-width="2.2" fill="none" stroke-linecap="round">
                        <circle cx="118" cy="66" r="10" fill="rgba(56,189,248,.25)"/>
                        <line x1="118" y1="76" x2="118" y2="118"/><line x1="118" y1="90" x2="103" y2="108"/><line x1="118" y1="90" x2="133" y2="108"/>
                        <line x1="118" y1="118" x2="105" y2="148"/><line x1="118" y1="118" x2="131" y2="148"/>
                    </g>
                    <text x="150" y="70" font-size="13.5" font-weight="600" fill="#38BDF8">child · 600 N</text>
                    <text x="430" y="116" font-size="13.5" font-weight="600" fill="#9DB0CC">uniform plank · 300 N</text>
                    <line x1="428" y1="122" x2="400" y2="150" stroke="#647691" stroke-width="1.2"/>
                    <line x1="95" y1="224" x2="585" y2="224" stroke="#647691" stroke-width="1.2" marker-start="url(#ar)" marker-end="url(#ar)"/>
                    <text x="340" y="244" font-size="14" font-weight="600" fill="#EAF1FB" text-anchor="middle">4.0 m</text>
                </svg>
            </div>
        </div>

        <div class="card">
            <div class="eyebrow">Choose one</div>
            <div class="opts" id="optList"></div>
        </div>
    </section>

    <!-- ═══════════ INTERACTIVE ═══════════ -->
    <section class="panel" id="p-interactive">
        <div class="card flush">
            <div class="sim-head">
                <div class="eyebrow">Interactive · drag the child in 3D</div>
                <h2 class="serif">Reaction-force simulator</h2>
                <p class="muted" style="margin:2px 0 0; font-size:14.5px;">Grab the child and slide them along the beam — or use the slider. Both support reactions update live, the glowing force vectors scale in real time, and the readout tells you exactly which exam option you are standing on.</p>
            </div>
            <div class="sim-grid">
                <div class="stage-col">
                    <div class="stage" id="stage">
                        <span class="stage-badge">Live physics · τ = F·d</span>
                        <div class="stage-tools">
                            <div class="stool" id="btnSpin" title="Toggle auto-rotate">⟳</div>
                            <div class="stool" onclick="resetView()" title="Reset camera">⌖</div>
                        </div>
                        <div class="stage-hint">Drag the child · orbit · scroll to zoom</div>
                    </div>
                </div>
                <div class="ctrl-col">
                    <div class="readout">
                        <div class="stat cyan"><div class="lab"><span class="dot" style="background:var(--cyan)"></span>F at X</div><div class="num" id="rFx">750<small> N</small></div></div>
                        <div class="stat green"><div class="lab"><span class="dot" style="background:var(--green)"></span>R at Y</div><div class="num" id="rFy">150<small> N</small></div></div>
                        <div class="stat wide"><div class="lab">Child distance from X · total upward force</div><div class="num"><span id="rPos">0.0</span><small> m</small> &nbsp;·&nbsp; <span id="rTot" style="color:var(--ink-soft)">900</span><small> N</small></div></div>
                    </div>

                    <div class="splitwrap">
                        <div class="splitcap"><span>reaction split</span><span>X ↔ Y</span></div>
                        <div class="splitbar"><i class="seg-fx" id="segFx">750</i><i class="seg-ry" id="segRy">150</i></div>
                    </div>

                    <div class="slider-row">
                        <label>Child position from X <b id="vPos">0.0 m</b></label>
                        <input type="range" class="sld" id="posSld" min="0" max="4" step="0.05" value="0">
                    </div>
                    <div class="snaps">
                        <button class="snap" onclick="setPos(0)">◄ at X</button>
                        <button class="snap" onclick="setPos(2)">centre</button>
                        <button class="snap" onclick="setPos(4)">at Y ►</button>
                    </div>

                    <div class="liveeq" id="liveEq"></div>
                    <div class="flag ok" id="flag"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════ SOLUTION ═══════════ -->
    <section class="panel" id="p-solution">
        <div class="card">
            <div class="eyebrow">Worked solution · principle of moments</div>
            <h2 class="serif" style="margin-bottom:18px;">Take moments about Y to isolate F</h2>
            <div class="steps">
                <div class="step"><div class="n">1</div><div><h4>List every vertical force</h4><p class="muted">Three downward: the plank's <strong>300 N</strong> acting at its centre (2.0 m from each end, because it is uniform) and the child's <strong>600 N</strong>. Two upward: the support reactions <strong>F</strong> at X and <strong>R</strong> at Y.</p></div></div>
                <div class="step"><div class="n">2</div><div><h4>Pivot at Y so its reaction vanishes</h4><p class="muted">A moment taken about Y has zero contribution from R (its line of action passes through Y), leaving F as the only unknown:</p><div class="eqbox">$$ F \times L = W_{plank}\cdot\tfrac{L}{2} \;+\; W_{child}\cdot d_{\text{child}\to Y} $$</div></div></div>
                <div class="step"><div class="n">3</div><div><h4>Child at X — that's 4.0 m from Y</h4><div class="eqbox">$$ F(4.0) = (300)(2.0) + (600)(4.0) = 600 + 2400 = 3000 $$</div><div class="eqbox">$$ F = \tfrac{3000}{4.0} = \mathbf{750\ N} $$</div></div></div>
                <div class="step"><div class="n">4</div><div><h4>Child at Y — that's 0 m from Y</h4><div class="eqbox">$$ F(4.0) = (300)(2.0) + (600)(0) = 600 \;\Rightarrow\; F = \mathbf{150\ N} $$</div><p class="muted">Directly above Y the child exerts no moment about Y, so F only carries the plank's share.</p></div></div>
            </div>
            <div class="finalcard">
                <div class="badge serif">D</div>
                <div>
                    <div style="font-weight:700; font-size:17px; color:var(--ink);">F = 750 N at X &nbsp;·&nbsp; 150 N at Y</div>
                    <div class="muted" style="font-size:13.5px; margin-top:2px;">Sanity check — reactions always sum to the total weight: 750 + 150 = 900 N = 300 + 600 N. ✓</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════ FLASHCARDS ═══════════ -->
    <section class="panel" id="p-flashcards">
        <div class="card"><div class="eyebrow">Flashcards · tap to flip</div><h2 class="serif" style="margin-bottom:16px;">Self-test the core ideas</h2><div class="grid2" id="flashGrid"></div></div>
    </section>

    <!-- ═══════════ MEMCARDS ═══════════ -->
    <section class="panel" id="p-memcards">
        <div class="card"><div class="eyebrow">Memcards · commit to memory</div><h2 class="serif" style="margin-bottom:16px;">The facts &amp; formulae behind it</h2><div class="grid2" id="memGrid"></div></div>
    </section>

    <!-- ═══════════ MERMAID ═══════════ -->
    <section class="panel" id="p-mermaid">
        <div class="card"><div class="eyebrow">Flow · the method</div><h2 class="serif" style="margin-bottom:16px;">Solution strategy</h2><div class="mermaid-box"><pre class="mermaid" id="mm1">
flowchart TD
    A[Identify every vertical force] --> B["Plank 300 N at centre (2.0 m)"]
    A --> C[Child 600 N at distance d]
    A --> D["Reactions F at X, R at Y"]
    D --> E[Take moments about Y]
    E --> F["F x 4.0 = 300 x 2.0 + 600 x (dist of child from Y)"]
    F --> G{Child stands where?}
    G -->|At X = 4.0 m from Y| H["F = 3000 / 4 = 750 N"]
    G -->|At Y = 0 m from Y| I["F = 600 / 4 = 150 N"]
    H --> J([Answer D: 750 N and 150 N])
    I --> J
        </pre></div></div>
        <div class="card"><div class="eyebrow">Flow · the concept</div><h2 class="serif" style="margin-bottom:16px;">Why it works — static equilibrium</h2><div class="mermaid-box"><pre class="mermaid" id="mm2">
graph LR
    EQ([Static Equilibrium]) --> T1["Net force = 0"]
    EQ --> T2["Net moment = 0"]
    T2 --> M[Principle of Moments]
    M --> CW[Clockwise moments]
    M --> ACW[Anticlockwise moments]
    CW --> EQM{{CW = ACW}}
    ACW --> EQM
    T1 --> SUM["F + R = 900 N"]
        </pre></div></div>
    </section>

    <!-- ═══════════ JSON ═══════════ -->
    <section class="panel" id="p-json">
        <div class="card"><div class="eyebrow">JSON · structured model</div><button class="copy" onclick="copyJson()">⧉ Copy</button><h2 class="serif" style="margin-bottom:16px;">Machine-readable question object</h2><div class="json-box" id="jsonOut"></div></div>
    </section>
</div>

<script>
/* ═══════════ DATA ═══════════ */
const DATA = {
    id:"9702_m25_qp_12_q13",
    paper:{code:"9702",level:"A Level",subject:"Physics",session:"2025 Feb/Mar",variant:"qp_12",question:13,type:"mcq"},
    topic:{unit:"Forces, density & pressure",subtopic:"Turning effects of forces",skills:["principle of moments","static equilibrium","uniform body weight at centre"]},
    given:{plank_length_m:4.0,plank_weight_N:300,child_weight_N:600,supports:["X","Y"],plank_uniform:true},
    stem:"A uniform plank XY (4.0 m, 300 N) rests on supports at ends X and Y. A child (600 N) stands in different positions. Support at X exerts force F upward. Find F when the child stands at X and at Y.",
    options:[
        {label:"A",F_at_X:600,F_at_Y:0,error:"used child weight only; ignored the plank"},
        {label:"B",F_at_X:600,F_at_Y:150,error:"F at X wrong — did not add the child's full moment"},
        {label:"C",F_at_X:750,F_at_Y:0,error:"forgot the plank still needs support when child is at Y"},
        {label:"D",F_at_X:750,F_at_Y:150,error:null}
    ],
    answer:"D",
    model:{method:"moments about Y",formula_F:"F = (300*2 + 600*(4-d)) / 4 = 750 - 150*d",formula_R:"R = 900 - F = 150 + 150*d",units:"N; d in metres from X"},
    solution:{child_at_X:{equation:"F*4.0 = 300*2.0 + 600*4.0 = 3000",F_N:750},child_at_Y:{equation:"F*4.0 = 300*2.0 + 600*0 = 600",F_N:150},check:"750 + 150 = 900 N = total weight"}
};
const FLASH=[
    {q:"Where does the weight of a uniform plank act?",a:"At its geometric centre — 2.0 m from each end of a 4.0 m plank."},
    {q:"Which point do you take moments about to find F at X?",a:"Point Y — it makes the unknown reaction there have zero moment, leaving F alone."},
    {q:"Child stands at X — what is F?",a:"F·4.0 = 300·2.0 + 600·4.0 = 3000 → F = 750 N."},
    {q:"Child stands at Y — what is F?",a:"The child has zero moment about Y. F·4.0 = 300·2.0 = 600 → F = 150 N."},
    {q:"Fast check on any reaction answer?",a:"The two support reactions must sum to the total weight: 750 + 150 = 900 N."},
    {q:"Why D and not C (750, 0)?",a:"At Y the plank's 300 N still needs supporting, so F cannot be 0 — it is 150 N."}
];
const MEM=[
    {tone:"",k:"Definition",t:"Moment of a force",d:"Moment = force × perpendicular distance from the pivot.",f:"τ = F × d"},
    {tone:"green",k:"Principle",t:"Principle of moments",d:"In equilibrium, about any point:",f:"Σ clockwise = Σ anticlockwise"},
    {tone:"",k:"Condition",t:"Two equilibrium conditions",d:"Both must hold at once for a rigid body.",f:"ΣF = 0  AND  Στ = 0"},
    {tone:"amber",k:"Shortcut",t:"Uniform body weight",d:"A uniform plank's whole weight acts at its midpoint.",f:"x̄ = L / 2"},
    {tone:"amber",k:"Exam trick",t:"Pivot to kill an unknown",d:"Take moments about the support you don't want — its reaction drops out.",f:"about Y ⇒ R_Y vanishes"},
    {tone:"green",k:"Sanity check",t:"Reactions sum to weight",d:"Upward supports balance every downward weight.",f:"F + R = 900 N"}
];

/* ═══════════ RENDER static content ═══════════ */
const $=id=>document.getElementById(id);
$('optList').innerHTML = DATA.options.map(o=>`
    <div class="opt ${o.label===DATA.answer?'correct':''}">
        <div class="k">${o.label}</div>
        <div class="vals"><div><span>F at X</span><b>${o.F_at_X} N</b></div><div><span>F at Y</span><b>${o.F_at_Y} N</b></div></div>
        <div class="why">${o.label===DATA.answer?'✓ correct':o.error}</div>
    </div>`).join('');
$('flashGrid').innerHTML = FLASH.map(c=>`<div class="flip"><div class="flip-in">
    <div class="face front"><div class="q">${c.q}</div><div class="hint">tap to reveal</div></div>
    <div class="face back"><div class="a">${c.a}</div><div class="hint">tap to flip back</div></div></div></div>`).join('');
document.querySelectorAll('.flip').forEach(f=>f.addEventListener('click',()=>f.classList.toggle('on')));
$('memGrid').innerHTML = MEM.map(m=>`<div class="mem ${m.tone}"><div class="k">${m.k}</div><div class="t">${m.t}</div><div class="d">${m.d}</div><div class="f">${m.f}</div></div>`).join('');
function syntaxJson(o){let s=JSON.stringify(o,null,2).replace(/&/g,'&amp;').replace(/</g,'&lt;');return s.replace(/("(\\u[a-fA-F0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,m=>{let c='jn';if(/^"/.test(m))c=/:$/.test(m)?'jk':'js';else if(/true|false|null/.test(m))c='jb';return `<span class="${c}">${m}</span>`;});}
$('jsonOut').innerHTML=syntaxJson(DATA);
function copyJson(){navigator.clipboard.writeText(JSON.stringify(DATA,null,2));}

/* ═══════════ TABS with sliding indicator ═══════════ */
const tabsEl=$('tabs'), ind=$('tabInd');
function moveInd(btn){ ind.style.width=btn.offsetWidth+'px'; ind.style.transform=`translateX(${btn.offsetLeft-6}px)`; }
function activateTab(btn){
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(x=>x.classList.remove('show'));
    btn.classList.add('active'); $('p-'+btn.dataset.tab).classList.add('show'); moveInd(btn);
    if(btn.dataset.tab==='mermaid') renderMermaid();
    if(btn.dataset.tab==='interactive'){ ensureSim(); requestAnimationFrame(()=>SIM&&SIM.resize()); }
    if(window.MathJax&&window.MathJax.typesetPromise) window.MathJax.typesetPromise();
}
document.querySelectorAll('.tab').forEach(t=>t.addEventListener('click',()=>activateTab(t)));
window.addEventListener('load',()=>moveInd(document.querySelector('.tab.active')));
window.addEventListener('resize',()=>{const a=document.querySelector('.tab.active'); if(a) moveInd(a);});

/* ═══════════ MERMAID (lazy) ═══════════ */
let mmReady=false, mmDone=false, MM=null;
import('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs').then(m=>{MM=m.default; initMermaid(); mmReady=true; if($('p-mermaid').classList.contains('show')) renderMermaid();});
function themeVars(){const s=getComputedStyle(document.documentElement),g=(n,f)=>(s.getPropertyValue(n).trim()||f);return{primaryColor:g('--bg-3','#0E1626'),primaryBorderColor:g('--cyan','#38BDF8'),primaryTextColor:g('--ink','#EAF1FB'),lineColor:g('--ink-soft','#9DB0CC'),secondaryColor:g('--bg-2','#0B1120'),tertiaryColor:g('--panel','#111')};}
function initMermaid(){ if(!MM)return; MM.initialize({startOnLoad:false,theme:'base',themeVariables:{fontFamily:'Inter, sans-serif',...themeVars()}}); }
function renderMermaid(){ if(!mmReady||mmDone) return; mmDone=true; MM.run({nodes:document.querySelectorAll('.mermaid')}); }

/* ═══════════ 3D SIMULATOR ═══════════ */
const L=4.0, Wp=300, Wc=600, Wtot=900;
const Fof = d => 750 - 150*d;          // reaction at X
const Rof = d => 150 + 150*d;          // reaction at Y
let SIM=null, simBooted=false;
function ensureSim(){ if(simBooted) return; simBooted=true; try{ SIM=buildSim(); }catch(e){ console.error('sim boot failed',e); } }

function buildSim(){
    const host=$('stage');
    const THREE=window.THREE;
    const scene=new THREE.Scene();
    const camera=new THREE.PerspectiveCamera(42, 16/11, 0.1, 200);
    const renderer=new THREE.WebGLRenderer({antialias:true, alpha:true});
    renderer.setPixelRatio(Math.min(window.devicePixelRatio,2));
    renderer.outputEncoding = THREE.sRGBEncoding;
    host.appendChild(renderer.domElement);

    const controls=new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping=true; controls.dampingFactor=.08;
    controls.enablePan=false; controls.minDistance=11; controls.maxDistance=26;
    controls.minPolarAngle=0.6; controls.maxPolarAngle=1.42;
    controls.rotateSpeed=.65;
    const HOME={theta:0.62, phi:1.04, r:18.5};
    function applyHome(){ const s=HOME; camera.position.set(s.r*Math.sin(s.phi)*Math.sin(s.theta), s.r*Math.cos(s.phi), s.r*Math.sin(s.phi)*Math.cos(s.theta)); controls.target.set(0,1.5,0); controls.update(); }
    applyHome();

    /* lights */
    scene.add(new THREE.AmbientLight(0xbcd4ff, .55));
    const key=new THREE.DirectionalLight(0xffffff,1.15); key.position.set(6,12,8); scene.add(key);
    const rim=new THREE.DirectionalLight(0x38bdf8,.9); rim.position.set(-8,4,-6); scene.add(rim);
    const fill=new THREE.PointLight(0x22d3ee,.5,60); fill.position.set(0,6,10); scene.add(fill);

    /* floor grid */
    const grid=new THREE.GridHelper(40,40,0x2b415f,0x18263c); grid.position.y=-2.55; scene.add(grid);
    const floor=new THREE.Mesh(new THREE.PlaneGeometry(60,60), new THREE.MeshStandardMaterial({color:0x0a1220,roughness:1,metalness:0,transparent:true,opacity:.55}));
    floor.rotation.x=-Math.PI/2; floor.position.y=-2.56; scene.add(floor);

    /* materials */
    const woodMat=new THREE.MeshStandardMaterial({color:0xc39a5f,roughness:.62,metalness:.05});
    const supMat=new THREE.MeshStandardMaterial({color:0x243651,roughness:.4,metalness:.5,emissive:0x0a1a2e,emissiveIntensity:.4});
    const childMat=new THREE.MeshStandardMaterial({color:0x38bdf8,roughness:.35,metalness:.1,emissive:0x0e5a86,emissiveIntensity:.55});

    const PSPAN=8, x0=-PSPAN/2;                     // plank spans x=-4..+4
    const mToX = d => x0 + (d/L)*PSPAN;

    /* plank */
    const plank=new THREE.Mesh(new THREE.BoxGeometry(PSPAN+0.6,0.42,1.7), woodMat);
    plank.position.y=0; scene.add(plank);
    // wood edge line
    const edge=new THREE.LineSegments(new THREE.EdgesGeometry(plank.geometry), new THREE.LineBasicMaterial({color:0x5b422a})); plank.add(edge);

    /* supports (triangular prisms) at each end */
    function support(x,color){
        const shape=new THREE.Shape(); shape.moveTo(-0.9,0); shape.lineTo(0.9,0); shape.lineTo(0,1.85); shape.lineTo(-0.9,0);
        const g=new THREE.ExtrudeGeometry(shape,{depth:1.3,bevelEnabled:false});
        g.center(); g.rotateX(0);
        const m=new THREE.Mesh(g, supMat.clone());
        m.material.emissive=new THREE.Color(color); m.material.emissiveIntensity=.35;
        m.position.set(x,-1.15,0); m.scale.set(1,1.25,1);
        scene.add(m); return m;
    }
    support(mToX(0),0x38bdf8); support(mToX(4),0x4ade80);
    // labels via sprites
    function label(text,color){ const c=document.createElement('canvas'); c.width=128;c.height=128; const cx=c.getContext('2d'); cx.fillStyle=color; cx.font='bold 84px Inter,sans-serif'; cx.textAlign='center'; cx.textBaseline='middle'; cx.fillText(text,64,68); const tex=new THREE.CanvasTexture(c); const sp=new THREE.Sprite(new THREE.SpriteMaterial({map:tex,transparent:true,depthTest:false})); sp.scale.set(1.3,1.3,1); return sp; }
    const lblX=label('X','#EAF1FB'); lblX.position.set(mToX(0),1.2,0); scene.add(lblX);
    const lblY=label('Y','#EAF1FB'); lblY.position.set(mToX(4),1.2,0); scene.add(lblY);

    /* child */
    const child=new THREE.Group();
    const body=new THREE.Mesh(new THREE.CylinderGeometry(0.30,0.40,1.15,20), childMat);
    body.position.y=0.72; child.add(body);
    const shoulder=new THREE.Mesh(new THREE.SphereGeometry(0.30,18,14), childMat); shoulder.position.y=1.22; shoulder.scale.set(1,0.6,1); child.add(shoulder);
    const head=new THREE.Mesh(new THREE.SphereGeometry(0.32,20,16), childMat); head.position.y=1.5; child.add(head);
    const ring=new THREE.Mesh(new THREE.TorusGeometry(0.6,0.05,10,32), new THREE.MeshBasicMaterial({color:0x7fe0ff})); ring.rotation.x=Math.PI/2; ring.position.y=0.24; child.add(ring);
    child.position.set(mToX(0),0.21,0); scene.add(child);

    /* force arrows — custom (shaft + cone), glowing */
    function makeArrow(color, up){
        const g=new THREE.Group();
        const mat=new THREE.MeshStandardMaterial({color,emissive:color,emissiveIntensity:.9,roughness:.3});
        const shaft=new THREE.Mesh(new THREE.CylinderGeometry(0.07,0.07,1,12), mat);
        const cone=new THREE.Mesh(new THREE.ConeGeometry(0.2,0.5,16), mat);
        g.add(shaft); g.add(cone); g.userData={shaft,cone,up};
        return g;
    }
    function setArrow(g,x,baseY,len,up){
        const {shaft,cone}=g.userData; len=Math.max(0.4,len);
        shaft.scale.y=len; shaft.position.set(0, up? baseY+len/2 : baseY-len/2, 0);
        cone.position.set(0, up? baseY+len+0.25 : baseY-len-0.25, 0);
        cone.rotation.z = up?0:Math.PI;
        g.position.x=x;
    }
    const aFx=makeArrow(0x38bdf8,true); scene.add(aFx);   // reaction up at X
    const aRy=makeArrow(0x4ade80,true); scene.add(aRy);   // reaction up at Y
    const aWp=makeArrow(0xfb7185,false); scene.add(aWp);  // weight of plank
    const aWc=makeArrow(0xfbbf24,false); scene.add(aWc);  // weight of child

    /* HTML overlay labels for forces */
    const lay=document.createElement('div'); lay.style.cssText='position:absolute;inset:0;pointer-events:none;z-index:4;'; host.appendChild(lay);
    function tag(txt,color){ const d=document.createElement('div'); d.style.cssText=`position:absolute;transform:translate(-50%,-50%);font:600 12px 'JetBrains Mono',monospace;color:${color};background:rgba(6,11,20,.72);border:1px solid ${color}55;padding:2px 7px;border-radius:7px;white-space:nowrap;`; lay.appendChild(d); return d; }
    const tFx=tag('','#38BDF8'), tRy=tag('','#4ADE80'), tWp=tag('','#FB7185'), tWc=tag('','#FBBF24');
    function project(x,y,z){ const v=new THREE.Vector3(x,y,z).project(camera); return {x:(v.x*.5+.5)*host.clientWidth, y:(-v.y*.5+.5)*host.clientHeight, vis:v.z<1}; }

    /* dragging the child via raycast on a horizontal plane at plank height */
    const ray=new THREE.Raycaster(); const dragPlane=new THREE.Plane(new THREE.Vector3(0,1,0), -0.21);
    const ndc=new THREE.Vector2(); let dragging=false;
    function pointerNDC(e){ const r=renderer.domElement.getBoundingClientRect(); ndc.x=((e.clientX-r.left)/r.width)*2-1; ndc.y=-((e.clientY-r.top)/r.height)*2+1; }
    function hitChild(e){ pointerNDC(e); ray.setFromCamera(ndc,camera); return ray.intersectObject(child,true).length>0; }
    function dragTo(e){ pointerNDC(e); ray.setFromCamera(ndc,camera); const p=new THREE.Vector3(); if(ray.ray.intersectPlane(dragPlane,p)){ let d=(p.x-x0)/PSPAN*L; d=Math.max(0,Math.min(L,d)); setPos(+d.toFixed(2), true); } }
    renderer.domElement.addEventListener('pointerdown',e=>{ if(hitChild(e)){ dragging=true; controls.enabled=false; ring.material.color.set(0xffffff); renderer.domElement.setPointerCapture(e.pointerId);} });
    renderer.domElement.addEventListener('pointermove',e=>{ if(dragging) dragTo(e); });
    const endDrag=()=>{ dragging=false; controls.enabled=true; ring.material.color.set(0x7fe0ff); };
    renderer.domElement.addEventListener('pointerup',endDrag); renderer.domElement.addEventListener('pointercancel',endDrag);

    /* spin toggle */
    let spinning=false; $('btnSpin').addEventListener('click',()=>{ spinning=!spinning; $('btnSpin').style.color=spinning?'#38BDF8':''; });

    let curD=0;
    function render3D(d){
        const cx=mToX(d);
        child.position.x=cx; ring.position.set(0,0.24,0);
        const F=Fof(d), R=Rof(d);
        const sc=v=>0.5+(v/900)*2.7;                 // force → world length
        setArrow(aFx, mToX(0), 0.22, sc(F), true);
        setArrow(aRy, mToX(4), 0.22, sc(R), true);
        setArrow(aWp, mToX(2), -0.22, sc(Wp), false);
        setArrow(aWc, cx, -0.22, sc(Wc), false);
        // labels
        const put=(t,x,y,z,txt)=>{ const p=project(x,y,z); t.textContent=txt; t.style.left=p.x+'px'; t.style.top=p.y+'px'; t.style.display=p.vis?'block':'none'; };
        put(tFx, mToX(0), 0.22+sc(F)+0.6, 0, Math.round(F)+' N');
        put(tRy, mToX(4), 0.22+sc(R)+0.6, 0, Math.round(R)+' N');
        put(tWp, mToX(2), -0.22-sc(Wp)-0.6, 0, '300 N');
        put(tWc, cx, -0.22-sc(Wc)-0.6, 0, '600 N');
        curD=d;
    }

    function loop(){ requestAnimationFrame(loop); if(spinning && !dragging){ controls.autoRotate=false; const t=controls.getAzimuthalAngle(); camera.position.applyAxisAngle(new THREE.Vector3(0,1,0),0.0035); controls.update(); } controls.update(); render3D(curD); renderer.render(scene,camera); }

    function resize(){ const w=host.clientWidth, h=host.clientHeight; if(!w||!h) return; renderer.setSize(w,h,false); camera.aspect=w/h; camera.updateProjectionMatrix(); }
    const ro=new ResizeObserver(resize); ro.observe(host); resize();
    loop();

    return { setD:d=>render3D(d), resize, home:applyHome };
}

/* ═══════════ shared UI update ═══════════ */
function fmt(n){return Math.round(n);}
function updateUI(d){
    const F=Fof(d), R=Rof(d);
    $('rFx').innerHTML=`${fmt(F)}<small> N</small>`;
    $('rFy').innerHTML=`${fmt(R)}<small> N</small>`;
    $('rPos').textContent=d.toFixed(1);
    $('rTot').textContent=fmt(F+R);
    $('vPos').textContent=d.toFixed(1)+' m';
    const sld=$('posSld'); sld.style.setProperty('--fill',(d/4*100)+'%');
    // split bar
    const fp=F/Wtot*100;
    $('segFx').style.flexBasis=fp+'%'; $('segFx').textContent=fmt(F);
    $('segRy').style.flexBasis=(100-fp)+'%'; $('segRy').textContent=fmt(R);
    // live equation
    $('liveEq').innerHTML=`F = ( 300·2 + 600·(4−<b>${d.toFixed(1)}</b>) ) / 4<br>&nbsp;&nbsp;= ( 600 + <b>${fmt(600*(4-d))}</b> ) / 4 = <span class="g">${fmt(F)} N</span>`;
    // flag
    const f=$('flag');
    if(Math.abs(d)<0.03){ f.className='flag ok'; f.innerHTML='✓ Child at <b>X</b> → F = 750 N, R = 150 N. This is exam option <b>D</b> (the X column).'; }
    else if(Math.abs(d-4)<0.03){ f.className='flag ok'; f.innerHTML='✓ Child at <b>Y</b> → F = 150 N, R = 750 N. This is exam option <b>D</b> (the Y column).'; }
    else if(Math.abs(d-2)<0.03){ f.className='flag warn'; f.innerHTML='Child at the centre → both supports share equally: F = R = 450 N.'; }
    else{ f.className='flag neutral'; f.innerHTML=`Child ${d.toFixed(1)} m from X → F = ${fmt(F)} N. Slide to <b>0 m</b> or <b>4 m</b> to hit the exam answer.`; }
}
function setPos(d, fromDrag){ d=Math.max(0,Math.min(4,+d)); $('posSld').value=d; updateUI(d); if(SIM) SIM.setD(d); }
function resetView(){ if(SIM) SIM.home(); }
$('posSld').addEventListener('input',e=>setPos(+e.target.value));
updateUI(0);

/* ═══════════ MODE toggle ═══════════ */
function toggleMode(){
    const h=document.documentElement, dark=h.getAttribute('data-mode')==='dark';
    h.setAttribute('data-mode', dark?'light':'dark');
    $('modeIco').textContent=dark?'🌙':'☀️'; $('modeTxt').textContent=dark?'Dark':'Light';
    if(MM){ mmDone=false; initMermaid(); document.querySelectorAll('.mermaid').forEach(n=>{n.removeAttribute('data-processed'); }); if($('p-mermaid').classList.contains('show')){ document.querySelectorAll('.mermaid').forEach(n=>{ if(n.dataset.orig) n.innerHTML=n.dataset.orig; }); renderMermaid(); } }
}
/* preserve mermaid source for re-render */
document.querySelectorAll('.mermaid').forEach(n=>n.dataset.orig=n.textContent);
</script>
@endverbatim
</body>
</html>
