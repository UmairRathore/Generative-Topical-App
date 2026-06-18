<?php

namespace App\Services\V2;

use App\Models\V2\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Student progress report
|--------------------------------------------------------------------------
| Pulls the student's per-subject / per-topic stats over a window and writes a
| short narrative. Uses OpenAI when OPENAI_API_KEY is set; otherwise falls back
| to a deterministic, stats-driven narrative so the PDF always works.
| Only minimal context (first name + numbers) is sent to OpenAI.
*/
class ReportService
{
    public function __construct(private ExamService $exams) {}

    public function build(Student $student, Carbon $since, string $periodLabel): array
    {
        $stats = $this->exams->studentStats($student, $since);

        return [
            'stats'     => $stats,
            'narrative' => $this->narrative($student, $stats, $periodLabel),
        ];
    }

    private function narrative(Student $student, array $stats, string $periodLabel): array
    {
        if ($stats['overall']['tests'] === 0) {
            return ['source' => 'none', 'summary' => 'No tests were completed in this period, so there is no performance to report yet.', 'subjects' => []];
        }

        $key = config('services.openai.key');
        if ($key) {
            try {
                return $this->openai($key, $student, $stats, $periodLabel);
            } catch (\Throwable $e) {
                Log::warning('OpenAI report generation failed, using fallback: '.$e->getMessage());
            }
        }

        return $this->fallback($student, $stats, $periodLabel);
    }

    /* ---- OpenAI ---------------------------------------------------------- */

    private function openai(string $key, Student $student, array $stats, string $periodLabel): array
    {
        $first = strtok($student->name, ' ');
        $compact = collect($stats['subjects'])->map(fn ($s) => [
            'subject' => $s['subject'],
            'average' => $s['avg'],
            'tests'   => $s['tests_count'],
            'topics'  => collect($s['topics'])->map(fn ($t) => ['topic' => $t['topic'], 'percent' => $t['percent']])->all(),
        ])->all();

        $prompt = "Student: {$first}\nPeriod: {$periodLabel}\nOverall average: {$stats['overall']['avg']}%\n"
            ."Subjects (with per-topic accuracy %):\n".json_encode($compact, JSON_PRETTY_PRINT)."\n\n"
            ."Write a concise progress report. Return STRICT JSON with keys: "
            ."\"summary\" (2-3 sentences, parent-friendly, encouraging but honest, mention overall standing and the biggest area to improve), "
            ."and \"subjects\" (an object mapping each subject name to a 1-2 sentence comment naming its strongest and weakest topic with a concrete next step). Use the student's first name.";

        $resp = Http::withToken($key)->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model'           => config('services.openai.model', 'gpt-4o-mini'),
            'messages'        => [
                ['role' => 'system', 'content' => 'You are a concise, encouraging academic mentor writing short progress reports for a Cambridge O/A-Level school. Plain, parent-friendly language. Always return valid JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature'     => 0.5,
        ]);

        $resp->throw();
        $json = json_decode($resp->json('choices.0.message.content') ?? '{}', true) ?: [];

        $fallback = $this->fallback($student, $stats, $periodLabel);

        return [
            'source'   => 'openai',
            'summary'  => $json['summary'] ?? $fallback['summary'],
            'subjects' => is_array($json['subjects'] ?? null) ? $json['subjects'] : $fallback['subjects'],
        ];
    }

    /* ---- Deterministic fallback ----------------------------------------- */

    private function fallback(Student $student, array $stats, string $periodLabel): array
    {
        $first = strtok($student->name, ' ');
        $avg = $stats['overall']['avg'];
        $standing = $avg >= 75 ? 'performing strongly' : ($avg >= 60 ? 'making solid progress' : ($avg >= 40 ? 'developing but needs support' : 'struggling and needs close attention'));

        $subjects = [];
        $weakestOverall = null;
        foreach ($stats['subjects'] as $s) {
            $topics = collect($s['topics'])->filter(fn ($t) => $t['total'] > 0);
            $best = $topics->sortByDesc('percent')->first();
            $weak = $topics->sortBy('percent')->first();
            if ($weak && ($weakestOverall === null || $weak['percent'] < $weakestOverall['percent'])) {
                $weakestOverall = ['subject' => $s['subject']] + $weak;
            }
            $parts = ["{$first} is averaging {$s['avg']}% in {$s['subject']} across {$s['tests_count']} ".\Illuminate\Support\Str::plural('test', $s['tests_count']).'.'];
            if ($best) {
                $parts[] = "Strongest topic: {$best['topic']} ({$best['percent']}%).";
            }
            if ($weak && $weak['topic'] !== ($best['topic'] ?? null)) {
                $parts[] = "Needs work on {$weak['topic']} ({$weak['percent']}%) — recommend focused practice there.";
            }
            $subjects[$s['subject']] = implode(' ', $parts);
        }

        $summary = "Over {$periodLabel}, {$first} completed {$stats['overall']['tests']} "
            .\Illuminate\Support\Str::plural('test', $stats['overall']['tests'])
            ." with an overall average of {$avg}% — {$standing}.";
        if ($weakestOverall) {
            $summary .= " The clearest priority is {$weakestOverall['topic']} in {$weakestOverall['subject']} ({$weakestOverall['percent']}%).";
        }

        return ['source' => 'template', 'summary' => $summary, 'subjects' => $subjects];
    }
}
