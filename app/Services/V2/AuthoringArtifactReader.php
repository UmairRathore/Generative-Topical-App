<?php

namespace App\Services\V2;

use App\Models\V2\AuthoringReviewFeedback;
use App\Models\V2\ReviewSignoff;
use InvalidArgumentException;
use RuntimeException;

/**
 * Loads ONE private offline authoring artifact for Super Admin review and
 * produces a reviewer-safe view model. The browser only ever supplies a safe
 * artifact id (directory basename); this class resolves it INSIDE the private
 * authoring root and rejects traversal, symlink escapes and malformed
 * metadata. Filesystem, hashing and approval-state logic live here - never in
 * controllers or React.
 *
 * Leakage boundary: the student-preview lesson payload strips every formative
 * check's evaluation envelope down to {mode, server_only} - answer keys,
 * tolerances and authored feedback exist only in the reviewer payload, so a
 * future student renderer reusing the same lesson document cannot inherit
 * correct answers.
 */
class AuthoringArtifactReader
{
    public const ID_PATTERN = '/^[a-z0-9][a-z0-9_.-]{0,100}$/i';

    /** Lesson file preference order inside a Stage B artifact directory. */
    public const LESSON_FILES = ['revised_lesson.json', 'lesson.json', 'initial_lesson.json'];

    public function __construct(
        private readonly StageBLessonValidator $validator,
        private ?string $root = null,
    ) {
        $this->root = $root ?? storage_path('app/authoring_experiments');
    }

    public function root(): string
    {
        return $this->root;
    }

    /** Resolve a safe artifact id to its real directory, or throw. */
    public function resolveDirectory(string $artifactId): string
    {
        if (! preg_match(self::ID_PATTERN, $artifactId) || str_contains($artifactId, '..')) {
            throw new InvalidArgumentException('Invalid artifact id.');
        }
        $rootReal = realpath($this->root);
        $dirReal = realpath($this->root.DIRECTORY_SEPARATOR.$artifactId);
        if ($rootReal === false || $dirReal === false || ! is_dir($dirReal)) {
            throw new InvalidArgumentException('Unknown artifact.');
        }
        if ($dirReal !== $rootReal && ! str_starts_with($dirReal, $rootReal.DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Artifact escapes the authoring root.');
        }
        if ($dirReal === $rootReal) {
            throw new InvalidArgumentException('Unknown artifact.');
        }

        return $dirReal;
    }

    /** @return array{path: string, json: array, sha256: string} */
    public function readJson(string $dir, string $file): array
    {
        $path = $dir.DIRECTORY_SEPARATOR.$file;
        if (! is_file($path)) {
            throw new RuntimeException("Artifact file missing: {$file}.");
        }
        $raw = file_get_contents($path);
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            throw new RuntimeException("Artifact file malformed: {$file}.");
        }

        return ['path' => $path, 'json' => $json, 'sha256' => hash('sha256', $raw)];
    }

    public function lessonFile(string $dir): ?string
    {
        foreach (self::LESSON_FILES as $file) {
            if (is_file($dir.DIRECTORY_SEPARATOR.$file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Load a full Stage B artifact: lesson + packet + plan + metadata, hashes,
     * live deterministic validation and approval state.
     */
    public function load(string $artifactId): array
    {
        $dir = $this->resolveDirectory($artifactId);

        $metadata = $this->readJson($dir, 'metadata.json');
        $lessonFile = $this->lessonFile($dir);
        if ($lessonFile === null) {
            throw new RuntimeException('Artifact has no lesson document - not a Stage B artifact.');
        }
        $lesson = $this->readJson($dir, $lessonFile);
        $packet = $this->readJson($dir, 'packet.json');
        $plan = $this->readJson($dir, 'approved_stage_a_plan.json');

        $schema = $lesson['json']['lesson_schema'] ?? null;
        if ($schema !== StageBLessonValidator::LESSON_SCHEMA) {
            throw new RuntimeException("Unsupported lesson schema '{$schema}'.");
        }

        $validation = $this->validator->validate($lesson['json'], $packet['json'], $plan['json'], [
            'packet_sha256' => $packet['sha256'],
            'plan_sha256' => $plan['sha256'],
            'verify_approvals' => true,
        ]);

        $fingerprint = ReviewSignoff::foundationFingerprint($packet['json']);
        $foundationScope = ReviewSignoff::scopeFor($packet['json']);
        $suffix = substr($foundationScope, strlen('stage-a-foundation:'));

        return [
            'id' => $artifactId,
            'dir' => $dir,
            'lesson_file' => $lessonFile,
            'metadata' => $metadata['json'],
            'lesson' => $lesson['json'],
            'packet' => $packet['json'],
            'plan' => $plan['json'],
            'hashes' => [
                'lesson_sha256' => $lesson['sha256'],
                'packet_sha256' => $packet['sha256'],
                'plan_sha256' => $plan['sha256'],
                'foundation_fingerprint' => $fingerprint,
                'source_pdf_sha256' => $packet['json']['identity']['source_pdf_sha256'] ?? null,
            ],
            'scopes' => [
                'foundation' => $foundationScope,
                'plan' => 'stage-a-plan:'.$suffix,
                'lesson' => 'stage-b-lesson:'.$suffix,
            ],
            'validation' => $validation,
            'approvals' => $this->approvalStates($foundationScope, $suffix, $fingerprint, $plan['sha256'], $lesson['sha256']),
        ];
    }

    /** Foundation / Stage A plan / Stage B lesson approval states, each hash-verified. */
    public function approvalStates(string $foundationScope, string $suffix, string $fingerprint, string $planSha, string $lessonSha): array
    {
        $state = function (?ReviewSignoff $signoff, array $checks): array {
            if (! $signoff) {
                return ['status' => 'none', 'reviewer' => null, 'signed_at' => null];
            }
            foreach ($checks as $reason => $ok) {
                if (! $ok) {
                    return ['status' => 'stale', 'reason' => $reason, 'reviewer' => $signoff->reviewer, 'signed_at' => (string) $signoff->signed_at];
                }
            }

            return ['status' => 'valid', 'reviewer' => $signoff->reviewer, 'signed_at' => (string) $signoff->signed_at];
        };

        $foundation = ReviewSignoff::where('scope', $foundationScope)->orderByDesc('signed_at')->orderByDesc('id')->first();
        $plan = ReviewSignoff::where('scope', 'stage-a-plan:'.$suffix)->orderByDesc('signed_at')->orderByDesc('id')->first();
        $lesson = ReviewSignoff::where('scope', 'stage-b-lesson:'.$suffix)->orderByDesc('signed_at')->orderByDesc('id')->first();

        return [
            'foundation' => $state($foundation, ['foundation_changed' => $foundation?->foundation_fingerprint === $fingerprint]),
            'plan' => $state($plan, [
                'plan_changed' => $plan?->plan_sha256 === $planSha,
                'foundation_changed' => $plan?->foundation_fingerprint === $fingerprint,
            ]),
            'lesson' => $state($lesson, [
                'lesson_changed' => $lesson?->lesson_sha256 === $lessonSha,
                'plan_changed' => $lesson?->plan_sha256 === $planSha,
                'foundation_changed' => $lesson?->foundation_fingerprint === $fingerprint,
            ]),
        ];
    }

    /**
     * The lesson document as a future learner would receive it: every formative
     * check's evaluation envelope is reduced to {mode, server_only} - no answer
     * keys, tolerances or authored feedback.
     */
    public function studentLesson(array $lesson): array
    {
        foreach ($lesson['phases'] ?? [] as $pi => $phase) {
            foreach ($phase['blocks'] ?? [] as $bi => $block) {
                if (($block['type'] ?? null) === 'formative_check') {
                    $lesson['phases'][$pi]['blocks'][$bi]['evaluation'] = [
                        'mode' => $block['evaluation']['mode'] ?? null,
                        'server_only' => true,
                    ];
                }
            }
        }

        return $lesson;
    }

    /** Reviewer-only payload: evaluation data per check + provenance the shell overlays. */
    public function reviewerData(array $artifact): array
    {
        $evaluations = [];
        foreach ($artifact['lesson']['phases'] ?? [] as $phase) {
            foreach ($phase['blocks'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'formative_check' && isset($block['check_id'])) {
                    $evaluations[$block['check_id']] = $block['evaluation'] ?? null;
                }
            }
        }

        $evidence = collect($artifact['packet']['assessment_evidence']['questions'] ?? [])->map(fn ($q) => [
            'evidence_id' => $q['evidence_id'] ?? null,
            'question' => $q['question'] ?? null,
            'year' => $q['year'] ?? null,
            'classification' => $q['classification'] ?? null,
            'compatibility_scope' => $q['compatibility']['scope'] ?? null,
            'compatibility_decision' => $q['compatibility']['decision'] ?? null,
        ])->values()->all();

        return [
            'evaluations' => $evaluations,
            'stage_a_plan' => $artifact['plan'],
            'evidence' => $evidence,
            'reference_handles' => $artifact['packet']['allowed_reference_handles'] ?? [],
            'misconceptions' => collect($artifact['packet']['pedagogical_evidence']['misconceptions'] ?? [])
                ->map(fn ($m) => collect($m)->only(['id', 'statement', 'affected_target_los', 'provenance_type', 'priority'])->all())
                ->values()->all(),
        ];
    }

    /** Review timeline: metadata events + additive feedback + hash-bound approvals. */
    public function history(array $artifact): array
    {
        $events = [];
        if ($generated = $artifact['metadata']['generated_at'] ?? null) {
            $events[] = ['at' => $generated, 'event' => 'generated', 'detail' => $artifact['metadata']['authoring_mode'] ?? 'offline authoring'];
        }
        if ($validated = $artifact['metadata']['validated_at'] ?? null) {
            $events[] = ['at' => $validated, 'event' => 'structurally_validated', 'detail' => 'deterministic Stage B validation'];
        }
        foreach (AuthoringReviewFeedback::where('artifact_id', $artifact['id'])->orderBy('created_at')->get() as $feedback) {
            $events[] = [
                'at' => (string) $feedback->created_at,
                'event' => 'revision_requested',
                'detail' => "{$feedback->reviewer} ({$feedback->severity}): {$feedback->overall_note}",
                'stale' => $feedback->lesson_sha256 !== $artifact['hashes']['lesson_sha256'],
            ];
        }
        foreach (ReviewSignoff::where('scope', $artifact['scopes']['lesson'])->orderBy('signed_at')->get() as $signoff) {
            $events[] = [
                'at' => (string) $signoff->signed_at,
                'event' => 'approved',
                'detail' => "{$signoff->reviewer} approved lesson ".substr((string) $signoff->lesson_sha256, 0, 12),
                'stale' => $signoff->lesson_sha256 !== $artifact['hashes']['lesson_sha256'],
            ];
        }
        usort($events, fn ($a, $b) => strcmp((string) $a['at'], (string) $b['at']));

        return $events;
    }
}
