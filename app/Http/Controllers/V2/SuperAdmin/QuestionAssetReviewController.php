<?php

namespace App\Http\Controllers\V2\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\V2\Question;
use App\Models\V2\QuestionLearningAsset;
use App\Support\LearningAssetValidator;
use App\Support\SignedImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Super-admin review + approval of AI-generated learning assets. Generation
 * happens OUTSIDE the app (Claude Code / workflow) and writes `draft` assets;
 * this is where a super-admin inspects them against the original question and
 * approves / edits / rejects / hides each one. Students only ever see the
 * statuses in QuestionLearningAsset::VISIBLE_STATUSES.
 */
class QuestionAssetReviewController extends Controller
{
    private const ORDER = "FIELD(asset_type,'worked_solution','option_explanation','interactive_widget','flashcards','memcards','mermaid','revision_notes','common_mistakes')";

    /** The review studio for one question — shows the question + every asset, any status. */
    public function review(Question $question, Request $request)
    {
        $question->load('images');

        // Return to the exact (possibly filtered) question-bank list the admin came
        // from. Only accept a same-path URL to avoid an open redirect.
        $indexUrl = route('v2.super_admin.question_bank.index');
        $return = (string) $request->query('return', '');
        $backUrl = ($return !== '' && str_starts_with($return, $indexUrl)) ? $return : $indexUrl;

        $snapshot = json_decode(optional(DB::table('v2_question_versions')->where('id', $question->current_version_id)->first())->snapshot ?? 'null', true);
        $options = collect($snapshot['options'] ?? [])->map(fn ($o) => [
            'label'   => $o['label'] ?? null,
            'text'    => $o['text'] ?? null,
            'correct' => ($o['label'] ?? null) === $question->correct_answer,
        ])->values();

        $figures = $question->images
            ->filter(fn ($im) => $im->option_label === null && preg_match('/question|diagram|figure/i', (string) $im->role))
            ->sortBy('sort_order')
            ->map(fn ($im) => ['url' => SignedImage::url($im->image_path, ['question_id' => $question->id]), 'caption' => $im->caption])
            ->values();

        $assets = QuestionLearningAsset::where('question_id', $question->id)
            ->orderByRaw(self::ORDER)
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'type'        => $a->asset_type,
                'title'       => $a->title,
                'content'     => $a->content,
                'payload'     => $a->payload_json,
                'format'      => $a->format,
                'status'      => $a->status,
                'generatedBy' => $a->generated_by,
                'warnings'    => LearningAssetValidator::warnings($a, $question->correct_answer),
                'urls'        => [
                    'status' => route('v2.super_admin.learning_assets.status', $a),
                    'update' => route('v2.super_admin.learning_assets.update', $a),
                ],
            ]);

        return Inertia::render('AssetReview', [
            'question' => [
                'ref'     => $question->source_paper . ' · Q' . $question->question_number,
                'stem'    => $question->question_text,
                'correct' => $question->correct_answer,
                'options' => $options,
                'images'  => $figures,
            ],
            'assets' => $assets,
            'urls'   => [
                'approveAll' => route('v2.super_admin.learning_assets.approve_all', $question),
                'back'       => $backUrl,
            ],
        ]);
    }

    /** approve | reject | hide | reset (back to pending) one asset. */
    public function updateStatus(QuestionLearningAsset $asset, Request $request)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject', 'hide', 'reset'])]]);
        $uid = (string) auth('v2_super_admin')->id();
        $now = now();

        match ($data['action']) {
            'approve' => $asset->update(['status' => QuestionLearningAsset::STATUS_APPROVED, 'reviewed_by' => $uid, 'reviewed_at' => $now, 'approved_at' => $now]),
            'reject'  => $asset->update(['status' => QuestionLearningAsset::STATUS_REJECTED, 'reviewed_by' => $uid, 'reviewed_at' => $now]),
            'hide'    => $asset->update(['status' => QuestionLearningAsset::STATUS_HIDDEN, 'reviewed_by' => $uid, 'reviewed_at' => $now]),
            'reset'   => $asset->update(['status' => QuestionLearningAsset::STATUS_DRAFT, 'approved_at' => null]),
        };

        return back()->with('success', 'Asset ' . $data['action'] . 'd.');
    }

    /** Edit an asset's text/payload → marks it edited_by_superadmin (student-visible). */
    public function update(QuestionLearningAsset $asset, Request $request)
    {
        $data = $request->validate([
            'title'        => ['nullable', 'string', 'max:255'],
            'content'      => ['nullable', 'string'],
            'payload_json' => ['nullable'],
        ]);

        $update = [
            'status'      => QuestionLearningAsset::STATUS_EDITED,
            'reviewed_by' => (string) auth('v2_super_admin')->id(),
            'reviewed_at' => now(),
            'approved_at' => now(),
        ];
        if (array_key_exists('title', $data)) { $update['title'] = $data['title']; }
        if (array_key_exists('content', $data)) { $update['content'] = $data['content']; }
        if (! empty($data['payload_json'])) {
            $payload = is_string($data['payload_json']) ? json_decode($data['payload_json'], true) : $data['payload_json'];
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['payload_json' => 'Invalid JSON: ' . json_last_error_msg()]);
            }
            $update['payload_json'] = $payload;
        }

        $asset->update($update);

        return back()->with('success', 'Asset saved and marked edited.');
    }

    /** Approve every still-pending asset for the question in one click. */
    public function approveAll(Question $question)
    {
        $uid = (string) auth('v2_super_admin')->id();
        $now = now();
        QuestionLearningAsset::where('question_id', $question->id)
            ->whereIn('status', QuestionLearningAsset::PENDING_STATUSES)
            ->update(['status' => QuestionLearningAsset::STATUS_APPROVED, 'reviewed_by' => $uid, 'reviewed_at' => $now, 'approved_at' => $now]);

        return back()->with('success', 'All pending assets approved.');
    }
}
