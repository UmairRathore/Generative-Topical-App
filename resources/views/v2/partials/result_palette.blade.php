{{-- Sticky sidebar: result summary + numbered question palette.
     Clicking a number scrolls to the question; the active question is tracked
     while scrolling. Props: $palette (from ExamService::resultBreakdown), $breakdown. --}}
<div class="result-aside">
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
        <div style="font-size: 13px; font-weight: 600; margin-bottom: 12px;">Result summary</div>

        <div class="space-y-2" style="margin-bottom: 14px;">
            @php
                $rows = [
                    ['Correct', $breakdown['correct'], 'var(--ok)'],
                    ['Wrong', $breakdown['wrong'], 'var(--bad)'],
                    ['Unattempted', $breakdown['unattempted'], 'var(--text-soft)'],
                ];
                if (! empty($breakdown['voided'])) {
                    $rows[] = ['Excluded', $breakdown['voided'], 'var(--text-faint)'];
                }
            @endphp
            @foreach ($rows as [$label, $val, $color])
                <div class="flex items-center justify-between" style="font-size: 12.5px;">
                    <span class="flex items-center gap-2"><span style="width: 9px; height: 9px; border-radius: 50%; background: {{ $color }}; display: inline-block;"></span>{{ $label }}</span>
                    <strong>{{ $val }}</strong>
                </div>
            @endforeach
        </div>

        <div style="font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-faint); margin-bottom: 9px;">Questions</div>
        <div class="result-palette-grid">
            @foreach ($palette as $p)
                <a href="#q{{ $p['n'] }}" data-qnav="{{ $p['n'] }}" class="qp qp--{{ $p['state'] }} {{ $p['flagged'] ? 'is-flagged' : '' }}">{{ $p['n'] }}</a>
            @endforeach
        </div>
    </div>
</div>

@once
<style>
    .result-shell{max-width:1180px;margin:0 auto;}
    .result-grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:24px;align-items:start;}
    .result-aside{position:sticky;top:84px;}
    .result-palette-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:6px;}
    .qp{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1px solid var(--border);font-size:13px;font-weight:600;text-decoration:none;color:var(--text);cursor:pointer;transition:box-shadow .12s;}
    .qp--correct{background:var(--ok-soft);color:#15803d;border-color:rgba(95,160,82,.4);}
    .qp--wrong{background:var(--bad-soft);color:#b91c1c;border-color:rgba(200,60,60,.4);}
    .qp--unattempted{background:var(--soft-surface);color:var(--text-soft);}
    .qp--voided{opacity:.45;}
    .qp.is-flagged{box-shadow:0 0 0 2px var(--warn);}
    .qp.is-active{box-shadow:0 0 0 2px var(--accent);}
    .qp.is-flagged.is-active{box-shadow:0 0 0 2px var(--accent),0 0 0 4px var(--warn);}
    @media (max-width:900px){
        .result-grid{grid-template-columns:1fr;}
        .result-aside{position:static;order:-1;}
        .result-palette-grid{display:flex;overflow-x:auto;gap:6px;padding-bottom:6px;-webkit-overflow-scrolling:touch;}
        .result-palette-grid .qp{flex:0 0 auto;}
    }
</style>
<script>
(function () {
    function init() {
        var navs = document.querySelectorAll('[data-qnav]');
        if (!navs.length) return;
        var byNum = {};
        navs.forEach(function (n) { byNum[n.getAttribute('data-qnav')] = n; });

        function setActive(num) {
            navs.forEach(function (n) { n.classList.remove('is-active'); });
            var el = byNum[num];
            if (el) { el.classList.add('is-active'); el.scrollIntoView({block: 'nearest', inline: 'nearest'}); }
        }

        navs.forEach(function (n) {
            n.addEventListener('click', function (e) {
                e.preventDefault();
                var num = n.getAttribute('data-qnav');
                var card = document.getElementById('q' + num);
                if (card) card.scrollIntoView({behavior: 'smooth', block: 'start'});
                setActive(num);
            });
        });

        var cards = document.querySelectorAll('[data-result-question-card]');
        if (!cards.length) return;
        var obs = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) setActive(en.target.getAttribute('data-question-number'));
            });
        }, {rootMargin: '-45% 0px -50% 0px', threshold: 0});
        cards.forEach(function (c) { obs.observe(c); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
</script>
@endonce
