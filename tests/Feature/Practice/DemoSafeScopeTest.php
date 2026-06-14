<?php

namespace Tests\Feature\Practice;

use App\Enums\QaStatus;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionVisibility;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSafeScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demosafe_excludes_blocker_failed_hidden_rejected_and_review(): void
    {
        $this->seed(CatalogSeeder::class);
        $subject = Subject::where('slug', 'a-level-physics')->first();
        $paper = Paper::create([
            'subject_id' => $subject->id,
            'source_file' => 'unit/test.json',
            'paper_code' => '9702',
            'session' => 'march',
            'year' => 2024,
            'paper_number' => 1,
            'variant' => '2',
        ]);

        $cases = [
            ['public_pass',                  QuestionVisibility::Public,    QuestionReviewStatus::Pass,               QaStatus::Pass,                true],
            ['public_render_fix',            QuestionVisibility::Public,    QuestionReviewStatus::RenderFix,          QaStatus::RenderFix,           true],
            ['public_acceptable_fallback',   QuestionVisibility::Public,    QuestionReviewStatus::AcceptableFallback, QaStatus::AcceptableFallback,  true],
            ['public_approved',              QuestionVisibility::Public,    QuestionReviewStatus::Approved,           QaStatus::Pass,                true],
            ['blocker',                      QuestionVisibility::Hidden,    QuestionReviewStatus::Rejected,           QaStatus::Blocker,             false],
            ['failed',                       QuestionVisibility::Hidden,    QuestionReviewStatus::Rejected,           QaStatus::Failed,              false],
            ['review_admin_only',            QuestionVisibility::AdminOnly, QuestionReviewStatus::Review,             QaStatus::Review,              false],
            ['rejected',                     QuestionVisibility::Hidden,    QuestionReviewStatus::Rejected,           QaStatus::Failed,              false],
            ['public_but_review_status',     QuestionVisibility::Public,    QuestionReviewStatus::Review,             QaStatus::Pass,                false],
        ];

        $expectedSafe = [];
        foreach ($cases as $i => [$tag, $vis, $review, $qa, $shouldBeSafe]) {
            $q = Question::create([
                'paper_id' => $paper->id,
                'question_number' => $i + 1,
                'question_text' => $tag,
                'visibility' => $vis,
                'review_status' => $review,
                'qa_status' => $qa,
                'needs_review' => false,
                'layout_type' => 'standard',
            ]);
            if ($shouldBeSafe) {
                $expectedSafe[] = $q->id;
            }
        }

        $actualSafeIds = Question::demoSafe()->pluck('id')->sort()->values()->all();
        sort($expectedSafe);

        $this->assertSame($expectedSafe, $actualSafeIds);
    }
}
