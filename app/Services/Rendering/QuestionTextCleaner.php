<?php

namespace App\Services\Rendering;

class QuestionTextCleaner
{
    /**
     * Strip duplicated A/B/C/D option blocks at the tail of a question stem.
     */
    public function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $cut = preg_replace('/\n\s*A[\.\)]\s.*\z/su', '', $normalized);
        $cut = $cut === null ? $normalized : $cut;

        return rtrim($cut);
    }
}
