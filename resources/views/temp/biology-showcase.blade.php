<!DOCTYPE html>
<html lang="en" data-mode="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>9700 · Q39 — Energy Flow &amp; Photosynthesis · V2 Showcase</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|fraunces:400,500,600,700|jetbrains-mono:400,500,600" rel="stylesheet" />

    <script>
        window.MathJax = { tex:{inlineMath:[['\\(','\\)']],displayMath:[['$$','$$']]}, svg:{fontCache:'global'}, startup:{typeset:false} };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>
</head>
<body>
@verbatim
<style>
    /* ═══════════════════ TOKENS (bio palette: emerald + gold) ═══════════════════ */
    :root{
        --bg:#06100C; --bg-2:#08150F; --bg-3:#0B1D15;
        --panel:rgba(255,255,255,.035); --panel-2:rgba(255,255,255,.055);
        --line:rgba(160,200,175,.14); --line-2:rgba(160,200,175,.09);
        --ink:#EAF6EE; --ink-soft:#A6C4B3; --ink-faint:#68877A;
        --em:#34D399; --em-2:#6EE7B7; --em-deep:#059669;      /* emerald = primary accent */
        --green:#22C55E; --green-deep:#16A34A;                 /* NPP / correct */
        --gold:#FBBF24; --gold-deep:#D97706;                   /* sunlight / respiration */
        --slate:#7C93A8;                                        /* not captured */
        --red:#FB7185;
        --glow-em:0 0 0 1px rgba(52,211,153,.25), 0 8px 40px -8px rgba(52,211,153,.45);
        --shadow:0 24px 60px -20px rgba(0,0,0,.65);
        --radius:18px;
    }
    html[data-mode="light"]{
        --bg:#EDF4EF; --bg-2:#E4EFE8; --bg-3:#FFFFFF;
        --panel:rgba(255,255,255,.75); --panel-2:#FFFFFF;
        --line:rgba(15,60,35,.12); --line-2:rgba(15,60,35,.07);
        --ink:#0B2318; --ink-soft:#3C5A49; --ink-faint:#6E8877;
        --em-deep:#047857; --green-deep:#15803D; --gold-deep:#B45309;
        --shadow:0 24px 60px -24px rgba(20,60,40,.24);
    }
    *{box-sizing:border-box;} html,body{margin:0;padding:0;}
    body{ font-family:'Inter',system-ui,-apple-system,sans-serif; background:var(--bg); color:var(--ink); line-height:1.6; -webkit-font-smoothing:antialiased; overflow-x:hidden; transition:background .5s ease,color .5s ease;}
    body::before{ content:""; position:fixed; inset:0; z-index:0; pointer-events:none;
        background:
            radial-gradient(900px 520px at 76% -8%, rgba(52,211,153,.16), transparent 60%),
            radial-gradient(760px 500px at 6% 6%, rgba(251,191,36,.10), transparent 62%),
            radial-gradient(1100px 700px at 50% 118%, rgba(34,197,94,.07), transparent 60%);}
    html[data-mode="light"] body::before{opacity:.5;}
    .serif{font-family:'Fraunces',Georgia,serif;} .mono{font-family:'JetBrains Mono',ui-monospace,monospace;font-feature-settings:"tnum";}
    .shell{position:relative; z-index:1; max-width:1120px; margin:0 auto; padding:0 22px 96px;}

    .hero{position:relative; padding:40px 0 26px;}
    .hero-row{display:flex; align-items:flex-start; gap:18px; flex-wrap:wrap;}
    .mark{ width:52px; height:52px; border-radius:15px; flex:none; background:linear-gradient(140deg,var(--em),var(--em-deep)); display:grid; place-items:center; color:#04150d; font-weight:800; font-size:15px; box-shadow:var(--glow-em); letter-spacing:-.02em;}
    .hero h1{margin:2px 0 0; font-size:15px; font-weight:600; letter-spacing:.02em; color:var(--ink-soft);}
    .hero .title{font-family:'Fraunces',serif; font-size:clamp(29px,4vw,42px); font-weight:600; letter-spacing:-.015em; line-height:1.08; margin:6px 0 12px; color:var(--ink);}
    .chips{display:flex; gap:8px; flex-wrap:wrap;}
    .chip{display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:999px; font-size:12px; font-weight:600; letter-spacing:.02em; background:var(--panel); border:1px solid var(--line); color:var(--ink-soft);}
    .chip.accent{color:var(--em); border-color:rgba(52,211,153,.35); background:rgba(52,211,153,.08);}
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
    .eyebrow{font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:var(--em); margin-bottom:12px; display:flex; align-items:center; gap:9px;}
    .eyebrow::before{content:""; width:22px; height:2px; border-radius:2px; background:linear-gradient(90deg,var(--em),transparent);}
    .card h2{font-family:'Fraunces',serif; font-size:27px; font-weight:600; letter-spacing:-.01em; margin:0 0 6px; color:var(--ink);}
    .lead{font-size:16.5px; color:var(--ink); line-height:1.7;}
    .lead strong{color:var(--ink); font-weight:700; background:linear-gradient(transparent 62%, rgba(52,211,153,.20) 62%);}
    .muted{color:var(--ink-soft);}
    .qfig{margin-top:22px; border-radius:14px; border:1px solid var(--line); background:linear-gradient(180deg,var(--bg-3),var(--bg-2)); padding:22px;}
    html[data-mode="light"] .qfig{background:linear-gradient(180deg,#fff,#f2f8f4);}

    .opts{display:grid; gap:11px; margin-top:6px;}
    .opt{display:grid; grid-template-columns:auto auto 1fr; align-items:center; gap:16px; padding:15px 18px; border:1px solid var(--line); border-radius:13px; background:var(--panel); transition:.25s;}
    .opt .k{width:34px; height:34px; border-radius:9px; display:grid; place-items:center; font-weight:700; font-size:15px; border:1px solid var(--line); color:var(--ink-soft); background:var(--bg-3);}
    .opt .pct{font-family:'JetBrains Mono',monospace; font-weight:600; font-size:19px; color:var(--ink); min-width:80px;}
    .opt .why{font-size:12.5px; color:var(--ink-faint);}
    .opt.correct{border-color:rgba(34,197,94,.5); background:linear-gradient(100deg,rgba(34,197,94,.12),var(--panel));}
    .opt.correct .k{background:var(--green-deep); color:#03130a; border-color:transparent; box-shadow:0 0 22px -4px rgba(34,197,94,.6);}
    .opt.correct .pct{color:var(--green);} .opt.correct .why{color:var(--green); font-weight:600;}

    .steps{counter-reset:s; margin-top:8px;}
    .step{position:relative; display:grid; grid-template-columns:auto 1fr; gap:18px; padding:0 0 26px 0;}
    .step:not(:last-child)::after{content:""; position:absolute; left:17px; top:40px; bottom:6px; width:2px; background:linear-gradient(var(--line),transparent);}
    .step .n{width:36px; height:36px; border-radius:11px; background:var(--bg-3); border:1px solid var(--line); display:grid; place-items:center; font-weight:700; font-family:'JetBrains Mono',monospace; color:var(--em); z-index:1;}
    .step h4{margin:6px 0 8px; font-size:16px; font-weight:700; color:var(--ink);}
    .eqbox{background:var(--bg-3); border:1px solid var(--line); border-radius:11px; padding:10px 18px; margin:10px 0; color:var(--ink); text-align:center;}
    html[data-mode="light"] .eqbox{background:#f2f8f4;}
    .eqbox mjx-container{max-width:100%; margin:0!important;} .eqbox mjx-container svg{max-width:100%; height:auto;}
    .finalcard{display:flex; align-items:center; gap:20px; margin-top:6px; padding:22px 26px; border-radius:15px; border:1px solid rgba(34,197,94,.4); background:linear-gradient(110deg,rgba(34,197,94,.14),var(--panel));}
    .finalcard .badge{font-family:'Fraunces',serif; font-size:44px; font-weight:600; color:var(--green); width:64px; height:64px; border-radius:16px; display:grid; place-items:center; background:rgba(34,197,94,.12); border:1px solid rgba(34,197,94,.4); flex:none; box-shadow:0 0 30px -6px rgba(34,197,94,.5);}

    /* ═══════════ INTERACTIVE — canvas energy flow ═══════════ */
    .sim-head{padding:26px 28px 0;}
    .sim-grid{display:grid; grid-template-columns:1.55fr 1fr; gap:0; align-items:stretch;}
    @media (max-width:900px){ .sim-grid{grid-template-columns:1fr;} }
    .stage-col{padding:18px 18px 22px 28px; min-width:0;}
    @media (max-width:900px){ .stage-col{padding:18px;} }
    .stage{position:relative; width:100%; aspect-ratio:16/10; border-radius:16px; overflow:hidden; background:radial-gradient(120% 120% at 50% -5%, #0d2417 0%, #050e0a 72%); border:1px solid var(--line); box-shadow:inset 0 1px 0 rgba(255,255,255,.05), var(--shadow);}
    html[data-mode="light"] .stage{background:radial-gradient(120% 120% at 50% -5%, #e7f5ec 0%, #cbe6d6 78%);}
    .stage canvas{display:block; width:100%; height:100%;}
    .stage-badge{position:absolute; top:14px; left:14px; z-index:5; font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--em); background:rgba(6,16,12,.55); border:1px solid rgba(52,211,153,.3); padding:5px 11px; border-radius:999px; backdrop-filter:blur(8px);}
    @media (max-width:560px){ .stage-badge{font-size:9.5px; padding:4px 8px;} }
    html[data-mode="light"] .stage-badge{background:rgba(255,255,255,.7);}

    .ctrl-col{padding:18px 28px 22px 18px; display:flex; flex-direction:column; gap:14px; border-left:1px solid var(--line);}
    @media (max-width:900px){ .ctrl-col{border-left:0; border-top:1px solid var(--line); padding:18px;} }
    .stat{background:var(--bg-3); border:1px solid var(--line); border-radius:13px; padding:14px 16px;}
    html[data-mode="light"] .stat{background:#fff;}
    .stat .lab{font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:var(--ink-faint); margin-bottom:6px;}
    .stat .num{font-family:'JetBrains Mono',monospace; font-size:30px; font-weight:600; color:var(--em); letter-spacing:-.01em; line-height:1;}
    .stat .num small{font-size:15px; color:var(--ink-faint);}
    .stat .sub{font-size:12px; color:var(--ink-soft); margin-top:6px; font-family:'JetBrains Mono',monospace;}

    .slider-row label{display:flex; justify-content:space-between; align-items:baseline; font-size:12.5px; font-weight:600; color:var(--ink-soft); margin-bottom:8px;}
    .slider-row label b{font-family:'JetBrains Mono',monospace; color:var(--em); font-size:14px;}
    input[type=range].sld{-webkit-appearance:none; appearance:none; width:100%; height:6px; border-radius:6px; background:linear-gradient(90deg,var(--c,var(--em)) 0%,var(--c,var(--em)) var(--fill,0%), var(--bg-3) var(--fill,0%)); outline:none; cursor:pointer;}
    input[type=range].sld::-webkit-slider-thumb{-webkit-appearance:none; width:20px; height:20px; border-radius:50%; background:#fff; border:3px solid var(--c,var(--em)); box-shadow:0 2px 10px rgba(52,211,153,.5); cursor:grab;}
    input[type=range].sld::-moz-range-thumb{width:18px; height:18px; border-radius:50%; background:#fff; border:3px solid var(--c,var(--em)); cursor:grab;}
    .snaps{display:flex; gap:8px;}
    .snap{flex:1; border:1px solid var(--line); background:var(--bg-3); color:var(--ink-soft); border-radius:10px; padding:9px 6px; font:inherit; font-size:12.5px; font-weight:600; cursor:pointer; transition:.2s;}
    html[data-mode="light"] .snap{background:#fff;}
    .snap:hover{color:var(--em); border-color:rgba(52,211,153,.45);}
    .flag{padding:12px 15px; border-radius:12px; font-size:13px; font-weight:600; border:1px solid; display:flex; gap:9px; align-items:flex-start; line-height:1.45;}
    .flag.ok{background:rgba(34,197,94,.1); color:var(--green); border-color:rgba(34,197,94,.4);}
    .flag.warn{background:rgba(251,191,36,.1); color:var(--gold); border-color:rgba(251,191,36,.4);}
    .flag.neutral{background:var(--bg-3); color:var(--ink-soft); border-color:var(--line);}

    .grid2{display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:15px;}
    .flip{perspective:1600px; height:180px; cursor:pointer;}
    .flip-in{position:relative; width:100%; height:100%; transition:transform .7s cubic-bezier(.2,.8,.2,1); transform-style:preserve-3d;}
    .flip.on .flip-in{transform:rotateY(180deg);}
    .face{position:absolute; inset:0; backface-visibility:hidden; border-radius:15px; padding:22px; display:flex; flex-direction:column; justify-content:center; border:1px solid var(--line);}
    .face.front{background:var(--panel);}
    .face.back{background:linear-gradient(140deg,var(--em-deep),#07231a); color:#eafff3; transform:rotateY(180deg); border-color:transparent;}
    .face .q{font-size:16px; font-weight:600; color:var(--ink);} .face .a{font-size:14.5px; line-height:1.55;}
    .face .hint{position:absolute; bottom:13px; right:16px; font-size:10px; letter-spacing:.1em; text-transform:uppercase; opacity:.5;}
    .mem{border:1px solid var(--line); border-left:3px solid var(--em); border-radius:13px; padding:17px 19px; background:var(--panel);}
    .mem.green{border-left-color:var(--green);} .mem.gold{border-left-color:var(--gold);}
    .mem .k{font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:var(--ink-faint); margin-bottom:7px;}
    .mem .t{font-size:15px; font-weight:700; color:var(--ink); margin-bottom:5px;}
    .mem .d{font-size:13.5px; color:var(--ink-soft);}
    .mem .f{font-family:'JetBrains Mono',monospace; font-size:12.5px; background:var(--bg-3); border-radius:7px; padding:7px 11px; margin-top:9px; color:var(--em); display:inline-block;}
    html[data-mode="light"] .mem .f{background:#eef6f1;}

    .mermaid-box{background:var(--bg-3); border:1px solid var(--line); border-radius:13px; padding:20px; overflow-x:auto; text-align:center;}
    html[data-mode="light"] .mermaid-box{background:#fff;}
    .json-box{background:#04100a; color:#cbe8d8; border-radius:13px; padding:22px; overflow-x:auto; font-family:'JetBrains Mono',monospace; font-size:12.5px; line-height:1.75; border:1px solid var(--line);}
    .json-box .jk{color:#6ee7b7;} .json-box .js{color:#bbf7d0;} .json-box .jn{color:#fcd34d;} .json-box .jb{color:#fda4af;}
    .copy{float:right; background:var(--bg-3); border:1px solid var(--line); color:var(--ink-soft); border-radius:9px; padding:6px 13px; font:inherit; font-size:12px; font-weight:600; cursor:pointer;}
    .copy:hover{color:var(--em); border-color:rgba(52,211,153,.4);}
</style>

<div class="shell">
    <!-- HERO -->
    <header class="hero">
        <div class="hero-row">
            <div class="mark">9700</div>
            <div style="min-width:0;">
                <h1>Cambridge A Level Biology · Paper 1 (Multiple Choice)</h1>
                <div class="title serif">Energy Flow through an Ecosystem</div>
                <div class="chips">
                    <span class="chip accent">◆ Q39 · answer D</span>
                    <span class="chip">2015 Oct/Nov · variant 12</span>
                    <span class="chip">Energy &amp; Ecosystems</span>
                    <span class="chip">Gross primary productivity</span>
                </div>
            </div>
            <div class="spacer"></div>
            <button class="modebtn" onclick="toggleMode()"><span id="modeIco">☀️</span><span id="modeTxt">Light</span></button>
        </div>
    </header>

    <!-- TABS -->
    <div class="tabwrap">
        <nav class="tabs" id="tabs">
            <span class="tab-ind" id="tabInd"></span>
            <button class="tab active" data-tab="question"><span class="ic">◈</span> Question</button>
            <button class="tab" data-tab="interactive"><span class="ic">☀</span> Energy Flow</button>
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
            <p class="lead">The diagram shows the flow of energy through a food chain. Sunlight of <strong>1 × 10⁶ kJ m⁻²y⁻¹</strong> falls on green plants, which <strong>store 27 000 kJ m⁻²y⁻¹</strong> and <strong>lose 3 000 kJ m⁻²y⁻¹</strong> in respiration. What percentage of the incident energy is used by the green plants for <strong>photosynthesis</strong>?</p>

            <div class="qfig">
                <svg viewBox="0 0 720 250" width="100%" style="max-width:720px; display:block; margin:0 auto;" font-family="Inter, sans-serif" font-size="12.5">
                    <defs><marker id="ar" markerWidth="9" markerHeight="9" refX="6" refY="4.5" orient="auto"><path d="M1,1 L8,4.5 L1,8 Z" fill="#7C93A8"/></marker></defs>
                    <text x="40" y="70" font-weight="600" fill="#FBBF24">sunlight</text>
                    <text x="40" y="106" fill="#EAF6EE">1 × 10⁶</text><text x="40" y="122" fill="#A6C4B3">kJ m⁻²y⁻¹</text>
                    <line x1="118" y1="100" x2="175" y2="150" stroke="#FBBF24" stroke-width="2.5" marker-end="url(#ar)"/>
                    <rect x="180" y="150" width="150" height="58" rx="8" fill="rgba(34,197,94,.14)" stroke="#22C55E" stroke-width="1.5"/>
                    <text x="255" y="174" text-anchor="middle" font-weight="700" fill="#EAF6EE">green plants</text>
                    <text x="255" y="194" text-anchor="middle" fill="#EAF6EE">27 000 kJ m⁻²y⁻¹</text>
                    <line x1="255" y1="150" x2="255" y2="100" stroke="#FBBF24" stroke-width="1.5" stroke-dasharray="4 3" marker-end="url(#ar)"/>
                    <text x="255" y="90" text-anchor="middle" fill="#FBBF24">3000 (respiration)</text>
                    <line x1="330" y1="179" x2="395" y2="179" stroke="#A6C4B3" stroke-width="2" marker-end="url(#ar)"/>
                    <rect x="400" y="150" width="120" height="58" rx="8" fill="rgba(255,255,255,.04)" stroke="#7C93A8" stroke-width="1.5"/>
                    <text x="460" y="184" text-anchor="middle" font-weight="600" fill="#EAF6EE">herbivore</text>
                    <line x1="520" y1="179" x2="585" y2="179" stroke="#A6C4B3" stroke-width="2" marker-end="url(#ar)"/>
                    <rect x="590" y="150" width="120" height="58" rx="8" fill="rgba(255,255,255,.04)" stroke="#7C93A8" stroke-width="1.5"/>
                    <text x="650" y="184" text-anchor="middle" font-weight="600" fill="#EAF6EE">carnivore</text>
                </svg>
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
                <div class="eyebrow">Interactive · watch the energy flow</div>
                <h2 class="serif">Photosynthesis energy explorer</h2>
                <p class="muted" style="margin:2px 0 0; font-size:14.5px;">Sunlight streams into the leaf as flowing particles. Almost all of it is lost — only a thin thread is <em>fixed</em> by photosynthesis (that's GPP). Adjust the sliders and watch the split, the food chain, and the live efficiency change — and see which exam option you land on.</p>
            </div>
            <div class="sim-grid">
                <div class="stage-col">
                    <div class="stage" id="stage">
                        <span class="stage-badge">Live · GPP = NPP + R</span>
                        <canvas id="flowCanvas"></canvas>
                    </div>
                </div>
                <div class="ctrl-col">
                    <div class="stat">
                        <div class="lab">% of sunlight used for photosynthesis</div>
                        <div class="num" id="rEff">3.00<small>%</small></div>
                        <div class="sub">GPP = NPP + R = <span id="rNppE">27 000</span> + <span id="rRespE">3 000</span> = <span id="rGpp" style="color:var(--em)">30 000</span> kJ</div>
                    </div>
                    <div class="slider-row" style="--c:var(--gold);">
                        <label>Incident sunlight <b id="vSun">1 000 000</b></label>
                        <input type="range" class="sld" id="sSun" min="200000" max="2000000" step="50000" value="1000000" style="--c:var(--gold);--fill:47%;">
                    </div>
                    <div class="slider-row" style="--c:var(--green);">
                        <label>Energy stored · NPP <b id="vNpp">27 000</b></label>
                        <input type="range" class="sld" id="sNpp" min="0" max="60000" step="1000" value="27000" style="--c:var(--green);--fill:45%;">
                    </div>
                    <div class="slider-row" style="--c:var(--gold);">
                        <label>Respiratory loss · R <b id="vResp">3 000</b></label>
                        <input type="range" class="sld" id="sResp" min="0" max="20000" step="500" value="3000" style="--c:var(--gold);--fill:15%;">
                    </div>
                    <div class="snaps"><button class="snap" onclick="resetExam()">↺ Exam values</button></div>
                    <div class="flag ok" id="flag"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- SOLUTION -->
    <section class="panel" id="p-solution">
        <div class="card">
            <div class="eyebrow">Worked solution · gross primary productivity</div>
            <h2 class="serif" style="margin-bottom:18px;">"Used for photosynthesis" = total energy fixed = GPP</h2>
            <div class="steps">
                <div class="step"><div class="n">1</div><div><h4>Decode the wording</h4><p class="muted">Energy "used for photosynthesis" is the <strong>total</strong> chemical energy the plant fixes — its <strong>Gross Primary Productivity (GPP)</strong>. That's more than the energy left stored, because some fixed energy is already burned in <strong>respiration</strong>.</p></div></div>
                <div class="step"><div class="n">2</div><div><h4>Rebuild GPP from the diagram</h4><p class="muted">Stored energy is the Net Primary Productivity (NPP = 27 000). Add back the respiratory loss (3 000):</p><div class="eqbox">$$ GPP = NPP + R = 27\,000 + 3\,000 = 30\,000\ \text{kJ m}^{-2}\text{y}^{-1} $$</div></div></div>
                <div class="step"><div class="n">3</div><div><h4>Express as a percentage of sunlight</h4><div class="eqbox">$$ \% = \frac{GPP}{\text{sunlight}} \times 100 = \frac{30\,000}{1\times10^{6}} \times 100 = \mathbf{3.00\%} $$</div></div></div>
                <div class="step"><div class="n">!</div><div><h4>Why the traps catch people</h4><p class="muted"><strong>C (2.70%)</strong> uses NPP alone (27 000) and forgets respiration — the classic slip. <strong>B (0.30%)</strong> divides only the 3 000 loss by sunlight. <strong>A (0.03%)</strong> is a power-of-ten error.</p></div></div>
            </div>
            <div class="finalcard">
                <div class="badge serif">D</div>
                <div>
                    <div style="font-weight:700; font-size:17px; color:var(--ink);">3.00% of incident sunlight is fixed by photosynthesis</div>
                    <div class="muted" style="font-size:13.5px; margin-top:2px;">Reality check — photosynthetic efficiency really is this low (~1–3%): most light is reflected, the wrong wavelength, transmitted, or misses the chloroplasts. ✓</div>
                </div>
            </div>
        </div>
    </section>

    <!-- FLASHCARDS -->
    <section class="panel" id="p-flashcards"><div class="card"><div class="eyebrow">Flashcards · tap to flip</div><h2 class="serif" style="margin-bottom:16px;">Self-test the core ideas</h2><div class="grid2" id="flashGrid"></div></div></section>
    <!-- MEMCARDS -->
    <section class="panel" id="p-memcards"><div class="card"><div class="eyebrow">Memcards · commit to memory</div><h2 class="serif" style="margin-bottom:16px;">The facts &amp; formulae behind it</h2><div class="grid2" id="memGrid"></div></div></section>

    <!-- MERMAID -->
    <section class="panel" id="p-mermaid">
        <div class="card"><div class="eyebrow">Flow · where the sunlight goes</div><h2 class="serif" style="margin-bottom:16px;">The energy as a flow</h2><div class="mermaid-box"><pre class="mermaid" id="mm1">
flowchart TD
    S["Sunlight 1 x 10^6 kJ"] --> A{Captured by chlorophyll?}
    A -->|"No, ~97% reflected / wrong wavelength / transmitted"| L[Energy not used]
    A -->|"Yes, 3% fixed"| G["GPP = 30 000 kJ (photosynthesis)"]
    G --> R["Respiration 3 000 kJ"]
    G --> N["NPP stored 27 000 kJ"]
    N --> H[Herbivore]
    H --> C[Carnivore]
        </pre></div></div>
        <div class="card"><div class="eyebrow">Flow · the concept</div><h2 class="serif" style="margin-bottom:16px;">GPP, NPP &amp; the productivity equation</h2><div class="mermaid-box"><pre class="mermaid" id="mm2">
graph LR
    GPP([Gross Primary Productivity]) --> P[Total energy fixed]
    R[Respiration] --> EQ{{NPP = GPP - R}}
    GPP --> EQ
    NPP[Net Primary Productivity] --> EQ
    NPP --> T["Passed on to next level (~10%)"]
    P --> EFF["Efficiency = GPP / sunlight ~ 1-3%"]
        </pre></div></div>
    </section>

    <!-- JSON -->
    <section class="panel" id="p-json"><div class="card"><div class="eyebrow">JSON · structured model</div><button class="copy" onclick="copyJson()">⧉ Copy</button><h2 class="serif" style="margin-bottom:16px;">Machine-readable question object</h2><div class="json-box" id="jsonOut"></div></div></section>
</div>

<script>
/* ═══════════ DATA ═══════════ */
const DATA={
    id:"9700_w15_qp_12_q39",
    paper:{code:"9700",level:"A Level",subject:"Biology",session:"2015 Oct/Nov",variant:"qp_12",question:39,type:"mcq"},
    topic:{unit:"Energy & ecosystems",subtopic:"Energy flow & productivity",skills:["gross vs net primary productivity","energy budget","trophic efficiency"]},
    given:{incident_sunlight_kJ_m2_y:1e6,npp_stored_kJ_m2_y:27000,respiration_loss_kJ_m2_y:3000,units:"kJ m^-2 y^-1"},
    stem:"1 x 10^6 kJ/m2/y of sunlight falls on green plants which store 27 000 and lose 3 000 in respiration. What percentage of energy is used for photosynthesis?",
    options:[
        {label:"A",value_pct:0.03,error:"power-of-ten slip"},
        {label:"B",value_pct:0.30,error:"used the 3 000 loss only"},
        {label:"C",value_pct:2.70,error:"used NPP only; forgot to add respiration"},
        {label:"D",value_pct:3.00,error:null}
    ],
    answer:"D",
    model:{key_idea:"energy used for photosynthesis = GPP",formula_gpp:"GPP = NPP + R",formula_pct:"% = GPP / sunlight * 100"},
    solution:{gpp:{equation:"27000 + 3000 = 30000",value_kJ_m2_y:30000},percentage:{equation:"30000 / 1e6 * 100",value_pct:3.00},note:"Add respiration back: it was fixed by photosynthesis first, then lost."}
};
const FLASH=[
    {q:"What does \"energy used for photosynthesis\" mean?",a:"Gross Primary Productivity (GPP) — the TOTAL chemical energy fixed by the plant before any is respired."},
    {q:"Formula linking GPP, NPP and respiration?",a:"GPP = NPP + R. Energy fixed = energy stored + energy respired."},
    {q:"Plug in the numbers for GPP.",a:"GPP = 27 000 + 3 000 = 30 000 kJ m⁻²y⁻¹."},
    {q:"Now the percentage of sunlight?",a:"30 000 ÷ (1 × 10⁶) × 100 = 3.00% → option D."},
    {q:"Why is C (2.70%) wrong?",a:"It uses NPP (27 000) alone and forgets to add back the 3 000 lost to respiration."},
    {q:"Why is photosynthetic efficiency only ~3%?",a:"Most sunlight is reflected, is the wrong wavelength, passes through the leaf, or misses the chloroplasts."}
];
const MEM=[
    {tone:"",k:"Definition",t:"Gross Primary Productivity",d:"Total rate at which producers fix chemical energy by photosynthesis.",f:"GPP = total energy fixed"},
    {tone:"green",k:"Definition",t:"Net Primary Productivity",d:"Energy left in the plant after respiration — available to consumers.",f:"NPP = GPP − R"},
    {tone:"",k:"Key move",t:"Add respiration back",d:"Respired energy was fixed by photosynthesis first, so it counts toward GPP.",f:"GPP = NPP + R"},
    {tone:"gold",k:"Efficiency",t:"Low light-use efficiency",d:"Only ~1–3% of incident sunlight is fixed: reflection, wrong wavelength, transmission, missed chloroplasts.",f:"GPP / sunlight ≈ 1–3%"},
    {tone:"green",k:"Trophic rule",t:"~10% transfer",d:"Roughly 10% of energy passes to the next trophic level; the rest is lost.",f:"≈ 10% per level"},
    {tone:"gold",k:"Units",t:"Per area per time",d:"Productivity is a rate per unit area — watch the units in every term.",f:"kJ m⁻² y⁻¹"}
];

const $=id=>document.getElementById(id);
$('optList').innerHTML=DATA.options.map(o=>`<div class="opt ${o.label===DATA.answer?'correct':''}"><div class="k">${o.label}</div><div class="pct">${o.value_pct.toFixed(2)}%</div><div class="why">${o.label===DATA.answer?'✓ correct — GPP ÷ sunlight':o.error}</div></div>`).join('');
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
    if(b.dataset.tab==='interactive'){ FLOW.start(); requestAnimationFrame(()=>FLOW.resize()); } else { FLOW.stop(); }
    if(window.MathJax&&window.MathJax.typesetPromise) window.MathJax.typesetPromise();
}
document.querySelectorAll('.tab').forEach(t=>t.addEventListener('click',()=>activateTab(t)));
window.addEventListener('load',()=>moveInd(document.querySelector('.tab.active')));
window.addEventListener('resize',()=>{const a=document.querySelector('.tab.active'); if(a) moveInd(a); FLOW.resize();});

/* ═══════════ MERMAID ═══════════ */
let mmReady=false,mmDone=false,MM=null;
import('https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.esm.min.mjs').then(m=>{MM=m.default; initMermaid(); mmReady=true; if($('p-mermaid').classList.contains('show')) renderMermaid();});
function themeVars(){const s=getComputedStyle(document.documentElement),g=(n,f)=>(s.getPropertyValue(n).trim()||f);return{primaryColor:g('--bg-3','#0B1D15'),primaryBorderColor:g('--em','#34D399'),primaryTextColor:g('--ink','#EAF6EE'),lineColor:g('--ink-soft','#A6C4B3'),secondaryColor:g('--bg-2','#08150F'),tertiaryColor:g('--panel','#111')};}
function initMermaid(){ if(!MM)return; MM.initialize({startOnLoad:false,theme:'base',themeVariables:{fontFamily:'Inter, sans-serif',...themeVars()}}); }
function renderMermaid(){ if(!mmReady||mmDone) return; mmDone=true; MM.run({nodes:document.querySelectorAll('.mermaid')}); }
document.querySelectorAll('.mermaid').forEach(n=>n.dataset.orig=n.textContent);

/* ═══════════ CANVAS ENERGY-FLOW SIMULATOR ═══════════ */
const FLOW=(()=>{
    const cv=$('flowCanvas'), ctx=cv.getContext('2d');
    const host=$('stage');
    const W=1000, H=625;                       // logical design space
    let scale=1, ox=0, oy=0, dpr=1, raf=null, t0=0;
    let state={sun:1e6, npp:27000, resp:3000};

    // css var helper
    const css=(n,f)=>{const v=getComputedStyle(document.documentElement).getPropertyValue(n).trim(); return v||f;};

    /* node geometry (logical) */
    const SUN={x:120,y:120,r:52};
    const LEAF={x:392,y:330};
    const GPP={x:566,y:330};
    const RESP={x:566,y:120};
    const NPP={x:770,y:330};
    const HERB={x:770,y:330};
    const CARN={x:922,y:330};
    const LOST={x:300,y:565};

    /* flows: centerline as quadratic bezier (p0, control, p1) + energy accessor + color key + particle store */
    function q(p0,c,p1){return {p0,c,p1};}
    const flows=[
        {key:'incident', geo:q({x:158,y:150},{x:270,y:250},{x:LEAF.x-20,y:LEAF.y-10}), col:()=>css('--gold','#FBBF24'), e:s=>s.sun,       max:2e6},
        {key:'lost',     geo:q({x:LEAF.x-6,y:LEAF.y+18},{x:360,y:470},{x:LOST.x,y:LOST.y-16}), col:()=>css('--slate','#7C93A8'), e:s=>Math.max(0,s.sun-(s.npp+s.resp)), max:2e6},
        {key:'gpp',      geo:q({x:LEAF.x+20,y:LEAF.y},{x:479,y:330},{x:GPP.x-30,y:GPP.y}), col:()=>css('--em','#34D399'),  e:s=>s.npp+s.resp, max:80000},
        {key:'resp',     geo:q({x:GPP.x,y:GPP.y-24},{x:566,y:230},{x:RESP.x,y:RESP.y+26}), col:()=>css('--gold','#FBBF24'), e:s=>s.resp, max:80000},
        {key:'npp',      geo:q({x:GPP.x+22,y:GPP.y},{x:668,y:330},{x:NPP.x-46,y:NPP.y}), col:()=>css('--green','#22C55E'), e:s=>s.npp, max:80000},
        {key:'chain',    geo:q({x:HERB.x+46,y:HERB.y},{x:846,y:330},{x:CARN.x-42,y:CARN.y}), col:()=>css('--green','#22C55E'), e:s=>s.npp*0.1, max:80000},
    ];
    // particle bank per flow
    flows.forEach(f=>{ f.parts=Array.from({length:26},()=>Math.random()); });

    function bez(g,t){ const u=1-t; return { x:u*u*g.p0.x+2*u*t*g.c.x+t*t*g.p1.x, y:u*u*g.p0.y+2*u*t*g.c.y+t*t*g.p1.y }; }
    function widthOf(f){ // logical px width of ribbon, min-clamped for visibility
        const e=f.e(state); if(e<=0) return 0;
        const w=Math.sqrt(e/f.max)*54;                 // sqrt keeps big flows from dominating
        const minW = (f.key==='lost') ? 6 : 11;         // GPP/NPP/resp stay visible even when tiny vs sunlight
        return Math.max(minW, Math.min(58, w));
    }

    function roundRect(x,y,w,h,r){ ctx.beginPath(); ctx.moveTo(x+r,y); ctx.arcTo(x+w,y,x+w,y+h,r); ctx.arcTo(x+w,y+h,x,y+h,r); ctx.arcTo(x,y+h,x,y,r); ctx.arcTo(x,y,x+w,y,r); ctx.closePath(); }

    function drawRibbon(f){
        const w=widthOf(f); if(w<=0) return; const g=f.geo, col=f.col();
        // soft glow underlay
        ctx.save(); ctx.globalAlpha=.22; ctx.lineWidth=w+10; ctx.strokeStyle=col; ctx.lineCap='round'; ctx.lineJoin='round';
        ctx.beginPath(); ctx.moveTo(g.p0.x,g.p0.y); ctx.quadraticCurveTo(g.c.x,g.c.y,g.p1.x,g.p1.y); ctx.stroke(); ctx.restore();
        // ribbon body
        ctx.save(); ctx.globalAlpha=(f.key==='lost')?.28:.55; ctx.lineWidth=w; ctx.strokeStyle=col; ctx.lineCap='round'; ctx.lineJoin='round';
        ctx.beginPath(); ctx.moveTo(g.p0.x,g.p0.y); ctx.quadraticCurveTo(g.c.x,g.c.y,g.p1.x,g.p1.y); ctx.stroke(); ctx.restore();
    }
    function drawParticles(f,dt){
        const w=widthOf(f); if(w<=0) return; const g=f.geo, col=f.col();
        const e=f.e(state); const speed=0.10+Math.min(.5,(e/f.max)*1.4);   // faster = more energy
        const count = Math.max(4, Math.min(26, Math.round(6+ (Math.sqrt(e/f.max))*22)));
        ctx.save(); ctx.fillStyle=col; ctx.shadowColor=col; ctx.shadowBlur=8;
        for(let i=0;i<count;i++){
            f.parts[i]=(f.parts[i]+speed*dt)%1;
            const t=f.parts[i], p=bez(g,t);
            // slight perpendicular jitter within ribbon
            const off=((i%5)-2)/2 * (w*0.22);
            const p2=bez(g,Math.min(1,t+0.01)); const ang=Math.atan2(p2.y-p.y,p2.x-p.x)+Math.PI/2;
            const px=p.x+Math.cos(ang)*off, py=p.y+Math.sin(ang)*off;
            const r=Math.max(1.4, w*0.11)*(0.7+0.5*Math.sin((t+i)*6.28));
            ctx.globalAlpha=(f.key==='lost')?.5:.95;
            ctx.beginPath(); ctx.arc(px,py,r,0,6.283); ctx.fill();
        }
        ctx.restore();
    }

    function label(x,y,title,sub,col,align){
        ctx.save(); ctx.textAlign=align||'center'; ctx.textBaseline='middle';
        ctx.fillStyle=col; ctx.font='700 15px Inter, sans-serif'; ctx.fillText(title,x,y);
        if(sub){ ctx.fillStyle=css('--ink-soft','#A6C4B3'); ctx.font='600 12.5px "JetBrains Mono", monospace'; ctx.fillText(sub,x,y+18); }
        ctx.restore();
    }
    function pill(x,y,w,h,text,col,fill){
        ctx.save(); roundRect(x-w/2,y-h/2,w,h,h/2); ctx.fillStyle=fill; ctx.fill(); ctx.lineWidth=1.5; ctx.strokeStyle=col; ctx.stroke();
        ctx.fillStyle=col; ctx.font='700 13px Inter, sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillText(text,x,y); ctx.restore();
    }

    function drawSun(time){
        const c=css('--gold','#FBBF24');
        ctx.save(); ctx.translate(SUN.x,SUN.y);
        // rays
        ctx.strokeStyle=c; ctx.lineCap='round';
        for(let i=0;i<12;i++){ const a=i/12*6.283+time*0.0004; const s=Math.sin(time*0.003+i)*0.5+0.5; ctx.globalAlpha=.25+s*.4; ctx.lineWidth=3; ctx.beginPath(); ctx.moveTo(Math.cos(a)*(SUN.r+8),Math.sin(a)*(SUN.r+8)); ctx.lineTo(Math.cos(a)*(SUN.r+8+10+s*8),Math.sin(a)*(SUN.r+8+10+s*8)); ctx.stroke(); }
        ctx.globalAlpha=1;
        const grd=ctx.createRadialGradient(0,0,4,0,0,SUN.r); grd.addColorStop(0,'#fff7dc'); grd.addColorStop(.5,c); grd.addColorStop(1,css('--gold-deep','#D97706'));
        ctx.fillStyle=grd; ctx.beginPath(); ctx.arc(0,0,SUN.r,0,6.283); ctx.fill();
        ctx.restore();
    }
    function drawLeaf(){
        const em=css('--em','#34D399'), gd=css('--em-deep','#059669');
        const eff=state.sun>0?((state.npp+state.resp)/state.sun):0;
        const vivid=Math.max(.4,Math.min(1,eff/0.05));
        ctx.save(); ctx.translate(LEAF.x,LEAF.y);
        // glow
        ctx.globalAlpha=.5*vivid; ctx.shadowColor=em; ctx.shadowBlur=30;
        const g=ctx.createLinearGradient(-40,-40,40,40); g.addColorStop(0,em); g.addColorStop(1,gd);
        ctx.fillStyle=g; ctx.globalAlpha=1;
        // two-lobe leaf
        ctx.beginPath(); ctx.moveTo(0,34); ctx.quadraticCurveTo(-46,6,-30,-40); ctx.quadraticCurveTo(-6,-16,0,26); ctx.quadraticCurveTo(6,-16,30,-40); ctx.quadraticCurveTo(46,6,0,34); ctx.closePath(); ctx.fill();
        ctx.strokeStyle='rgba(4,21,13,.5)'; ctx.lineWidth=1.5; ctx.beginPath(); ctx.moveTo(0,32); ctx.lineTo(0,-30); ctx.stroke();
        ctx.restore();
    }

    let last=0;
    function frame(time){
        if(!last) last=time; let dt=Math.min(0.05,(time-last)/1000)*6; last=time;
        ctx.setTransform(dpr,0,0,dpr,0,0);
        ctx.clearRect(0,0,cv.width,cv.height);
        ctx.setTransform(dpr*scale,0,0,dpr*scale,ox*dpr,oy*dpr);

        // ribbons behind, particles on top
        flows.forEach(drawRibbon);
        flows.forEach(f=>drawParticles(f,dt));

        drawSun(time); drawLeaf();

        // nodes / labels
        const em=css('--em','#34D399'), green=css('--green','#22C55E'), gold=css('--gold','#FBBF24'), slate=css('--slate','#7C93A8'), ink=css('--ink','#EAF6EE');
        const eff=state.sun>0?((state.npp+state.resp)/state.sun*100):0;
        label(SUN.x, SUN.y+SUN.r+26, 'sunlight', fmt(state.sun)+' kJ', gold);
        pill(GPP.x, GPP.y, 150, 40, 'GPP '+fmt(state.npp+state.resp), em, 'rgba(52,211,153,.14)');
        label(GPP.x, GPP.y+40, '', eff.toFixed(2)+'% fixed', em);
        pill(RESP.x, RESP.y, 168, 38, 'respiration '+fmt(state.resp), gold, 'rgba(251,191,36,.12)');
        pill(NPP.x, NPP.y-58, 156, 38, 'NPP '+fmt(state.npp), green, 'rgba(34,197,94,.12)');
        // herbivore/carnivore nodes
        drawCritter(HERB.x, HERB.y, green, 'herbivore');
        drawCritter(CARN.x, CARN.y, css('--ink-soft','#A6C4B3'), 'carnivore');
        label(LOST.x, LOST.y+6, 'not captured', '~'+(100-eff).toFixed(1)+'% reflected / wrong λ / transmitted', slate);

        raf=requestAnimationFrame(frame);
    }
    function drawCritter(x,y,col,name){
        ctx.save(); ctx.translate(x,y);
        ctx.fillStyle=col; ctx.globalAlpha=.9;
        roundRect(-15,-10,30,22,8); ctx.fill();
        ctx.beginPath(); ctx.arc(13,-8,7,0,6.283); ctx.fill();
        ctx.globalAlpha=1; ctx.fillStyle=css('--ink-soft','#A6C4B3'); ctx.font='600 12px Inter, sans-serif'; ctx.textAlign='center'; ctx.fillText(name,0,34);
        ctx.restore();
    }

    function resize(){
        const w=host.clientWidth, h=host.clientHeight; if(!w||!h) return;
        dpr=Math.min(window.devicePixelRatio||1,2);
        cv.width=w*dpr; cv.height=h*dpr; cv.style.width=w+'px'; cv.style.height=h+'px';
        scale=Math.min(w/W, h/H); ox=(w-W*scale)/2; oy=(h-H*scale)/2;
    }
    function start(){ if(raf) return; resize(); last=0; raf=requestAnimationFrame(frame); }
    function stop(){ if(raf){ cancelAnimationFrame(raf); raf=null; } }
    function set(s){ state=s; }
    return { start, stop, resize, set };
})();

/* ═══════════ shared UI + sliders ═══════════ */
function fmt(n){ return Math.round(n).toLocaleString('en-US').replace(/,/g,' '); }
function updateUI(){
    const sun=+$('sSun').value, npp=+$('sNpp').value, resp=+$('sResp').value;
    const gpp=npp+resp, eff=sun>0?(gpp/sun*100):0;
    FLOW.set({sun,npp,resp});
    $('vSun').textContent=fmt(sun); $('vNpp').textContent=fmt(npp); $('vResp').textContent=fmt(resp);
    $('rNppE').textContent=fmt(npp); $('rRespE').textContent=fmt(resp);
    $('rGpp').textContent=fmt(gpp); $('rEff').innerHTML=eff.toFixed(2)+'<small>%</small>';
    $('sSun').style.setProperty('--fill',((sun-200000)/1800000*100)+'%');
    $('sNpp').style.setProperty('--fill',(npp/60000*100)+'%');
    $('sResp').style.setProperty('--fill',(resp/20000*100)+'%');
    const f=$('flag');
    if(sun===1e6 && npp===27000 && resp===3000){ f.className='flag ok'; f.innerHTML='✓ Exam values → GPP = 30 000 kJ, efficiency = <b>3.00%</b>. This is option <b>D</b>.'; }
    else if(Math.abs(eff-2.70)<0.01 && sun===1e6){ f.className='flag warn'; f.innerHTML='⚠ This is distractor <b>C (2.70%)</b> — you have left respiration out of GPP.'; }
    else{ f.className='flag neutral'; f.innerHTML=`Efficiency = <b>${eff.toFixed(2)}%</b>. Return to the exam values to land on the answer.`; }
}
function resetExam(){ $('sSun').value=1000000; $('sNpp').value=27000; $('sResp').value=3000; updateUI(); }
['sSun','sNpp','sResp'].forEach(id=>$(id).addEventListener('input',updateUI));
updateUI();

/* ═══════════ MODE ═══════════ */
function toggleMode(){
    const h=document.documentElement, dark=h.getAttribute('data-mode')==='dark';
    h.setAttribute('data-mode', dark?'light':'dark');
    $('modeIco').textContent=dark?'🌙':'☀️'; $('modeTxt').textContent=dark?'Dark':'Light';
    if(MM){ mmDone=false; initMermaid(); if($('p-mermaid').classList.contains('show')){ document.querySelectorAll('.mermaid').forEach(n=>{ if(n.dataset.orig){ n.removeAttribute('data-processed'); n.innerHTML=n.dataset.orig; } }); renderMermaid(); } }
}
</script>
@endverbatim
</body>
</html>
