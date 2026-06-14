<?php

namespace App\Services\Questions;

/**
 * Heuristic difficulty evaluator for Cambridge AS/A Level Physics MCQs.
 *
 * Difficulty is judged relative to a Paper-1 candidate, NOT a university
 * physics student — direct recall and one-formula substitution land at "easy".
 */
class QuestionDifficultyEvaluator
{
    public const VERSION = 'rule-v1';

    private const VISUAL_LAYOUTS = [
        'question_diagram', 'option_images', 'option_table',
        'mixed_diagram', 'graph', 'circuit_diagram', 'force_diagram',
    ];

    private const TRAP_PHRASES = [
        'which statement is correct', 'which of the following statements',
        'which is correct', 'using same scales', 'using the same scales',
        'air resistance is ignored', 'air resistance can be ignored',
        'no air resistance', 'constant speed', 'zero reading',
        'best estimate', 'most accurate', 'most likely', 'most appropriate',
        'always true', 'never', 'cannot be',
        'show that', 'must be',
    ];

    private const CALC_TOKENS = [
        '=', '×', '*', '/', '+', '-', '^', '√',
    ];

    private const MULTI_STEP_HINTS = [
        'find the', 'calculate the', 'determine the', 'work out',
        'first', 'then', 'after', 'before',
    ];

    public function __construct(private readonly QuestionTextNormalizer $normalizer) {}

    /**
     * @param array<string,mixed> $question
     * @param array<int,array<string,mixed>> $tags Output of the topic classifier.
     */
    public function evaluate(array $question, array $tags = []): array
    {
        $blob = $this->normalizer->blob($question);
        $rawText = (string) ($question['question_text'] ?? '');
        $layout = (string) ($question['layout_type'] ?? 'unknown');

        $visual = $this->visualScore($question, $layout);
        $calc = $this->calculationScore($question, $blob);
        $reasoning = $this->reasoningScore($blob, $question);
        $conceptual = $this->conceptualScore($tags, $blob);
        $multiTopic = $this->multiTopicScore($tags);
        $trap = $this->trapScore($blob, $question);

        // Composite difficulty score, weighted blend of components.
        $score = round(
            0.28 * $reasoning
            + 0.22 * $calc
            + 0.18 * $conceptual
            + 0.10 * $multiTopic
            + 0.10 * $visual
            + 0.12 * $trap,
            3
        );

        $overall = match (true) {
            $score >= 0.78 => 'very_hard',
            $score >= 0.58 => 'hard',
            $score >= 0.38 => 'medium',
            default => 'easy',
        };

        $estimatedTime = $this->estimateTime($overall, $visual, $calc);
        [$driver, $reasons] = $this->primaryDriver(
            $reasoning, $calc, $conceptual, $multiTopic, $visual, $trap, $blob, $question
        );

        $needsReview = $score < 0.20 && $reasoning < 0.10 && $calc < 0.10;

        return [
            'overall' => $overall,
            'score' => $score,
            'reasoning_complexity' => round($reasoning, 3),
            'calculation_complexity' => round($calc, 3),
            'conceptual_depth' => round($conceptual, 3),
            'multi_topic_dependency' => round($multiTopic, 3),
            'visual_interpretation' => round($visual, 3),
            'trap_probability' => round($trap, 3),
            'estimated_time_seconds' => $estimatedTime,
            'primary_difficulty_driver' => $driver,
            'difficulty_reason' => $reasons,
            'needs_review' => $needsReview,
        ];
    }

    private function visualScore(array $question, string $layout): float
    {
        if ($layout === 'text_only') {
            $base = 0.05;
        } elseif (in_array($layout, self::VISUAL_LAYOUTS, true)) {
            $base = 0.7;
        } else {
            $base = 0.3;
        }

        $assets = $question['assets'] ?? [];
        if (is_array($assets) && count($assets) > 0) {
            $base += min(0.25, 0.08 * count($assets));
        }

        $optionImages = 0;
        foreach ($question['options'] ?? [] as $opt) {
            $imgs = $opt['images'] ?? [];
            if (is_array($imgs)) {
                $optionImages += count($imgs);
            }
        }
        if ($optionImages > 0) {
            $base += 0.2;
        }
        if (! empty($question['option_table'])) {
            $base += 0.2;
        }

        return min(1.0, $base);
    }

    private function calculationScore(array $question, string $blob): float
    {
        $score = 0.0;
        $rawText = (string) ($question['question_text'] ?? '');
        $combined = $rawText.' '.implode(
            ' ',
            array_map(fn ($o) => (string) ($o['text'] ?? ''), $question['options'] ?? [])
        );

        $tokenHits = 0;
        foreach (self::CALC_TOKENS as $tok) {
            $tokenHits += substr_count($combined, $tok);
        }
        if ($tokenHits >= 1) {
            $score += min(0.35, 0.06 * $tokenHits);
        }

        // Numeric density: many digits/SI units hint at calculation work.
        if (preg_match_all('/\d+(?:\.\d+)?/u', $combined, $m) > 0) {
            $score += min(0.25, 0.025 * count($m[0]));
        }

        // Powers-of-ten / scientific notation
        if (preg_match('/\d\s*(?:x|×|\*)\s*10\s*\^?\s*-?\d+/u', $blob)) {
            $score += 0.15;
        }
        if (preg_match('/\b10\^\s*-?\d+\b/u', $blob)) {
            $score += 0.10;
        }

        // Unit conversion markers
        if (preg_match('/\b(mm|cm|km|mg|kg|ms|min|kpa|mpa|gpa)\b/u', $blob)) {
            $score += 0.15;
        }

        return min(1.0, $score);
    }

    private function reasoningScore(string $blob, array $question): float
    {
        $score = 0.0;
        foreach (self::MULTI_STEP_HINTS as $hint) {
            if (str_contains($blob, $hint)) {
                $score += 0.08;
            }
        }
        if (preg_match('/which (statement|graph|diagram|expression)/u', $blob)) {
            $score += 0.20;
        }
        if (preg_match('/\b(if|when|after|before|while)\b/u', $blob)) {
            $score += 0.10;
        }
        if (preg_match('/\b(deduce|derive|explain|justify|account for)\b/u', $blob)) {
            $score += 0.20;
        }

        // Long stems imply more reasoning work.
        $words = str_word_count($blob);
        if ($words > 60) {
            $score += 0.20;
        } elseif ($words > 35) {
            $score += 0.10;
        }

        // Multi-clause options (long option text) suggest harder distractors.
        $longOpts = 0;
        foreach ($question['options'] ?? [] as $opt) {
            $t = (string) ($opt['text'] ?? '');
            if (str_word_count($t) > 8) {
                $longOpts++;
            }
        }
        if ($longOpts >= 3) {
            $score += 0.20;
        }

        return min(1.0, $score);
    }

    private function conceptualScore(array $tags, string $blob): float
    {
        $score = 0.0;
        // Topics traditionally testing abstract concepts get a baseline lift.
        $hardTopics = ['11', '13', '15', '16', '17', '18', '19', '20', '22', '23', '25'];
        foreach ($tags as $tag) {
            if (in_array($tag['topic_id'] ?? '', $hardTopics, true) && ($tag['relevance'] ?? '') !== 'low') {
                $score = max($score, 0.55);
                break;
            }
        }

        // Wording that probes principle understanding rather than calculation.
        $conceptCues = [
            'principle of', 'qualitatively', 'why', 'in terms of',
            'because of', 'reason for', 'explain why', 'explanation',
        ];
        foreach ($conceptCues as $c) {
            if (str_contains($blob, $c)) {
                $score = min(1.0, $score + 0.10);
            }
        }
        return min(1.0, $score);
    }

    private function multiTopicScore(array $tags): float
    {
        $distinctTopics = [];
        foreach ($tags as $tag) {
            if (($tag['relevance'] ?? '') === 'low') {
                continue;
            }
            $distinctTopics[$tag['topic_id'] ?? ''] = true;
        }
        $n = count(array_filter(array_keys($distinctTopics), fn ($k) => $k !== ''));
        return match (true) {
            $n >= 3 => 0.85,
            $n === 2 => 0.55,
            $n === 1 => 0.10,
            default => 0.0,
        };
    }

    private function trapScore(string $blob, array $question): float
    {
        $score = 0.0;
        foreach (self::TRAP_PHRASES as $phrase) {
            if (str_contains($blob, $phrase)) {
                $score += 0.12;
            }
        }
        // Distractor closeness heuristic: short numerical options that look similar.
        $opts = $question['options'] ?? [];
        if (count($opts) === 4) {
            $numericOpts = 0;
            foreach ($opts as $opt) {
                if (preg_match('/\d/', (string) ($opt['text'] ?? ''))) {
                    $numericOpts++;
                }
            }
            if ($numericOpts === 4) {
                $score += 0.15;
            }
        }
        if (str_contains($blob, 'common mistake') || str_contains($blob, 'is not')) {
            $score += 0.10;
        }
        return min(1.0, $score);
    }

    private function estimateTime(string $overall, float $visual, float $calc): int
    {
        $base = match ($overall) {
            'easy' => 45,
            'medium' => 70,
            'hard' => 95,
            'very_hard' => 120,
            default => 60,
        };
        $base += (int) round(20 * $visual);
        $base += (int) round(25 * $calc);
        return max(30, min(180, $base));
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function primaryDriver(
        float $reasoning,
        float $calc,
        float $conceptual,
        float $multiTopic,
        float $visual,
        float $trap,
        string $blob,
        array $question,
    ): array {
        $reasons = [];

        $components = [
            'reasoning_complexity' => $reasoning,
            'calculation_complexity' => $calc,
            'conceptual_depth' => $conceptual,
            'multi_topic_dependency' => $multiTopic,
            'visual_interpretation' => $visual,
            'trap_probability' => $trap,
        ];
        arsort($components);
        $top = array_key_first($components);
        $topVal = $components[$top];

        if ($topVal < 0.20) {
            $driver = 'direct_recall';
            $reasons[] = 'Question reads as a single-fact recall with no calculation or visual element.';
            return [$driver, $reasons];
        }

        $layout = (string) ($question['layout_type'] ?? '');
        $hasGraph = str_contains($blob, 'graph') || str_contains($blob, 'velocity-time')
            || str_contains($blob, 'distance-time') || str_contains($blob, 'gradient');
        $hasDiagram = $layout === 'question_diagram' || $layout === 'force_diagram'
            || $layout === 'circuit_diagram' || str_contains($blob, 'diagram');

        $driver = match ($top) {
            'reasoning_complexity' => $multiTopic >= 0.55 ? 'multi_topic_reasoning' : 'abstract_physics_reasoning',
            'calculation_complexity' => $reasoning >= 0.45 ? 'multi_step_calculation' : 'formula_substitution',
            'conceptual_depth' => 'abstract_physics_reasoning',
            'multi_topic_dependency' => 'multi_topic_reasoning',
            'visual_interpretation' => $hasGraph ? 'graph_interpretation'
                : ($hasDiagram ? 'diagram_reasoning' : 'experimental_analysis'),
            'trap_probability' => 'conceptual_trap',
            default => 'direct_recall',
        };

        // Override: unit conversion is a recognisable simple driver.
        if ($calc > 0 && preg_match('/\b(mm|cm|km|kg|mg|μ|micro|nano|pico|kpa|mpa)\b/u', $blob)
            && $reasoning < 0.30 && $multiTopic < 0.40) {
            $driver = 'units_conversion';
        }

        $reasons[] = sprintf(
            'Highest-loaded driver is %s (%.2f).',
            str_replace('_', ' ', (string) $top),
            $topVal
        );
        if ($trap >= 0.35) {
            $reasons[] = 'Distractor wording suggests a conceptual trap.';
        }
        if ($visual >= 0.5) {
            $reasons[] = 'Visual element (diagram/graph/table) requires interpretation.';
        }
        if ($multiTopic >= 0.55) {
            $reasons[] = 'Spans more than one syllabus topic.';
        }
        if ($calc >= 0.5 && $reasoning >= 0.4) {
            $reasons[] = 'Combines multi-step reasoning with numeric calculation.';
        }

        return [$driver, $reasons];
    }
}
