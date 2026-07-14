<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\V2\AuthoringArtifactCatalog;
use App\Services\V2\AuthoringArtifactReader;
use App\Services\V2\StageBReviewService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use InvalidArgumentException;
use RuntimeException;

/**
 * Super Admin Authoring Review Studio - the visual human-review surface for
 * offline-authored Stage B lesson artifacts. Read-only towards the artifacts:
 * approval and structured revision feedback are additive records via
 * StageBReviewService; the UI never edits lesson JSON. The browser only ever
 * sends a safe artifact id - path resolution stays in AuthoringArtifactReader.
 */
class AuthoringReviewController extends Controller
{
    public function __construct(
        private readonly AuthoringArtifactCatalog $catalog,
        private readonly AuthoringArtifactReader $reader,
        private readonly StageBReviewService $review,
    ) {}

    /** The review queue: every reviewable authoring artifact, pending first. */
    public function index(Request $request)
    {
        $index = $this->catalog->index();
        $artifacts = array_map(function (array $artifact) {
            $artifact['review_url'] = $artifact['reviewable']
                ? route('v2.super_admin.authoring.review', $artifact['id'])
                : null;

            return $artifact;
        }, $index['artifacts']);

        return Inertia::render('Authoring/Queue', [
            'artifacts' => $artifacts,
            'skipped' => $index['skipped'],
            'filters' => $request->only(['stage', 'status']),
            'adminUrl' => route('v2.super_admin.dashboard'),
        ]);
    }

    /** The visual review screen for one Stage B artifact. */
    public function review(string $artifact)
    {
        try {
            $loaded = $this->reader->load($artifact);
        } catch (InvalidArgumentException|RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        $identity = $loaded['lesson']['identity'] ?? [];

        return Inertia::render('Authoring/ReviewLesson', [
            // Student-preview payload: evaluation data stripped server-side.
            'lesson' => $this->reader->studentLesson($loaded['lesson']),
            'meta' => [
                'artifact_id' => $loaded['id'],
                'lesson_file' => $loaded['lesson_file'],
                'title' => $identity['lesson_title'] ?? $loaded['id'],
                'subject' => $identity['subject'] ?? null,
                'syllabus_code' => $identity['syllabus_code'] ?? null,
                'level' => $identity['level'] ?? null,
                'syllabus_version' => $identity['syllabus_version'] ?? null,
                'section' => $identity['leaf_section'] ?? null,
                'target_los' => $identity['target_los'] ?? [],
                'schema' => $loaded['lesson']['lesson_schema'] ?? null,
                'generated_at' => $loaded['metadata']['generated_at'] ?? null,
                'authoring_mode' => $loaded['metadata']['authoring_mode'] ?? null,
                'authoring_model' => $loaded['metadata']['model'] ?? null,
                'hashes' => $loaded['hashes'],
            ],
            'validation' => $loaded['validation'],
            'approvals' => $loaded['approvals'],
            // Reviewer-mode payload (this whole route is Super Admin-gated).
            'reviewer' => $this->reader->reviewerData($loaded),
            'history' => $this->reader->history($loaded),
            'feedbackCategories' => StageBReviewService::FEEDBACK_CATEGORIES,
            'urls' => [
                'back' => route('v2.super_admin.authoring.index'),
                'approve' => route('v2.super_admin.authoring.approve', $artifact),
                'feedback' => route('v2.super_admin.authoring.feedback', $artifact),
            ],
        ]);
    }

    /** Approve the exact artifact SHA. Reviewer = the authenticated Super Admin. */
    public function approve(string $artifact, Request $request)
    {
        $data = $request->validate([
            'lesson_sha256' => ['required', 'string', 'size:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $admin = $request->user('v2_super_admin');

        try {
            $this->review->approveExact($artifact, $data['lesson_sha256'], "{$admin->name} <{$admin->email}>", $data['notes'] ?? null);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['approve' => $e->getMessage()]);
        }

        return back()->with('status', 'Exact artifact approved.');
    }

    /** Structured Needs Revision feedback - additive record, never edits the lesson. */
    public function feedback(string $artifact, Request $request)
    {
        $data = $request->validate([
            'severity' => ['required', Rule::in(['minor', 'major'])],
            'overall_note' => ['required', 'string', 'max:4000'],
            'items' => ['array'],
            'items.*.severity' => ['required', Rule::in(['minor', 'major'])],
            'items.*.category' => ['required', Rule::in(StageBReviewService::FEEDBACK_CATEGORIES)],
            'items.*.phase_id' => ['nullable', 'string', 'max:120'],
            'items.*.block_id' => ['nullable', 'string', 'max:120'],
            'items.*.note' => ['required', 'string', 'max:2000'],
        ]);

        $admin = $request->user('v2_super_admin');

        try {
            $this->review->requestRevision($artifact, "{$admin->name} <{$admin->email}>", $data);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['feedback' => $e->getMessage()]);
        }

        return back()->with('status', 'Revision feedback recorded.');
    }
}
