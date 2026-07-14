<?php

namespace App\Services\V2;

use App\Models\V2\AuthoringReviewFeedback;
use App\Models\V2\ReviewSignoff;
use App\Models\V2\SyllabusSource;
use InvalidArgumentException;
use RuntimeException;

/**
 * Stage B human-review domain actions, shared by the Super Admin UI and the
 * CLI (no shell-exec from web controllers; both call this service).
 *
 * Approval binds the EXACT lesson artifact SHA + the approved Stage A plan SHA
 * + the foundation fingerprint + the syllabus source. Any lesson edit stales
 * only the Stage B approval; a Stage A plan change or canonical-truth change
 * stales it too (checked hash-by-hash at read time - records are immutable).
 *
 * "Needs Revision" creates an additive AuthoringReviewFeedback record bound to
 * the reviewed lesson SHA. It NEVER mutates the lesson artifact: the offline
 * authoring workflow reads the feedback and produces a new artifact/SHA.
 */
class StageBReviewService
{
    public const FEEDBACK_CATEGORIES = [
        'academic_accuracy', 'pedagogy', 'explanation_clarity', 'cognitive_load',
        'widget_interaction', 'formative_check', 'misconception_handling',
        'visual_presentation', 'scope_depth', 'other',
    ];

    public function __construct(private readonly AuthoringArtifactReader $reader) {}

    /**
     * Approve the exact artifact. $expectedLessonSha is the hash the reviewer
     * saw on screen - a concurrent artifact change fails the approval.
     */
    public function approveExact(string $artifactId, string $expectedLessonSha, string $reviewer, ?string $notes = null): ReviewSignoff
    {
        $artifact = $this->reader->load($artifactId);

        if (! hash_equals($artifact['hashes']['lesson_sha256'], $expectedLessonSha)) {
            throw new RuntimeException('Artifact changed since it was loaded for review - reload and re-review before approving.');
        }
        if ($artifact['validation']['blocking'] !== []) {
            throw new RuntimeException('Deterministic Stage B validation has blocking violations - approval refused.');
        }
        if ($artifact['approvals']['foundation']['status'] !== 'valid') {
            throw new RuntimeException('Academic-foundation approval is not VALID - approve the foundation first.');
        }
        if ($artifact['approvals']['plan']['status'] !== 'valid') {
            throw new RuntimeException('Stage A plan approval is not VALID - Stage B cannot be approved against an unapproved plan.');
        }
        if ($artifact['approvals']['lesson']['status'] === 'valid') {
            throw new RuntimeException('This exact lesson artifact is already approved.');
        }
        if (trim($reviewer) === '') {
            throw new InvalidArgumentException('Reviewer identity is required (the authenticated user).');
        }

        $packet = $artifact['packet'];
        $source = SyllabusSource::resolveReference(
            $packet['identity']['syllabus_code'].'@'.$packet['identity']['syllabus_version']
        );
        if (! $source) {
            throw new RuntimeException('Packet syllabus source is not registered.');
        }

        return ReviewSignoff::create([
            'syllabus_source_id' => $source->id,
            'scope' => $artifact['scopes']['lesson'],
            'reviewer' => $reviewer,
            'artifact_path' => $artifact['dir'],
            'packet_sha256' => $artifact['hashes']['packet_sha256'],
            'foundation_fingerprint' => $artifact['hashes']['foundation_fingerprint'],
            'plan_sha256' => $artifact['hashes']['plan_sha256'],
            'lesson_sha256' => $artifact['hashes']['lesson_sha256'],
            'notes' => $notes,
            'signed_at' => now(),
        ]);
    }

    /**
     * Record structured Needs Revision feedback (additive; read-only towards
     * the artifact). Items may target a phase/block - targets must exist.
     */
    public function requestRevision(string $artifactId, string $reviewer, array $payload): AuthoringReviewFeedback
    {
        $artifact = $this->reader->load($artifactId);

        $severity = $payload['severity'] ?? null;
        if (! in_array($severity, ['minor', 'major'], true)) {
            throw new InvalidArgumentException("severity must be 'minor' or 'major'.");
        }
        if (trim((string) ($payload['overall_note'] ?? '')) === '') {
            throw new InvalidArgumentException('An overall review note is required.');
        }
        if (trim($reviewer) === '') {
            throw new InvalidArgumentException('Reviewer identity is required (the authenticated user).');
        }

        $phaseIds = [];
        $blockIds = [];
        foreach ($artifact['lesson']['phases'] ?? [] as $phase) {
            $phaseIds[] = $phase['phase_id'] ?? null;
            foreach ($phase['blocks'] ?? [] as $block) {
                $blockIds[] = $block['block_id'] ?? null;
            }
        }

        $items = [];
        foreach ($payload['items'] ?? [] as $i => $item) {
            if (! in_array($item['category'] ?? null, self::FEEDBACK_CATEGORIES, true)) {
                throw new InvalidArgumentException("items[{$i}].category is not a supported feedback category.");
            }
            if (! in_array($item['severity'] ?? 'minor', ['minor', 'major'], true)) {
                throw new InvalidArgumentException("items[{$i}].severity must be 'minor' or 'major'.");
            }
            if (trim((string) ($item['note'] ?? '')) === '') {
                throw new InvalidArgumentException("items[{$i}].note is required.");
            }
            if (! empty($item['phase_id']) && ! in_array($item['phase_id'], $phaseIds, true)) {
                throw new InvalidArgumentException("items[{$i}].phase_id '{$item['phase_id']}' does not exist in the lesson.");
            }
            if (! empty($item['block_id']) && ! in_array($item['block_id'], $blockIds, true)) {
                throw new InvalidArgumentException("items[{$i}].block_id '{$item['block_id']}' does not exist in the lesson.");
            }
            $items[] = [
                'severity' => $item['severity'] ?? 'minor',
                'category' => $item['category'],
                'phase_id' => $item['phase_id'] ?? null,
                'block_id' => $item['block_id'] ?? null,
                'note' => trim((string) $item['note']),
            ];
        }

        return AuthoringReviewFeedback::create([
            'artifact_id' => $artifactId,
            'lesson_sha256' => $artifact['hashes']['lesson_sha256'],
            'scope' => $artifact['scopes']['lesson'],
            'reviewer' => $reviewer,
            'severity' => $severity,
            'overall_note' => trim((string) $payload['overall_note']),
            'items' => $items,
        ]);
    }
}
