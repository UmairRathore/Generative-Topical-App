<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Question;
use App\Models\V2\Subject;
use App\Models\V2\Subtopic;
use App\Models\V2\Topic;
use App\Services\Questions\QuestionTextNormalizer;
use App\Services\Questions\QuestionTopicClassifier;
use Illuminate\Console\Command;

/*
|--------------------------------------------------------------------------
| v2:tag-questions
|--------------------------------------------------------------------------
| Tags v2_questions with a syllabus topic + subtopic using the deterministic
| QuestionTopicClassifier (rule/keyword based). NO external API.
|
| The classifier is anchored to the loaded syllabus tree - it can only return
| topic/subtopic ids that exist in the syllabus, so nothing out-of-syllabus is
| ever written. We additionally hard-map every returned id back to a real
| v2_topics / v2_subtopics row and skip anything that doesn't resolve.
|
| Run after v2:import-questions. Idempotent; use --fresh to re-tag from clean.
*/
class TagQuestions extends Command
{
    protected $signature = 'v2:tag-questions
        {--subject=9702 : Subject code in v2_subjects}
        {--fresh : Clear existing topic/subtopic assignments before tagging}
        {--syllabus= : Override syllabus JSON path}';

    protected $description = 'Tag V2 questions with syllabus topics using the in-repo deterministic classifier (no API)';

    public function handle(): int
    {
        $subject = Subject::where('code', $this->option('subject'))->first();
        if (! $subject) {
            $this->error("Subject {$this->option('subject')} not found in v2_subjects.");
            return self::FAILURE;
        }

        $syllabusPath = $this->option('syllabus')
            ?: storage_path('syllabus/physics/Alevels/subject_content_a_level_physics.json');
        if (! is_file($syllabusPath)) {
            $this->error("Syllabus JSON not found: {$syllabusPath}");
            return self::FAILURE;
        }

        // Build the classifier and load the syllabus tree (in-memory; anchors every tag).
        $classifier = new QuestionTopicClassifier(new QuestionTextNormalizer());
        $classifier->loadSyllabus(json_decode(file_get_contents($syllabusPath), true));

        // external_id ("2" / "2.1") -> v2 primary keys, scoped to this subject.
        $topicMap = Topic::where('subject_id', $subject->id)->pluck('id', 'external_id');
        $subtopicMap = Subtopic::whereIn('topic_id', $topicMap->values())->pluck('id', 'external_id');

        if ($this->option('fresh')) {
            $cleared = Question::where('subject_id', $subject->id)
                ->update(['topic_id' => null, 'subtopic_id' => null]);
            $this->warn("--fresh: cleared topic assignments on {$cleared} questions.");
        }

        $total = Question::where('subject_id', $subject->id)->count();
        if ($total === 0) {
            $this->error('No questions for this subject. Run v2:import-questions first.');
            return self::FAILURE;
        }

        $stats = ['tagged' => 0, 'untagged' => 0, 'needs_review' => 0, 'unresolved' => 0];
        $perTopic = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        Question::where('subject_id', $subject->id)
            ->with(['options:id,question_id,text', 'images:id,question_id,caption,ocr_text,diagram_labels'])
            ->chunkById(250, function ($questions) use ($classifier, $topicMap, $subtopicMap, &$stats, &$perTopic, $bar) {
                foreach ($questions as $q) {
                    $tags = $classifier->classify($this->toClassifierArray($q));
                    $bar->advance();

                    if (empty($tags)) {
                        $q->update(['topic_id' => null, 'subtopic_id' => null]);
                        $stats['untagged']++;
                        continue;
                    }

                    $top = $tags[0];
                    $topicId = $topicMap[(string) $top['topic_id']] ?? null;
                    $subtopicId = $subtopicMap[(string) ($top['subtopic_id'] ?? '')] ?? null;

                    // Hard guard: a tag that doesn't resolve to a real syllabus row is dropped.
                    if (! $topicId) {
                        $q->update(['topic_id' => null, 'subtopic_id' => null]);
                        $stats['unresolved']++;
                        continue;
                    }

                    $q->update([
                        'topic_id'     => $topicId,
                        'subtopic_id'  => $subtopicId,
                        'needs_review' => $q->needs_review || ! empty($top['needs_review']),
                    ]);

                    $stats['tagged']++;
                    if (! empty($top['needs_review'])) {
                        $stats['needs_review']++;
                    }
                    $label = (string) $top['topic_id'].' '.($top['topic_title'] ?? '');
                    $perTopic[$label] = ($perTopic[$label] ?? 0) + 1;
                }
            }, 'id');

        $bar->finish();
        $this->newLine(2);

        $this->info("Tagging complete for subject {$subject->code}:");
        $this->table(['metric', 'count'], collect($stats)->map(fn ($v, $k) => [$k, $v])->all());

        ksort($perTopic, SORT_NATURAL);
        $this->line('Per-topic distribution:');
        $rows = [];
        foreach ($perTopic as $label => $count) {
            $rows[] = [$label, $count];
        }
        $this->table(['topic', 'questions'], $rows);

        if ($stats['untagged'] > 0) {
            $this->warn("{$stats['untagged']} questions had no keyword match (left untagged). A cleanup pass can address these.");
        }

        return self::SUCCESS;
    }

    /** Shape a V2 question (+ relations) into the array the classifier expects. */
    private function toClassifierArray(Question $q): array
    {
        return [
            'question_text' => $q->question_text,
            'layout_type'   => $q->layout_type,
            'option_table'  => $q->option_table,
            'options'       => $q->options->map(fn ($o) => ['text' => $o->text])->all(),
            'assets'        => $q->images->map(fn ($im) => [
                'caption'        => $im->caption,
                'ocr_text'       => $im->ocr_text,
                'diagram_labels' => $im->diagram_labels,
            ])->all(),
        ];
    }
}
