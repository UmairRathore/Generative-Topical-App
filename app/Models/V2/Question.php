<?php

namespace App\Models\V2;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasHashid;
    // Questions are never permanently deleted - "delete" soft-deletes (recoverable from Trash).
    use SoftDeletes;

    protected $table = 'v2_questions';

    protected $fillable = [
        'paper_id',
        'subject_id',
        'topic_id',
        'subtopic_id',
        'year',
        'question_number',
        'question_text',
        'text_before',
        'text_after',
        'layout_type',
        'correct_answer',
        'option_table',
        'marks',
        'difficulty',
        'page_start',
        'page_end',
        'needs_review',
        'warnings',
        'source_paper',
        'status',
        'current_version_id',
    ];

    protected function casts(): array
    {
        return [
            'option_table'    => 'array',
            'warnings'        => 'array',
            'needs_review'    => 'boolean',
            'year'            => 'integer',
            'question_number' => 'integer',
            'marks'           => 'integer',
            'page_start'      => 'integer',
            'page_end'        => 'integer',
        ];
    }

    public function paper(): BelongsTo
    {
        return $this->belongsTo(Paper::class, 'paper_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    public function subtopic(): BelongsTo
    {
        return $this->belongsTo(Subtopic::class, 'subtopic_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class, 'question_id')->orderBy('sort_order');
    }

    public function images(): HasMany
    {
        return $this->hasMany(QuestionImage::class, 'question_id')->orderBy('sort_order');
    }

    /** Teacher-submitted "this looks wrong" reports against this question. */
    public function flags(): HasMany
    {
        return $this->hasMany(QuestionFlag::class, 'question_id');
    }

    /** Reusable learning content (worked solutions, explanations, ...) for the Learning Hub. */
    public function learningAssets(): HasMany
    {
        return $this->hasMany(QuestionLearningAsset::class, 'question_id');
    }

    /** Full immutable content history (newest first). */
    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class, 'question_id')->orderByDesc('version_number');
    }

    /** The live/active content version (what new exams freeze + what the editor shows). */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'current_version_id');
    }

    public function openFlags(): HasMany
    {
        return $this->flags()->where('status', 'open');
    }

    /** Only questions usable in a generated test: a known correct answer. */
    public function scopeAnswerable(Builder $query): Builder
    {
        return $query->whereNotNull('correct_answer');
    }

    /** Live questions - the only ones a generated test may draw from. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * The stem broken into ordered render blocks, reconstructing the paper's
     * visual order from the text segments + each diagram's bbox position.
     *
     * Rule: text_before → between diagrams → text_after → after diagrams → table,
     * EXCEPT when two between-diagrams are vertically separated by a text-sized
     * gap - then text_after is placed in that gap (text → diagram → text → diagram).
     * Diagrams that overlap vertically (one figure, e.g. side-by-side) stay together.
     *
     * @return array<int,array{type:string,text?:string,image?:\App\Models\V2\QuestionImage}>
     */
    public function stemBlocks(): array
    {
        $GAP = 15.0; // pt of vertical space that can hold a line of text

        $top = fn ($im) => is_array($im->bbox) ? (float) ($im->bbox[1] ?? 0) : 0.0;
        $bottom = fn ($im) => is_array($im->bbox) ? (float) ($im->bbox[3] ?? 0) : 0.0;
        $byPos = fn ($im) => ($im->page ?? 0) * 100000 + $top($im);
        $strip = fn ($t) => trim(preg_replace('/^\s*\d+\s+/', '', (string) $t));

        $between = $this->images->where('role', 'question_image_between_text')->sortBy($byPos)->values();
        $after   = $this->images->where('role', 'question_image_after_text')->sortBy($byPos)->values();

        $blocks = [];

        if ($between->isNotEmpty() && filled($this->text_before)) {
            // Find the first gap (next diagram starts below previous, same page) big enough for text.
            $splitAt = null;
            for ($i = 1; $i < $between->count(); $i++) {
                $prev = $between[$i - 1];
                $cur = $between[$i];
                $samePage = ($prev->page ?? 0) === ($cur->page ?? 0);
                $gap = $samePage ? ($top($cur) - $bottom($prev)) : 999;
                if ($gap >= $GAP) {
                    $splitAt = $i;
                    break;
                }
            }

            $blocks[] = ['type' => 'text', 'text' => $strip($this->text_before)];

            if ($splitAt !== null && filled($this->text_after)) {
                foreach ($between->slice(0, $splitAt) as $im) {
                    $blocks[] = ['type' => 'figure', 'image' => $im];
                }
                $blocks[] = ['type' => 'text', 'text' => $strip($this->text_after)];
                foreach ($between->slice($splitAt) as $im) {
                    $blocks[] = ['type' => 'figure', 'image' => $im];
                }
            } else {
                foreach ($between as $im) {
                    $blocks[] = ['type' => 'figure', 'image' => $im];
                }
                if (filled($this->text_after)) {
                    $blocks[] = ['type' => 'text', 'text' => $strip($this->text_after)];
                }
            }

            foreach ($after as $im) {
                $blocks[] = ['type' => 'figure', 'image' => $im];
            }
        } else {
            $blocks[] = ['type' => 'text', 'text' => $strip($this->question_text)];
            foreach ($between->merge($after) as $im) {
                $blocks[] = ['type' => 'figure', 'image' => $im];
            }
        }

        return $blocks;
    }

    /** The options/answer table image (option_table layout), or null. */
    public function optionTableImage(): ?QuestionImage
    {
        return $this->images->firstWhere('role', 'table');
    }

    /**
     * Render-time safety net: if this question's option images are actually
     * duplicates of the question figure (a single-figure question mis-tagged as
     * `option_images` that slipped past the importer's dedup), return the
     * distinct figure(s) so the view can show them once and render A/B/C/D as
     * plain labels. Null for genuine per-option pictures. The canonical repair is
     * the v2:fix-duplicate-option-images command - this just keeps the page sane
     * if an unfixed question is ever served.
     *
     * @return \Illuminate\Support\Collection<int,QuestionImage>|null
     */
    public function collapsedOptionFigures(): ?\Illuminate\Support\Collection
    {
        return app(\App\Services\V2\DuplicateOptionImageFixer::class)->optionFiguresToShow($this);
    }
}
