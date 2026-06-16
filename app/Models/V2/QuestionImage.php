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
        'width',
        'height',
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
            'width'          => 'integer',
            'height'         => 'integer',
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
    /** On-screen size as a fraction of the crop's true pixel width. All crops
     *  share one render DPI, so a single fraction keeps internal label text a
     *  consistent size across diagrams. Tables get a touch more room. */
    private const FRACTION_FIGURE = 0.72;  // "a bit smaller, all one size"
    private const FRACTION_TABLE  = 0.87;  // tables a touch bigger (denser content)

    /** Floor for small figures so a tiny stimulus stays readable next to the
     *  option tiles — but never enlarged past MAX_UPSCALE x its real size, so
     *  genuinely tiny/degenerate crops don't blow up into a blur. */
    private const MIN_FIGURE_PX = 290;
    private const MAX_UPSCALE   = 1.85;

    /** The crop's true pixel width: the cached file dimension when known,
     *  otherwise derived from the bbox (reliable for most figures/tables). */
    private function pixelWidth(): ?float
    {
        if ($this->width) {
            return (float) $this->width;
        }

        $b = $this->bbox;
        if (! is_array($b) || count($b) < 4) {
            return null;
        }
        $widthPt = (float) $b[2] - (float) $b[0];

        return $widthPt > 0 ? $widthPt * 200 / 72 : null;
    }

    /** Target on-screen width in CSS px (uniform fraction of real size, with a
     *  readable floor for small figures), or null. Pair with max-width:100%. */
    public function displayWidth(): ?int
    {
        $pixelWidth = $this->pixelWidth();
        if (! $pixelWidth) {
            return null;
        }

        if ($this->role === 'table') {
            return (int) round($pixelWidth * self::FRACTION_TABLE);
        }

        $scaled = $pixelWidth * self::FRACTION_FIGURE;
        $floor  = min(self::MIN_FIGURE_PX, $pixelWidth * self::MAX_UPSCALE);

        return (int) round(max($scaled, $floor));
    }
}
