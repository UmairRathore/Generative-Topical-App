<?php

namespace App\Services\V2;

use App\Models\V2\AuthoringReviewFeedback;
use Throwable;

/**
 * Safe index of the private offline authoring artifacts (Stage A plans and
 * Stage B lessons) for the Super Admin review queue. Only immediate child
 * directories of the authoring root with parseable metadata.json are indexed;
 * ids are directory basenames validated by AuthoringArtifactReader - the
 * browser never supplies a filesystem path. Malformed or unsupported
 * directories are skipped with a reason, never trusted.
 */
class AuthoringArtifactCatalog
{
    public function __construct(private readonly AuthoringArtifactReader $reader) {}

    /** @return array{artifacts: array<int, array>, skipped: array<int, array>} */
    public function index(): array
    {
        $root = $this->reader->root();
        $rootReal = realpath($root);
        $artifacts = [];
        $skipped = [];
        if ($rootReal === false) {
            return ['artifacts' => [], 'skipped' => []];
        }

        foreach (scandir($rootReal) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (! preg_match(AuthoringArtifactReader::ID_PATTERN, $entry)) {
                $skipped[] = ['id' => $entry, 'reason' => 'unsafe id'];

                continue;
            }
            try {
                $dir = $this->reader->resolveDirectory($entry);
            } catch (Throwable) {
                $skipped[] = ['id' => $entry, 'reason' => 'not a safe artifact directory'];

                continue;
            }
            try {
                $artifacts[] = $this->entry($entry, $dir);
            } catch (Throwable $e) {
                $skipped[] = ['id' => $entry, 'reason' => $e->getMessage()];
            }
        }

        usort($artifacts, fn ($a, $b) => strcmp((string) ($b['generated_at'] ?? ''), (string) ($a['generated_at'] ?? '')));

        return ['artifacts' => $artifacts, 'skipped' => $skipped];
    }

    private function entry(string $id, string $dir): array
    {
        $metadata = $this->reader->readJson($dir, 'metadata.json')['json'];
        $lessonFile = $this->reader->lessonFile($dir);
        $stage = $lessonFile !== null ? 'stage_b' : (is_file($dir.DIRECTORY_SEPARATOR.'plan.json') ? 'stage_a' : null);
        if ($stage === null) {
            throw new \RuntimeException('no reviewable artifact document');
        }

        if ($stage === 'stage_a') {
            $sectionCode = $metadata['leaf_section']['section_code'] ?? $this->sectionCodeFallback($metadata, $id);
            $sectionTitle = $metadata['leaf_section']['title'] ?? null;

            return [
                'id' => $id,
                'stage' => 'stage_a',
                'reviewable' => false,
                'title' => $metadata['lesson_title'] ?? $metadata['title'] ?? $id,
                'subject' => $metadata['subject'] ?? null,
                'syllabus_code' => $metadata['syllabus_code'] ?? (preg_match('/(\d{4})_\d{4}_\d{4}$/', $id, $m) ? $m[1] : null),
                'syllabus_version' => $metadata['syllabus_version'] ?? null,
                'section' => trim(($sectionCode ?? '').' '.($sectionTitle ?? '')) ?: null,
                'section_code' => $sectionCode,
                'topic_code' => $this->topicCode($sectionCode),
                'target_los' => $metadata['target_los'] ?? [],
                'generated_at' => $metadata['generated_at'] ?? null,
                'authoring_mode' => $metadata['authoring_mode'] ?? null,
            ];
        }

        $artifact = $this->reader->load($id);
        $lesson = $artifact['lesson'];
        $identity = $lesson['identity'] ?? [];
        $approvals = $artifact['approvals'];

        $latestFeedback = AuthoringReviewFeedback::where('artifact_id', $id)->orderByDesc('created_at')->first();
        $humanReview = match (true) {
            $approvals['lesson']['status'] === 'valid' => 'approved',
            $approvals['lesson']['status'] === 'stale' => 'approval_stale',
            $latestFeedback && $latestFeedback->lesson_sha256 === $artifact['hashes']['lesson_sha256'] => 'needs_revision',
            default => 'pending_review',
        };

        return [
            'id' => $id,
            'stage' => 'stage_b',
            'reviewable' => true,
            'title' => $identity['lesson_title'] ?? $id,
            'subject' => $identity['subject'] ?? null,
            'syllabus_code' => $identity['syllabus_code'] ?? null,
            'level' => $identity['level'] ?? null,
            'syllabus_version' => $identity['syllabus_version'] ?? null,
            'section' => trim(($identity['leaf_section']['section_code'] ?? '').' '.($identity['leaf_section']['title'] ?? '')) ?: null,
            'section_code' => $identity['leaf_section']['section_code'] ?? null,
            'topic_code' => $this->topicCode($identity['leaf_section']['section_code'] ?? null),
            'target_los' => $identity['target_los'] ?? [],
            'schema' => $lesson['lesson_schema'] ?? null,
            'lesson_sha256' => $artifact['hashes']['lesson_sha256'],
            'packet_sha256' => $artifact['hashes']['packet_sha256'],
            'plan_sha256' => $artifact['hashes']['plan_sha256'],
            'foundation_fingerprint' => $artifact['hashes']['foundation_fingerprint'],
            'validation' => [
                'blocking' => count($artifact['validation']['blocking']),
                'warnings' => count($artifact['validation']['warnings']),
            ],
            'approvals' => [
                'foundation' => $approvals['foundation']['status'],
                'plan' => $approvals['plan']['status'],
                'lesson' => $approvals['lesson']['status'],
            ],
            'human_review' => $humanReview,
            'generated_at' => $metadata['generated_at'] ?? null,
            'authoring_mode' => $metadata['authoring_mode'] ?? null,
            'authoring_model' => $metadata['model'] ?? $metadata['authoring_model'] ?? null,
        ];
    }

    /**
     * Best-effort section code for records whose metadata carries no leaf_section
     * (e.g. legacy migration records): try the first target LO ("1.2 #7" → "1.2"),
     * then the artifact id ("..._1.2_5054_..." → "1.2"). Null if nothing matches.
     */
    private function sectionCodeFallback(array $metadata, string $id): ?string
    {
        $los = $metadata['target_los'] ?? [];
        if (is_array($los) && isset($los[0]) && preg_match('/^(\d+(?:\.\d+)*)/', (string) $los[0], $m)) {
            return $m[1];
        }
        if (preg_match('/_(\d+(?:\.\d+)+)_5054/', $id, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Leading integer of a dotted section code ("3.2.2" → "3"); null when absent. */
    private function topicCode(?string $sectionCode): ?string
    {
        if ($sectionCode === null || $sectionCode === '') {
            return null;
        }
        $head = explode('.', $sectionCode)[0];

        return ctype_digit($head) ? $head : null;
    }
}
