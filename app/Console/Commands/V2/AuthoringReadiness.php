<?php

namespace App\Console\Commands\V2;

use App\Models\V2\SyllabusSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:authoring-readiness
|--------------------------------------------------------------------------
| Read-only, deterministic map of how far EVERY leaf section of a syllabus
| source is from being Stage-B-authorable. Measures the same inputs the
| packet compiler fails closed on - it never generates content, never
| approves anything, and never weakens a gate. This is the rollout
| dashboard for scaling the authoring pipeline across a whole subject.
|
|   php artisan v2:authoring-readiness --source=5054@2026-2028 [--json]
|
| Per-section verdicts (strictly ordered gates):
|   needs_lo_validation  - machine-extracted LOs not yet human-validated
|   needs_curation       - LOs validated; references / evidence /
|                          misconceptions / widget shortlist incomplete
|   packet_ready         - deterministic packet inputs complete; awaiting
|                          human foundation sign-off
|   plan_stage           - foundation signed; Stage A plan authoring/approval
|   stage_b_ready        - foundation + plan approvals recorded
*/
class AuthoringReadiness extends Command
{
    protected $signature = 'v2:authoring-readiness
        {--source= : Syllabus source reference, e.g. 5054@2026-2028}
        {--json : Emit the full matrix as JSON}';

    protected $description = 'Deterministic per-section authoring-pipeline readiness matrix for one syllabus source (read-only)';

    public function handle(): int
    {
        $source = $this->option('source') ? SyllabusSource::resolveReference($this->option('source')) : null;
        if (! $source) {
            $this->error('Pass --source=<code>@<version> for a registered source.');

            return self::FAILURE;
        }

        $sections = [];

        foreach (DB::table('v2_learning_objectives')
            ->where('syllabus_source_id', $source->id)
            ->get(['section_code', 'section_title', 'status']) as $lo) {
            $row = &$sections[$lo->section_code];
            $row['title'] = $lo->section_title;
            $row['lo_total'] = ($row['lo_total'] ?? 0) + 1;
            $row['lo_validated'] = ($row['lo_validated'] ?? 0) + ($lo->status === 'validated' ? 1 : 0);
            unset($row);
        }
        ksort($sections, SORT_NATURAL);

        foreach (DB::table('v2_learning_objective_references as m')
            ->join('v2_learning_objectives as lo', 'lo.id', '=', 'm.learning_objective_id')
            ->join('v2_syllabus_references as r', 'r.id', '=', 'm.syllabus_reference_id')
            ->where('lo.syllabus_source_id', $source->id)
            ->where('r.status', 'validated')
            ->select('lo.section_code', DB::raw('count(distinct r.id) as n'))
            ->groupBy('lo.section_code')->get() as $row) {
            if (isset($sections[$row->section_code])) {
                $sections[$row->section_code]['refs_validated'] = (int) $row->n;
            }
        }

        foreach (DB::table('v2_questions as q')
            ->join('v2_subtopics as st', 'st.id', '=', 'q.subtopic_id')
            ->join('v2_topics as t', 't.id', '=', 'st.topic_id')
            ->where('t.subject_id', $source->subject_id)
            ->where('q.status', 'active')
            ->select('st.external_id', DB::raw('count(*) as n'))
            ->groupBy('st.external_id')->get() as $row) {
            if (isset($sections[$row->external_id])) {
                $sections[$row->external_id]['questions_active'] = (int) $row->n;
            }
        }

        $referenceDir = dirname(base_path($source->source_pdf_path)).'/references';
        foreach (['evidence', 'misconceptions', 'widgets'] as $kind) {
            foreach (glob("{$referenceDir}/{$kind}/*.json") ?: [] as $file) {
                $code = basename($file, '.json');
                if (isset($sections[$code])) {
                    $sections[$code]['curated_'.$kind] = true;
                }
            }
        }

        foreach (DB::table('v2_evidence_compatibilities')
            ->where('new_syllabus_source_id', $source->id)
            ->where('status', 'active')->pluck('section_code') as $code) {
            if (isset($sections[$code])) {
                $sections[$code]['evidence_compat'] = true;
            }
        }

        $prefix = $source->reference();
        foreach (DB::table('v2_review_signoffs')->pluck('scope') as $scope) {
            foreach (['stage-a-foundation' => 'foundation_signed', 'stage-a-plan' => 'plan_approved', 'stage-b-lesson' => 'lesson_approved'] as $kind => $flag) {
                if (str_starts_with($scope, "{$kind}:{$prefix}:")) {
                    $code = explode(':', $scope)[2] ?? null;
                    if ($code !== null && isset($sections[$code])) {
                        $sections[$code][$flag] = true;
                    }
                }
            }
        }

        $matrix = [];
        foreach ($sections as $code => $s) {
            $losValidated = ($s['lo_validated'] ?? 0) === ($s['lo_total'] ?? -1);
            $curated = ($s['refs_validated'] ?? 0) > 0
                && ! empty($s['curated_evidence']) && ! empty($s['curated_misconceptions']) && ! empty($s['curated_widgets']);
            $verdict = match (true) {
                ! empty($s['plan_approved']) && ! empty($s['foundation_signed']) => 'stage_b_ready',
                ! empty($s['foundation_signed']) => 'plan_stage',
                $losValidated && $curated => 'packet_ready',
                $losValidated => 'needs_curation',
                default => 'needs_lo_validation',
            };
            $matrix[] = [
                'section' => $code,
                'title' => $s['title'] ?? '',
                'los' => ($s['lo_validated'] ?? 0).'/'.($s['lo_total'] ?? 0),
                'refs' => $s['refs_validated'] ?? 0,
                'questions' => $s['questions_active'] ?? 0,
                'evidence' => ! empty($s['curated_evidence']),
                'misconceptions' => ! empty($s['curated_misconceptions']),
                'widgets' => ! empty($s['curated_widgets']),
                'compat' => ! empty($s['evidence_compat']),
                'foundation' => ! empty($s['foundation_signed']),
                'plan' => ! empty($s['plan_approved']),
                'verdict' => $verdict,
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'source' => $source->reference(),
                'reference_dir' => $referenceDir,
                'sections' => $matrix,
                'summary' => collect($matrix)->countBy('verdict'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(
            ['section', 'title', 'LOs ok', 'refs', 'Qs', 'evid', 'misc', 'widg', 'compat', 'fnd', 'plan', 'verdict'],
            array_map(fn ($r) => [
                $r['section'], mb_strimwidth($r['title'], 0, 34, '…'), $r['los'], $r['refs'], $r['questions'],
                $r['evidence'] ? '✓' : '·', $r['misconceptions'] ? '✓' : '·', $r['widgets'] ? '✓' : '·',
                $r['compat'] ? '✓' : '·', $r['foundation'] ? '✓' : '·', $r['plan'] ? '✓' : '·', $r['verdict'],
            ], $matrix),
        );
        foreach (collect($matrix)->countBy('verdict') as $verdict => $n) {
            $this->line(str_pad($verdict, 22).$n);
        }

        return self::SUCCESS;
    }
}
