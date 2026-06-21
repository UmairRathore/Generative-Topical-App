{{-- Chart.js loader + theme bridge. Include ONCE per page that draws charts.
     Colors are read live from the V2 CSS variables so every chart tracks the
     active theme (and light/dark) instead of hard-coding hex. --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
window.V2 = window.V2 || {};

V2.cssVar = function (name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
};

V2.theme = function () {
    return {
        primary: V2.cssVar('--primary', '#061C30'),
        accent:  V2.cssVar('--accent',  '#0097D3'),
        ok:      V2.cssVar('--ok',      '#5FA052'),
        warn:    V2.cssVar('--warn',    '#D9952B'),
        bad:     V2.cssVar('--bad',     '#C64C44'),
        text:    V2.cssVar('--text-soft',  '#475569'),
        faint:   V2.cssVar('--text-faint', '#64748B'),
        border:  V2.cssVar('--border',  '#E2E4EA'),
        surface: V2.cssVar('--surface', '#ffffff'),
    };
};

/* hex (#RGB or #RRGGBB) -> rgba() string */
V2.alpha = function (hex, a) {
    hex = (hex || '').replace('#', '');
    if (hex.length === 3) { hex = hex.split('').map(function (c) { return c + c; }).join(''); }
    var n = parseInt(hex, 16);
    if (isNaN(n) || hex.length !== 6) { return 'rgba(0,0,0,' + a + ')'; }
    return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
};

/* mastery colour: green >=70, amber 50-69, red <50 */
V2.tone = function (p) {
    var t = V2.theme();
    return p >= 70 ? t.ok : (p >= 50 ? t.warn : t.bad);
};

if (window.Chart) {
    Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = V2.theme().faint;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.maintainAspectRatio = false;
}
</script>
