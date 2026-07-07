<!DOCTYPE html>
<html lang="en" data-mode="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mistake Bank · gateway to the Learning Studio</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|fraunces:400,500,600,700" rel="stylesheet">
    @include('v2.partials.theme')
    <style>
        *{box-sizing:border-box;} html,body{margin:0;padding:0;}
        body{font-family:'Inter',system-ui,sans-serif;background:var(--paper,#F6F7F5);color:#111;min-height:100vh;-webkit-font-smoothing:antialiased;}
        .wrap{max-width:760px;margin:0 auto;padding:48px 22px 90px;}
        .crumb{font-size:12px;font-weight:600;letter-spacing:.04em;color:#6b7280;text-transform:uppercase;margin-bottom:18px;}
        .title{font-family:'Fraunces',serif;font-size:30px;font-weight:600;letter-spacing:-.015em;margin:0 0 6px;}
        .sub{color:#4b5563;font-size:15px;margin:0 0 30px;}
        /* a couple of stand-in "mistake" rows so the gateway sits in context */
        .row{display:flex;align-items:center;gap:14px;padding:15px 18px;border:1px solid #e5e7eb;border-radius:14px;background:#fff;margin-bottom:12px;}
        .row .dot{width:9px;height:9px;border-radius:50%;background:var(--bad,#ef4444);flex:none;}
        .row .q{font-weight:600;font-size:14.5px;}
        .row .m{color:#6b7280;font-size:13px;margin-top:2px;}
        .row .tag{margin-left:auto;font-size:11.5px;font-weight:700;color:#b45309;background:#fef3c7;border-radius:999px;padding:4px 11px;}
        /* the gateway card */
        .gate{margin-top:30px;position:relative;overflow:hidden;border-radius:20px;padding:28px;color:#EAF6EE;
              background:linear-gradient(135deg,#08160F,#0B2A1C);border:1px solid rgba(52,211,153,.25);
              box-shadow:0 24px 60px -30px rgba(5,150,105,.6);}
        .gate::before{content:"";position:absolute;inset:0;pointer-events:none;
              background:radial-gradient(520px 240px at 88% -20%,rgba(52,211,153,.25),transparent 60%);}
        .gate .eyebrow{position:relative;font-size:11px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#34D399;margin-bottom:10px;}
        .gate h2{position:relative;font-family:'Fraunces',serif;font-size:24px;font-weight:600;margin:0 0 8px;}
        .gate p{position:relative;color:#A6C4B3;font-size:14.5px;margin:0 0 22px;max-width:52ch;}
        .cta{position:relative;display:inline-flex;align-items:center;gap:10px;text-decoration:none;font-weight:700;font-size:15px;
             color:#04150d;background:linear-gradient(140deg,#34D399,#059669);border-radius:12px;padding:13px 22px;
             box-shadow:0 10px 30px -8px rgba(52,211,153,.6);transition:.16s;}
        .cta:hover{filter:brightness(1.06);transform:translateY(-1px);}
        .cta .arr{font-size:18px;line-height:1;}
        .note{position:relative;margin-top:16px;font-size:12.5px;color:#68877A;}
        .note code{font-family:ui-monospace,monospace;color:#34D399;}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="crumb">Mistake Bank · Livewire (existing design)</div>
        <h1 class="title">My Mistakes</h1>
        <p class="sub">This page stays exactly as it is — same UI as the rest of the site. It's the launchpad.</p>

        <div class="row"><span class="dot"></span><div><div class="q">Moles in 8.0 g of NaOH</div><div class="m">Chemistry · you chose 5.0 mol</div></div><span class="tag">Not yet mastered</span></div>
        <div class="row"><span class="dot"></span><div><div class="q">Energy from a 60 W lamp in 5 min</div><div class="m">Physics · you chose 300 J</div></div><span class="tag">Review due</span></div>
        <div class="row"><span class="dot"></span><div><div class="q">Magnification of a photomicrograph</div><div class="m">Biology · you chose × 5000</div></div><span class="tag">Not yet mastered</span></div>

        <div class="gate">
            <div class="eyebrow">Ready to actually learn from these?</div>
            <h2>Enter the Learning Studio</h2>
            <p>Interactive solutions, live diagrams, flashcards and (soon) an AI tutor — the React-powered learning section, built around the mistakes above.</p>
            <a class="cta" href="{{ route('v2.learn.home') }}"><span>Enter the Learning Studio</span><span class="arr">→</span></a>
            <div class="note">Gateway from Livewire → Inertia/React. In production this button lives on the real Mistake Bank view. Target route: <code>v2.learn.home</code></div>
        </div>
    </div>
</body>
</html>
