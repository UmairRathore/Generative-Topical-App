<?php

namespace App\Support;

/*
|--------------------------------------------------------------------------
| SciText - render-time scientific notation for CAIE question text
|--------------------------------------------------------------------------
| Turns the plain extracted text ("H2O", "mol dm-3", "Na+") into proper
| sub/superscript HTML ("H₂O" via <sub>, "mol dm⁻³" via <sup>) WITHOUT
| touching the stored data. It is deliberately CONSERVATIVE: it only acts on
| anchored patterns - a digit right after a real element symbol, an exponent
| right after a known unit, a sign at a species boundary, or a curated common
| ion. Anything it is not sure about (coefficients like "2H2O", "Figure 2",
| "Period 3", bare numbers) is left exactly as-is. The toggle lives in the
| sci() helper / config('v2.sci_format'); when off, callers get plain escaped
| text identical to the old `{{ }}` output.
*/
class SciText
{
    /** Element symbols, used to anchor formula subscripts (so "Figure 2" is never touched). */
    private const ELEMENTS = 'H He Li Be B C N O F Ne Na Mg Al Si P S Cl Ar K Ca Sc Ti V Cr Mn Fe Co Ni Cu Zn Ga Ge As Se Br Kr Rb Sr Y Zr Nb Mo Tc Ru Rh Pd Ag Cd In Sn Sb Te I Xe Cs Ba La Ce Pr Nd Pm Sm Eu Gd Tb Dy Ho Er Tm Yb Lu Hf Ta W Re Os Ir Pt Au Hg Tl Pb Bi Po At Rn Fr Ra Ac Th Pa U Np Pu';

    /** Common ions where a regex cannot tell the subscript count from the charge magnitude. */
    private const IONS = [
        'H+' => 'H<sup>+</sup>', 'Na+' => 'Na<sup>+</sup>', 'K+' => 'K<sup>+</sup>',
        'Li+' => 'Li<sup>+</sup>', 'Ag+' => 'Ag<sup>+</sup>', 'Cu+' => 'Cu<sup>+</sup>',
        'NH4+' => 'NH<sub>4</sub><sup>+</sup>', 'H3O+' => 'H<sub>3</sub>O<sup>+</sup>',
        'OH-' => 'OH<sup>-</sup>', 'Cl-' => 'Cl<sup>-</sup>', 'Br-' => 'Br<sup>-</sup>',
        'I-' => 'I<sup>-</sup>', 'F-' => 'F<sup>-</sup>',
        'NO3-' => 'NO<sub>3</sub><sup>-</sup>', 'NO2-' => 'NO<sub>2</sub><sup>-</sup>',
        'HCO3-' => 'HCO<sub>3</sub><sup>-</sup>', 'MnO4-' => 'MnO<sub>4</sub><sup>-</sup>',
        'Mg2+' => 'Mg<sup>2+</sup>', 'Ca2+' => 'Ca<sup>2+</sup>', 'Ba2+' => 'Ba<sup>2+</sup>',
        'Zn2+' => 'Zn<sup>2+</sup>', 'Cu2+' => 'Cu<sup>2+</sup>', 'Fe2+' => 'Fe<sup>2+</sup>',
        'Pb2+' => 'Pb<sup>2+</sup>', 'Sn2+' => 'Sn<sup>2+</sup>', 'Mn2+' => 'Mn<sup>2+</sup>',
        'Fe3+' => 'Fe<sup>3+</sup>', 'Al3+' => 'Al<sup>3+</sup>', 'Cr3+' => 'Cr<sup>3+</sup>',
        'SO42-' => 'SO<sub>4</sub><sup>2-</sup>', 'SO32-' => 'SO<sub>3</sub><sup>2-</sup>',
        'CO32-' => 'CO<sub>3</sub><sup>2-</sup>', 'CrO42-' => 'CrO<sub>4</sub><sup>2-</sup>',
        'Cr2O72-' => 'Cr<sub>2</sub>O<sub>7</sub><sup>2-</sup>', 'PO43-' => 'PO<sub>4</sub><sup>3-</sup>',
        'S2-' => 'S<sup>2-</sup>', 'O2-' => 'O<sup>2-</sup>',
    ];

    /** Escape for HTML text content (not attributes), without touching quotes (no stray digits). */
    public static function escape(?string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], (string) $text);
    }

    public static function format(?string $text): string
    {
        $s = self::escape($text);
        if ($s === '') {
            return $s;
        }

        $s = self::ions($s);
        $s = self::units($s);
        $s = self::subscripts($s);
        $s = self::charges($s);

        return $s;
    }

    /** Curated common ions first (exact tokens), so the ambiguous digit-vs-charge cases are correct. */
    private static function ions(string $s): string
    {
        foreach (self::IONS as $ion => $html) {
            $s = preg_replace('/(?<![A-Za-z0-9])' . preg_quote($ion, '/') . '(?![A-Za-z0-9])/u', $html, $s);
        }

        return $s;
    }

    /** Unit exponents: "mol dm-3" -> mol dm⁻³, "kJ mol-1", "cm3" -> cm³. Anchored on a unit whitelist. */
    private static function units(string $s): string
    {
        $u = 'mol|dm|cm|mm|nm|pm|km|kg|kJ|MJ|kPa|MPa|GPa|Pa|kHz|MHz|Hz|ms|ns|µs|μs|m|s|g|J|N|V|A|W|K|Ω|C|T|F';

        // negative / explicit exponent right after a unit (preceded by space, "(" or "/").
        // Accept hyphen-minus, true minus, and en/em-dash (the source often uses "–").
        $s = preg_replace_callback(
            '/(?<=[\s(\/])(' . $u . ')\s?[-−–—](\d+)(?![\d.])/u',
            fn ($m) => $m[1] . '<sup>−' . $m[2] . '</sup>',
            $s
        );
        // positive area / volume exponent ("cm3", "dm3", "m3", "mm2")
        $s = preg_replace_callback(
            '/(?<=[\s(\/])(cm|dm|mm|nm|km|m)([23])(?![\d.A-Za-z])/u',
            fn ($m) => $m[1] . '<sup>' . $m[2] . '</sup>',
            $s
        );

        return $s;
    }

    /** Formula subscripts: a digit right after an element symbol or ")", never before a sign. */
    private static function subscripts(string $s): string
    {
        static $set = null;
        if ($set === null) {
            $set = array_fill_keys(explode(' ', self::ELEMENTS), true);
        }

        return preg_replace_callback('/([A-Z][a-z]?|\))(\d+)(?![\d+\-−])/u', function ($m) use ($set) {
            if ($m[1] !== ')' && ! isset($set[$m[1]])) {
                return $m[0]; // not a real element symbol -> leave alone (Figure 2, Q1, etc.)
            }

            return $m[1] . '<sub>' . $m[2] . '</sub>';
        }, $s);
    }

    /** Bare-sign charges at a species boundary: "Na+" -> Na⁺, "OH-" -> OH⁻ (equation "A + B" untouched). */
    private static function charges(string $s): string
    {
        return preg_replace_callback(
            '/(?<=[A-Za-z\)])([+\-−–—])(?=[\s,.;:)\]]|&|$)/u',
            fn ($m) => '<sup>' . ($m[1] === '+' ? '+' : '−') . '</sup>',
            $s
        );
    }
}
