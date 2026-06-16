<?php

namespace App\Models\V2;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionImage extends Model
{
    protected $table = 'v2_question_images';

    protected $fillable = [
        'question_id',
        'external_id',
        'image_path',
        'role',
        'option_label',
        'page',
        'bbox',
        'caption',
        'ocr_text',
        'confidence',
        'diagram_labels',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'bbox'           => 'array',
            'diagram_labels' => 'array',
            'page'           => 'integer',
            'confidence'     => 'float',
            'sort_order'     => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Display sizing
    |--------------------------------------------------------------------------
    | Every crop is rendered from the source PDF at the SAME 200 DPI, and its
    | `bbox` is stored in PDF points (72 pt = 1 in). So a single CSS-px-per-point
    | scale applied to every image gives a CONSTANT on-screen size for the
    | internal label text, no matter how large the original diagram was — small
    | and large diagrams shrink/grow together instead of each being squeezed
    | into a fixed box (which makes the labels vary). Callers pair the returned
    | width with `max-width:100%` so a diagram wider than its column still fits.
    */
    private const PX_PER_PT_FIGURE = 2.05;  // ~0.74x native — "a bit smaller, all one size"
    private const PX_PER_PT_TABLE  = 2.45;  // tables a touch bigger (denser content)

    /** Target on-screen width in CSS px (uniform scale), or null if no bbox. */
    public function displayWidth(): ?int
    {
        $b = $this->bbox;
        if (! is_array($b) || count($b) < 4) {
            return null;
        }

        $widthPt = (float) $b[2] - (float) $b[0];
        if ($widthPt <= 0) {
            return null;
        }

        $scale = $this->role === 'table' ? self::PX_PER_PT_TABLE : self::PX_PER_PT_FIGURE;

        return (int) round($widthPt * $scale);
    }
}
