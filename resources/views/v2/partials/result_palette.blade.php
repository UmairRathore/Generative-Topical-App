{{-- Sticky sidebar: result summary + numbered question palette.
     Clicking a number scrolls to the question; the active question is tracked
     while scrolling. Props: $palette (from ExamService::resultBreakdown), $breakdown. --}}
<div class="result-aside">
    <div style="background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); padding: 16px 18px;">
        {{-- Summary counts duplicate the score strip, so they are hidden on mobile (where the strip sits just above). --}}
        <div class="rp-summary">
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
    .result-aside{position:sticky;top:84px;max-height:calc(100vh - 100px);overflow:auto;}
    .result-palette-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(38px,1fr));gap:6px;justify-items:center;}
    .rs-strip{padding:22px 26px;}
    .ar-card{padding:20px;}
    .qp{display:flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;border:1px solid var(--border);font-size:13px;font-weight:600;text-decoration:none;color:var(--text);cursor:pointer;transition:box-shadow .12s;}
    .qp--correct{background:var(--ok-soft);color:#15803d;border-color:rgba(95,160,82,.4);}
    .qp--wrong{background:var(--bad-soft);color:#b91c1c;border-color:rgba(200,60,60,.4);}
    .qp--unattempted{background:var(--soft-surface);color:var(--text-soft);}
    .qp--voided{opacity:.45;}
    .qp.is-flagged{box-shadow:0 0 0 2px var(--warn);}
    .qp.is-active{box-shadow:0 0 0 2px var(--accent);}
    .qp.is-flagged.is-active{box-shadow:0 0 0 2px var(--accent),0 0 0 4px var(--warn);}
    @media (max-width:900px){
        /* minmax(0,1fr) so wide tables can't blow out the column; aside not sticky and
           height:auto so it doesn't stretch to a full-height empty block above the paper. */
        .result-grid{grid-template-columns:minmax(0,1fr);}
        .result-aside{position:static;order:-1;max-height:none;overflow:visible;height:auto;align-self:start;}
        .rp-summary{display:none;}
        /* palette wraps into rows (no horizontal scroll off-screen) - it already uses auto-fill. */
    }
    @media (max-width:600px){
        .rs-strip{padding:16px;}
        .rs-hero{gap:16px;}
        .ar-card{padding:15px 14px;}
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
            // Only highlight - do NOT scrollIntoView the chip. The palette wraps (and is sticky
            // on desktop) so chips are reachable; scrolling an off-screen chip into view would
            // yank the whole page back up to the palette.
            if (el) el.classList.add('is-active');
        }

        navs.forEach(function (n) {
            n.addEventListener('click', function () {
                // Let the native anchor (#qN) do the scroll: reliable in every browser, honours
                // scroll-margin-top, and never yanks the page. JS only updates the highlight.
                setActive(n.getAttribute('data-qnav'));
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
