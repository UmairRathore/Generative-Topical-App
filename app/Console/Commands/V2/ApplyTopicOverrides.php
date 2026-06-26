<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Question;
use App\Models\V2\Subject;
use App\Models\V2\Topic;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:apply-topic-overrides
|--------------------------------------------------------------------------
| Curated, human-verified corrections layered on top of the deterministic
| keyword classifier (v2:tag-questions). The classifier mis-tags a question
| when another topic's question happens to contain kinematics keywords
| (velocity / acceleration / displacement) - e.g. a Doppler, oil-drop, or
| collision question. Each entry below was verified by reading the question.
|
| Keyed by (source_paper, question_number) so it is stable across re-imports.
| Every target is a real syllabus topic external_id, so nothing out-of-syllabus
| can be written. Run AFTER v2:tag-questions. Idempotent.
|
| Scope: the demo's Kinematics 2020-2024 slice (the generated demo test). Extend
| this list as further corrections are verified.
*/
class ApplyTopicOverrides extends Command
{
    protected $signature = 'v2:apply-topic-overrides {--subject=9702}';

    protected $description = 'Apply curated topic corrections on top of the keyword classifier (no API)';

    /**
     * [source_paper, question_number, correct_topic_external_id, note]
     * @var array<int,array{0:string,1:int,2:string,3:string}>
     */
    private const OVERRIDES = [
        // --- mis-tagged as Kinematics, really WAVES (7) ---
        ['9702_m22_qp_12', 22, '7', 'Doppler: ambulance siren frequency'],
        ['9702_s20_qp_11', 25, '7', 'Doppler: moving sound source'],
        ['9702_s20_qp_13', 23, '7', 'wave on coiled spring, frequency'],
        ['9702_s21_qp_13', 23, '7', 'progressive sound wave displacement'],
        ['9702_w20_qp_13', 23, '7', 'sound wave displacement-time'],
        ['9702_w23_qp_11', 22, '7', 'Doppler: drone sound'],
        ['9702_w22_qp_12', 22, '7', 'wave pulse on rope'],
        ['9702_w24_qp_12', 24, '7', 'longitudinal sound wave'],
        ['9702_s23_qp_13', 21, '7', 'progressive wave displacement graphs'],

        // --- really SUPERPOSITION (8) ---
        ['9702_w24_qp_12', 28, '8', 'superposition of two waves'],

        // --- really ELECTRIC FIELDS (18) ---
        ['9702_s20_qp_11', 32, '18', 'charged particle between parallel plates'],
        ['9702_s20_qp_13', 32, '18', 'charged oil drop between plates'],
        ['9702_w20_qp_11', 31, '18', 'oil drop held in field between plates'],
        ['9702_w20_qp_12', 31, '18', 'electric dipole between charges'],

        // --- really ELECTRICITY (9) ---
        ['9702_s20_qp_13', 34, '9', 'current in copper wires, ratio'],
        ['9702_w20_qp_11', 32, '9', 'drift speed / current in copper wire'],
        ['9702_s22_qp_13', 32, '9', 'two wires resistance in series'],

        // --- really FORCES, DENSITY & PRESSURE (4) ---
        ['9702_s21_qp_13', 15, '4', 'derivation of p = rho g h'],
        ['9702_w22_qp_13', 15, '4', 'pressure of cube on surface'],
        ['9702_s22_qp_12', 15, '4', 'U-tube hydrostatic pressure'],

        // --- really DYNAMICS / momentum (3) ---
        ['9702_m21_qp_12', 10, '3', 'firework explosion, momentum'],
        ['9702_s21_qp_12', 10, '3', 'molecule collision, momentum'],
        ['9702_w20_qp_13', 10, '3', 'collide and stick, momentum'],
        ['9702_w21_qp_11', 10, '3', 'ball rebounds off wall, momentum'],
        ['9702_w23_qp_13', 8,  '3', 'snooker ball rebound, momentum'],
    ];

    public function handle(): int
    {
        $subject = Subject::where('code', $this->option('subject'))->first();
        if (! $subject) {
            $this->error("Subject {$this->option('subject')} not found.");
            return self::FAILURE;
        }

        $topicMap = Topic::where('subject_id', $subject->id)->pluck('id', 'external_id');

        $applied = 0;
        $missing = 0;

        foreach (self::OVERRIDES as [$paper, $qnum, $topicExt, $note]) {
            $topicId = $topicMap[$topicExt] ?? null;
            if (! $topicId) {
                $this->warn("  topic {$topicExt} not found (skipping {$paper} #{$qnum})");
                continue;
            }

            $q = Question::where('subject_id', $subject->id)
                ->where('source_paper', $paper)
                ->where('question_number', $qnum)
                ->first();

            if (! $q) {
                $this->warn("  question not found: {$paper} #{$qnum}");
                $missing++;
                continue;
            }

            $q->update([
                'topic_id'     => $topicId,
                'subtopic_id'  => null,        // topic corrected; subtopic left for re-tagging
                'needs_review' => false,       // human-verified
            ]);
            $applied++;
        }

        $this->info("Applied {$applied} topic overrides (".count(self::OVERRIDES)." defined, {$missing} missing).");
        return self::SUCCESS;
    }
}
