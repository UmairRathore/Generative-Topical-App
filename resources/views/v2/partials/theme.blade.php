{{--
    ╔══════════════════════════════════════════════════════════════════════╗
    ║  V2 THEME CONTROL PANEL — the one file to change the app's colors.     ║
    ║  Edit here, refresh the page. No build step. Scoped to V2 (V1 untouched).║
    ╠══════════════════════════════════════════════════════════════════════╣
    ║  • Switch the whole app:  change $default below to any palette key.     ║
    ║  • Preview any palette:   add ?theme=NAME to any V2 URL                 ║
    ║                           (e.g. ...?theme=heritage).                    ║
    ║  • Add a new theme:       add one entry to $themes. Every colour is      ║
    ║                           optional except primary/accent/bg — anything  ║
    ║                           omitted is derived. Recognised role keys:      ║
    ║                             sidebar, primary, hover, accent, bg, card,   ║
    ║                             border, ok (correct), bad (incorrect),       ║
    ║                             warn, secondary, success, darkBg             ║
    ╚══════════════════════════════════════════════════════════════════════╝
--}}
@php
    // ── 1. ACTIVE THEME ──────────────────────────────────────────────────
    $default = 'instructure';                    // ← change this to switch the app
    // ── 2. PALETTES ──────────────────────────────────────────────────────
    $themes = [
        'instructure' => [            // instructure.com — deep navy primary + brand blue
            'sidebar' => '#061C30',   // primary navy
            'primary' => '#061C30',   // primary navy — buttons/cards (white text on it)
            'hover'   => '#0E2D45',   // primary hover (lighter navy)
            'accent'  => '#0097D3',   // brand blue (highlights / active nav / links)
            'ink'     => '#061C30',   // body text on white = primary navy
            'bg'      => '#F2F4F7',   // light cool-gray page (so white cards lift off it)
            'card'    => '#FFFFFF',   // white cards
            'border'  => '#E2E4EA',   // cool gray border (separates white on white)
            'ok'      => '#5FA052',   // green (correct)
            'bad'     => '#FF0000',   // contrast red (incorrect / errors)
            'darkBg'  => '#0A1A28',   // deep navy dark mode
        ],
        'vellum' => [                 // vibrant + old-book parchment
            'sidebar' => '#233140',   // rich navy-slate
            'primary' => '#356CA6',   // vibrant Oxford blue
            'hover'   => '#2A5887',   // primary button hover
            'accent'  => '#DDA63B',   // vibrant gold
            'bg'      => '#F4ECDB',   // crisp old-book parchment
            'card'    => '#FFFFFF',   // crisp white cards
            'border'  => '#E6DCC6',   // warm parchment border
            'ok'      => '#5BA06B',   // vibrant green (correct)
            'bad'     => '#C64C44',   // vibrant brick-red (incorrect)
            'darkBg'  => '#18222E',   // dark-mode background
        ],
        'oxford' => [
            'sidebar' => '#27313A',   // sidebar background
            'primary' => '#446B8A',   // primary buttons
            'hover'   => '#36566E',   // primary button hover
            'accent'  => '#C89A45',   // accent badges / active nav / highlights
            'bg'      => '#FAF8F3',   // page background
            'card'    => '#FFFFFF',   // cards / inputs
            'border'  => '#E7DFD1',   // borders
            'ok'      => '#71826B',   // correct / good performance
            'bad'     => '#B35D55',   // incorrect / poor performance
            'darkBg'  => '#1A2128',   // dark-mode background
        ],
        'heritage' => [
            'primary' => '#313D4B', 'accent' => '#CD5554', 'secondary' => '#91684A',
            'success' => '#00C07F', 'bg' => '#F7F3EE', 'darkBg' => '#1C2530',
        ],
        'emerald' => [               // the original brand
            'primary' => '#0B3D2E', 'accent' => '#D4A437', 'secondary' => '#91684A',
            'success' => '#16A34A', 'bg' => '#FAF7EF', 'darkBg' => '#0A1410',
        ],
        'midnight' => [
            'primary' => '#1E3A5F', 'accent' => '#E07A5F', 'secondary' => '#8A8FA3',
            'success' => '#3DBC91', 'bg' => '#F4F5F7', 'darkBg' => '#10151F',
        ],
    ];

    // ── 3. RESOLVE + DERIVE (no need to edit below) ──────────────────────
    $key = request('theme', $default);
    $t   = $themes[$key] ?? $themes[$default];

    $toRgb = fn ($h) => sscanf(ltrim($h, '#'), '%02x%02x%02x');
    $mix = function ($a, $b, $amt) use ($toRgb) {
        [$ar, $ag, $ab] = $toRgb($a);
        [$br, $bg, $bb] = $toRgb($b);
        return sprintf('#%02X%02X%02X',
            (int) round($ar + ($br - $ar) * $amt),
            (int) round($ag + ($bg - $ag) * $amt),
            (int) round($ab + ($bb - $ab) * $amt));
    };
    $lt = fn ($h, $a) => $mix($h, '#FFFFFF', $a);   // lighten toward white
    $dk = fn ($h, $a) => $mix($h, '#000000', $a);   // darken toward black

    // roles — explicit where given, otherwise derived
    $primary   = $t['primary'];
    $accent    = $t['accent'];
    $bg        = $t['bg'];
    $sidebar   = $t['sidebar']   ?? $dk($primary, 0.40);
    $hover     = $t['hover']     ?? $lt($primary, 0.10);
    $card      = $t['card']      ?? '#FFFFFF';
    $border    = $t['border']    ?? $dk($bg, 0.085);
    $ok        = $t['ok']        ?? ($t['success'] ?? '#00C07F');
    $bad       = $t['bad']       ?? '#D9433F';
    $warn      = $t['warn']      ?? '#D9952B';
    $secondary = $t['secondary'] ?? $sidebar;
    $darkBg    = $t['darkBg']    ?? $dk($primary, 0.55);
    $ink       = $t['ink']       ?? $dk($primary, 0.25);   // body text on light/white bg

    $sidebarDeep = $dk($sidebar, 0.18);
    $accentRgb   = implode(',', $toRgb($accent));

    $light = [
        '--sidebar'      => $sidebar,
        '--sidebar-deep' => $sidebarDeep,
        '--accent-rgb'   => $accentRgb,
        '--bg-rgb'       => implode(',', $toRgb($bg)),

        '--emerald-900' => $dk($primary, 0.35),
        '--emerald-800' => $primary,
        '--emerald-700' => $hover,
        '--emerald-600' => $lt($primary, 0.17),
        '--emerald-500' => $lt($primary, 0.28),
        '--emerald-50'  => $lt($primary, 0.92),

        '--gold-700' => $dk($accent, 0.21),
        '--gold-600' => $dk($accent, 0.10),
        '--gold-500' => $accent,
        '--gold-400' => $lt($accent, 0.22),
        '--gold-100' => $lt($accent, 0.78),
        '--gold-50'  => $lt($accent, 0.90),

        '--brick-700' => $dk($secondary, 0.22),
        '--brick-500' => $secondary,
        '--brick-100' => $lt($secondary, 0.74),

        '--ivory'        => $bg,
        '--ivory-deep'   => $dk($bg, 0.05),
        '--paper'        => $card,
        '--soft-surface' => $dk($bg, 0.025),
        '--border'       => $border,
        '--border-soft'  => $mix($border, $bg, 0.5),
        '--charcoal'     => $ink,

        '--accent'        => $accent,
        '--accent-soft'   => $lt($accent, 0.78),
        '--primary'       => $primary,
        '--primary-hover' => $hover,

        '--success'      => $ok,
        '--success-soft' => $lt($ok, 0.86),
        '--qa-pass'      => $ok,

        '--ok'    => $ok,           '--ok-soft'    => $lt($ok, 0.84),
        '--warn'  => $warn,         '--warn-soft'  => $lt($warn, 0.84),
        '--bad'   => $bad,          '--bad-soft'   => $lt($bad, 0.84),
        '--error' => $bad,          '--error-soft' => $lt($bad, 0.84),

        '--shadow-emerald' => '0 8px 24px rgba(' . implode(',', $toRgb($sidebar)) . ',0.18)',

        '--color-brand-emerald'   => $primary,
        '--color-brand-forest'    => $hover,
        '--color-brand-gold'      => $accent,
        '--color-brand-gold-soft' => $lt($accent, 0.22),
        '--color-brand-ivory'     => $bg,
        '--color-accent'          => $primary,
        '--color-accent-content'  => $primary,
        '--color-status-success'  => $ok,
    ];

    $dark = [
        '--ivory'        => $darkBg,
        '--ivory-deep'   => $dk($darkBg, 0.12),
        '--paper'        => $lt($darkBg, 0.05),
        '--soft-surface' => $dk($darkBg, 0.06),
        '--border'       => $lt($darkBg, 0.14),
        '--border-soft'  => $lt($darkBg, 0.09),

        '--bg'         => $darkBg,
        '--bg-rgb'     => implode(',', $toRgb($darkBg)),
        '--surface'    => $lt($darkBg, 0.05),
        '--text'       => $lt($darkBg, 0.88),
        '--text-soft'  => $lt($darkBg, 0.55),
        '--text-faint' => $lt($darkBg, 0.38),
        '--charcoal'   => $lt($darkBg, 0.88),

        '--slate-900' => $lt($darkBg, 0.88),
        '--slate-700' => $lt($darkBg, 0.66),
        '--slate-600' => $lt($darkBg, 0.48),
        '--slate-500' => $lt($darkBg, 0.38),
        '--slate-400' => $lt($darkBg, 0.30),
        '--slate-300' => $lt($darkBg, 0.18),
        '--slate-200' => $lt($darkBg, 0.08),
        '--slate-100' => $lt($darkBg, 0.04),

        '--primary'       => $lt($primary, 0.12),
        '--primary-hover' => $lt($primary, 0.22),
        '--emerald-800'   => $lt($primary, 0.06),
        '--emerald-700'   => $lt($primary, 0.16),
    ];

    // Public/marketing pages pass forceLight => neutralise dark mode by resetting
    // every dark-affected token back to its light value (CSS-only, no JS race).
    $darkOut = empty($forceLight) ? $dark : ($light + [
        '--bg' => $bg, '--surface' => $card,
        '--text' => $ink,
        '--text-soft' => '#475569', '--text-faint' => '#64748B',
        '--slate-900' => '#0F172A', '--slate-700' => '#334155', '--slate-600' => '#475569',
        '--slate-500' => '#64748B', '--slate-400' => '#94A3B8', '--slate-300' => '#CBD5E1',
        '--slate-200' => '#E2E8F0', '--slate-100' => '#F1F5F9',
    ]);
@endphp
<style>
    :root {
        @foreach ($light as $name => $value) {{ $name }}: {{ $value }};
        @endforeach
    }
    .dark,
    [data-theme="dark"] {
        @foreach ($darkOut as $name => $value) {{ $name }}: {{ $value }};
        @endforeach
    }

    /* Emphasis + highlights (accent). Base borders stay neutral; accent is
       used for focus rings, hovered/selected rows, and interactive emphasis. */
    .input:focus, .select:focus, .textarea:focus,
    input:focus, select:focus, textarea:focus {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 3px rgba(var(--accent-rgb), 0.20) !important;
        outline: none !important;
    }
    .tbl tbody tr:hover td { background: rgba(var(--accent-rgb), 0.06) !important; }
    .chip:hover { border-color: var(--accent); color: var(--accent); }
</style>
