<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Subject;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:fix-symbol-font
|--------------------------------------------------------------------------
| CAIE PDFs draw arrows, Greek letters and maths operators with the Adobe
| "Symbol" font. The extractor kept the raw Symbol byte as a Private-Use-Area
| codepoint (e.g. the reaction arrow -> stored as U+F0AE), which has no glyph
| in normal fonts and renders as a tofu box. This maps every Symbol-font PUA
| codepoint back to its real Unicode character across the question text layer.
|
| Deterministic + idempotent (a second run finds nothing). Source images and
| correct_answer are never touched. Re-runnable after any re-import.
*/
class FixSymbolFont extends Command
{
    protected $signature = 'v2:fix-symbol-font
        {--subject=* : Limit to these subject codes (default: all v2 subjects)}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Repair Adobe Symbol-font characters mis-stored as Private-Use codepoints (arrows, Greek, maths operators)';

    /** PUA codepoint (0xF000 + Symbol byte) => real Unicode character. */
    private function map(): array
    {
        $m = [
            // arrows & logic
            0xF0AC => '←', 0xF0AD => '↑', 0xF0AE => '→', 0xF0AF => '↓', 0xF0AB => '↔',
            0xF0DB => '⇔', 0xF0DC => '⇐', 0xF0DD => '⇑', 0xF0DE => '⇒', 0xF0DF => '⇓',
            // maths operators
            0xF0A3 => '≤', 0xF0A5 => '∞', 0xF0B0 => '°', 0xF0B1 => '±', 0xF0B2 => '″',
            0xF0B3 => '≥', 0xF0B4 => '×', 0xF0B5 => '∝', 0xF0B6 => '∂', 0xF0B7 => '·',
            0xF0B8 => '÷', 0xF0B9 => '≠', 0xF0BA => '≡', 0xF0BB => '≈', 0xF0BC => '…',
            0xF0D6 => '√', 0xF0F2 => '∫', 0xF0D1 => '∇', 0xF040 => '≅', 0xF0B9 => '≠',
            // Symbol-font ASCII (digits/punct render 1:1; these are the maths ones seen)
            0xF020 => ' ', 0xF02B => '+', 0xF02D => '−', 0xF02E => '.', 0xF03D => '=',
            0xF028 => '(', 0xF029 => ')', 0xF03C => '<', 0xF03E => '>', 0xF02F => '/',
            0xF03A => ':', 0xF02C => ',', 0xF0B4 => '×',
            // lowercase Greek (Symbol 0x61-0x7A)
            0xF061 => 'α', 0xF062 => 'β', 0xF063 => 'χ', 0xF064 => 'δ', 0xF065 => 'ε',
            0xF066 => 'φ', 0xF067 => 'γ', 0xF068 => 'η', 0xF069 => 'ι', 0xF06B => 'κ',
            0xF06C => 'λ', 0xF06D => 'μ', 0xF06E => 'ν', 0xF06F => 'ο', 0xF070 => 'π',
            0xF071 => 'θ', 0xF072 => 'ρ', 0xF073 => 'σ', 0xF074 => 'τ', 0xF075 => 'υ',
            0xF077 => 'ω', 0xF078 => 'ξ', 0xF079 => 'ψ', 0xF07A => 'ζ',
            // uppercase Greek (Symbol 0x41-0x5A)
            0xF044 => 'Δ', 0xF046 => 'Φ', 0xF047 => 'Γ', 0xF050 => 'Π', 0xF051 => 'Θ',
            0xF053 => 'Σ', 0xF057 => 'Ω', 0xF058 => 'Ξ', 0xF059 => 'Ψ', 0xF04C => 'Λ',
        ];

        // Tall-bracket / radical / integral EXTENDER pieces (Apple Symbol PUA F8xx and the
        // Adobe F0E5-F0E8 / F0F6-F0F8 ranges). They are layout artifacts of multi-line
        // maths flattened to inline text - strip them.
        foreach (range(0xF8E0, 0xF8FF) as $cp) { $m[$cp] = ''; }
        foreach ([0xF0E5, 0xF0E6, 0xF0E7, 0xF0E8, 0xF0F6, 0xF0F7, 0xF0F8] as $cp) { $m[$cp] = ''; }

        $out = [];
        foreach ($m as $cp => $repl) { $out[mb_chr($cp, 'UTF-8')] = $repl; }

        return $out;
    }

    public function handle(): int
    {
        $map = $this->map();
        $search = array_keys($map);
        $replace = array_values($map);
        $dry = (bool) $this->option('dry-run');

        $codes = $this->option('subject') ?: Subject::pluck('code')->all();
        $subjectIds = Subject::whereIn('code', $codes)->pluck('id')->all();

        // Collapse the runs of spaces a stripped arrow/operator can leave behind.
        $clean = function (?string $s) use ($search, $replace): ?string {
            if ($s === null || $s === '') {
                return $s;
            }
            $new = str_replace($search, $replace, $s);

            return preg_replace('/[ \t]{2,}/', ' ', $new);
        };

        $touched = ['questions' => 0, 'options' => 0, 'images' => 0];

        // Recursively clean every string cell of a decoded option_table (headers + rows).
        $cleanTable = function (?string $json) use ($clean): ?string {
            if ($json === null || $json === '' || ! str_contains($json, "\u{E000}") && ! preg_match('/[\x{E000}-\x{F8FF}]/u', $json)) {
                return $json; // nothing to do (fast path)
            }
            $decoded = json_decode($json, true);
            if (! is_array($decoded)) {
                return $json;
            }
            $walk = function (&$node) use (&$walk, $clean) {
                foreach ($node as &$v) {
                    if (is_array($v)) {
                        $walk($v);
                    } elseif (is_string($v)) {
                        $v = $clean($v);
                    }
                }
            };
            $walk($decoded);

            return json_encode($decoded); // matches Laravel's array-cast storage (escaped unicode/slashes)
        };

        // ---- v2_questions: question_text + text_before + text_after + option_table ----
        DB::table('v2_questions')->whereIn('subject_id', $subjectIds)
            ->orderBy('id')->select('id', 'question_text', 'text_before', 'text_after', 'option_table')
            ->chunkById(500, function ($rows) use ($clean, $cleanTable, $dry, &$touched) {
                foreach ($rows as $r) {
                    $upd = [];
                    foreach (['question_text', 'text_before', 'text_after'] as $col) {
                        $new = $clean($r->$col);
                        if ($new !== $r->$col) {
                            $upd[$col] = $new;
                        }
                    }
                    $newTable = $cleanTable($r->option_table);
                    if ($newTable !== $r->option_table) {
                        $upd['option_table'] = $newTable;
                    }
                    if ($upd) {
                        $touched['questions']++;
                        if (! $dry) {
                            DB::table('v2_questions')->where('id', $r->id)->update($upd);
                        }
                    }
                }
            });

        // ---- v2_question_options: text ----
        DB::table('v2_question_options as o')->join('v2_questions as q', 'q.id', '=', 'o.question_id')
            ->whereIn('q.subject_id', $subjectIds)->orderBy('o.id')->select('o.id', 'o.text')
            ->chunkById(1000, function ($rows) use ($clean, $dry, &$touched) {
                foreach ($rows as $r) {
                    $new = $clean($r->text);
                    if ($new !== $r->text) {
                        $touched['options']++;
                        if (! $dry) {
                            DB::table('v2_question_options')->where('id', $r->id)->update(['text' => $new]);
                        }
                    }
                }
            }, 'o.id', 'id');

        // ---- v2_question_images: caption + ocr_text ----
        DB::table('v2_question_images as i')->join('v2_questions as q', 'q.id', '=', 'i.question_id')
            ->whereIn('q.subject_id', $subjectIds)->orderBy('i.id')->select('i.id', 'i.caption', 'i.ocr_text')
            ->chunkById(1000, function ($rows) use ($clean, $dry, &$touched) {
                foreach ($rows as $r) {
                    $upd = [];
                    foreach (['caption', 'ocr_text'] as $col) {
                        $new = $clean($r->$col);
                        if ($new !== $r->$col) {
                            $upd[$col] = $new;
                        }
                    }
                    if ($upd) {
                        $touched['images']++;
                        if (! $dry) {
                            DB::table('v2_question_images')->where('id', $r->id)->update($upd);
                        }
                    }
                }
            }, 'i.id', 'id');

        $this->info(($dry ? '[DRY RUN] ' : '') . "Symbol-font repair on subjects: " . implode(', ', $codes));
        $this->table(['target', 'rows changed'], collect($touched)->map(fn ($v, $k) => [$k, $v])->all());

        return self::SUCCESS;
    }
}
