<?php

namespace App\Console\Commands\V2;

use App\Models\V2\Question;
use App\Models\V2\Subject;
use App\Models\V2\Topic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| v2:tag-by-keywords
|--------------------------------------------------------------------------
| Subject-generic keyword tagger. Seeds v2_topics for a subject from a syllabus
| JSON that carries weighted keywords per topic, then assigns each question the
| highest-scoring topic by matching those keywords against the question's text +
| option text + option-table cells + image captions/OCR.
|
| Built for O-Level Physics (5054), whose syllabus has different topics than the
| A-Level keyword classifier (v2:tag-questions). Any subject with a keyword JSON
| of the same shape can be tagged the same way.
|
|   php artisan v2:tag-by-keywords --subject=5054 \
|       --syllabus=storage/syllabus/physics/OLevels/subject_content_o_level_physics.json
*/
class TagByKeywords extends Command
{
    protected $signature = 'v2:tag-by-keywords
        {--subject=5054 : Subject code in v2_subjects}
        {--syllabus=storage/syllabus/physics/OLevels/subject_content_o_level_physics.json : Keyword syllabus JSON}
        {--fresh : Clear existing topic assignments for this subject first}';

    protected $description = 'Seed topics from a keyword syllabus JSON and tag a subject\'s questions to the best-matching topic';

    /** Below this best-score a tag is kept but flagged needs_review. */
    private const MIN_CONFIDENCE = 3.5;

    public function handle(): int
    {
        $subject = Subject::where('code', $this->option('subject'))->first();
        if (! $subject) {
            $this->error("Subject {$this->option('subject')} not found. Run V2SubjectsSeeder.");
            return self::FAILURE;
        }

        $path = $this->option('syllabus');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) {
            $this->error("Syllabus JSON not found: {$path}");
            return self::FAILURE;
        }
        $json = json_decode(file_get_contents($path), true);
        if (empty($json['topics'])) {
            $this->error('Syllabus JSON has no topics[].');
            return self::FAILURE;
        }

        // 1. Upsert topics for the subject.
        foreach ($json['topics'] as $i => $t) {
            Topic::updateOrCreate(
                ['subject_id' => $subject->id, 'external_id' => (string) $t['id']],
                ['title' => $t['title'] ?? '', 'level' => $json['level'] ?? $subject->level, 'sort_order' => $i + 1]
            );
        }
        $topicMap = Topic::where('subject_id', $subject->id)->pluck('id', 'external_id');
        $this->info("Seeded {$topicMap->count()} topics for {$subject->code}.");

        // Pre-normalise the keyword lists (dedupe dash/slash variants per topic).
        $keywords = [];
        foreach ($json['topics'] as $t) {
            $seen = [];
            foreach (($t['keywords'] ?? []) as $entry) {
                [$kw, $w] = is_array($entry) ? $entry : [$entry, 1];
                $n = $this->normalize($kw);
                if ($n !== ' ' && ! isset($seen[$n])) {
                    $seen[$n] = true;
                    $keywords[$t['id']][] = [trim($n), (float) $w];
                }
            }
        }

        if ($this->option('fresh')) {
            Question::where('subject_id', $subject->id)->update(['topic_id' => null, 'subtopic_id' => null]);
        }

        // 2. Tag each question.
        $stats = ['tagged' => 0, 'untagged' => 0, 'review' => 0];
        $byTopic = [];
        $questions = Question::where('subject_id', $subject->id)->with('options', 'images')->get();
        $bar = $this->output->createProgressBar($questions->count());
        $bar->start();

        foreach ($questions as $q) {
            $blob = $this->blob($q);
            $best = null;
            $bestScore = 0.0;
            foreach ($keywords as $ext => $kws) {
                $score = 0.0;
                foreach ($kws as [$kw, $w]) {
                    if (str_contains($blob, $kw)) {
                        $score += $w;
                    }
                }
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $ext;
                }
            }

            if ($best === null || $bestScore <= 0 || ! isset($topicMap[$best])) {
                $q->update(['topic_id' => null]);
                $stats['untagged']++;
            } else {
                $review = $bestScore < self::MIN_CONFIDENCE;
                $q->update(['topic_id' => $topicMap[$best], 'needs_review' => $q->needs_review || $review]);
                $stats['tagged']++;
                $stats['review'] += $review ? 1 : 0;
                $byTopic[$best] = ($byTopic[$best] ?? 0) + 1;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Tagged {$stats['tagged']} / {$questions->count()} questions ({$stats['untagged']} untagged, {$stats['review']} low-confidence flagged for review).");
        ksort($byTopic);
        $this->table(['topic', 'questions'], collect($byTopic)->map(fn ($c, $ext) => [$ext.' '.($json['topics'][array_search($ext, array_column($json['topics'], 'id'))]['title'] ?? ''), $c])->all());

        return self::SUCCESS;
    }

    /** Build the searchable blob: stem + options + table cells + image captions/OCR. */
    private function blob(Question $q): string
    {
        $parts = [$q->question_text, $q->text_before, $q->text_after];
        foreach ($q->options as $o) {
            $parts[] = $o->text;
        }
        if (is_array($q->option_table['rows'] ?? null)) {
            foreach ($q->option_table['rows'] as $row) {
                $parts[] = is_array($row) ? implode(' ', $row) : $row;
            }
            $parts[] = implode(' ', $q->option_table['headers'] ?? []);
        }
        foreach ($q->images as $img) {
            $parts[] = $img->caption;
            $parts[] = $img->ocr_text;
        }

        return $this->normalize(implode(' ', array_filter($parts)));
    }

    /** Lowercase, unify dashes/slashes, collapse whitespace; pad so word-ish matches are clean. */
    private function normalize(?string $s): string
    {
        $s = mb_strtolower((string) $s);
        $s = str_replace(['–', '—'], '-', $s);
        $s = preg_replace('/\s*\/\s*/', '/', $s);   // "m / s" -> "m/s"
        $s = preg_replace('/\s+/', ' ', $s);

        return ' '.trim($s).' ';
    }
}
