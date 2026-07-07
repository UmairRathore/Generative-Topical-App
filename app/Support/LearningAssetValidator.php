<?php

namespace App\Support;

use App\Models\V2\QuestionLearningAsset;

/**
 * Lightweight, structural validation of a generated learning asset. Returns a
 * list of human-readable warnings for the super-admin review screen — it never
 * blocks; it just flags things worth a look before approval.
 */
class LearningAssetValidator
{
    /** Widget types that actually have a rendered React component today. */
    public const KNOWN_WIDGETS = ['calculator', 'beam_balance', 'moments_beam', 'graph_explorer', 'ultrasound_echo', 'circular_motion', 'ray_diagram', 'circuit_network', 'force_vectors', 'pressure_area', 'liquid_pressure', 'fleming_lhr', 'mirror_view', 'lens_rays', 'ac_generator', 'reflection_angle', 'critical_angle', 'graph_regions', 'charged_deflection', 'gas_cylinder', 'states_of_matter', 'heat_transfer', 'potential_divider', 'dc_motor', 'transformer', 'vector_resultant', 'refraction_block', 'inclined_plane', 'scene', 'photosynthesis_lake'];

    public static function warnings(QuestionLearningAsset $asset, ?string $answerKey = null): array
    {
        $w = [];
        $payload = $asset->payload_json ?? [];
        $content = (string) $asset->content;

        switch ($asset->asset_type) {
            case 'worked_solution':
            case 'revision_notes':
            case 'common_mistakes':
                if (trim($content) === '') { $w[] = 'Empty content.'; }
                elseif (mb_strlen($content) < 40) { $w[] = 'Very short — check it is complete.'; }
                break;

            case 'option_explanation':
                $opts = $payload['options'] ?? [];
                if (! $opts) { $w[] = 'No option explanations.'; break; }
                if (count($opts) !== 4) { $w[] = 'Expected 4 options, found ' . count($opts) . '.'; }
                $correct = array_values(array_filter($opts, fn ($o) => ! empty($o['correct'])));
                if (! $correct) { $w[] = 'No option marked correct.'; }
                if (count($correct) > 1) { $w[] = 'More than one option marked correct.'; }
                if ($answerKey && $correct && ($correct[0]['label'] ?? null) !== $answerKey) {
                    $w[] = "Marked correct ({$correct[0]['label']}) differs from the answer key ({$answerKey}).";
                }
                foreach ($opts as $o) {
                    if (trim((string) ($o['why'] ?? '')) === '') { $w[] = "Option {$o['label']} has no explanation."; }
                }
                break;

            case 'flashcards':
            case 'memcards':
                $cards = is_array($payload) ? $payload : [];
                if (! $cards) { $w[] = 'No cards.'; break; }
                foreach ($cards as $i => $c) {
                    if (trim((string) ($c['front'] ?? '')) === '' || trim((string) ($c['back'] ?? '')) === '') {
                        $w[] = 'Card ' . ($i + 1) . ' is missing a front or back.';
                    }
                }
                break;

            case 'mermaid':
                if (trim($content) === '') { $w[] = 'Empty diagram.'; }
                elseif (! preg_match('/^\s*(graph|flowchart|sequenceDiagram|stateDiagram)/i', $content)) {
                    $w[] = 'Does not start with a mermaid diagram keyword.';
                }
                break;

            case 'interactive_widget':
                $type = $payload['widget'] ?? $asset->asset_key;
                if (! $type) { $w[] = 'No widget type.'; break; }
                if (! in_array($type, self::KNOWN_WIDGETS, true)) {
                    $w[] = "Widget \"{$type}\" has no component yet — will not render.";
                }
                $cfg = $payload['config'] ?? [];
                if ($type === 'calculator') {
                    if (empty($cfg['formula'])) { $w[] = 'Calculator has no formula.'; }
                    if (empty($cfg['inputs'])) { $w[] = 'Calculator has no inputs.'; }
                }
                break;
        }

        return $w;
    }
}
