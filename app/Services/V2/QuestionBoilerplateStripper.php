<?php

namespace App\Services\V2;

use App\Models\V2\Question;

/**
 * Removes Cambridge end-of-paper boilerplate that the extraction pipeline swept
 * into question text from the blank/footer page after the last question, e.g.:
 *
 *   "… BLANK PAGE Permission to reproduce items where third-party owned material
 *    protected by copyright … UCLES … Cambridge Assessment is the brand name of
 *    University of …"
 *
 * The real question always ends before this footer, which reliably begins with
 * one of the markers below. We truncate at the earliest marker.
 *
 * Safety: this NEVER blanks a field that had content, and NEVER strips a field
 * whose removed tail still contains a "?" (which would mean the actual question
 * extends past the marker). Such fields are left untouched. The source
 * questions.json files remain the system of record, so a re-import restores the
 * original text if ever needed.
 */
class QuestionBoilerplateStripper
{
    /** The footer reliably starts with one of these (case-insensitive). */
    private const MARKERS = ['BLANK PAGE', 'Permission to reproduce', 'Every reasonable effort'];

    private const FIELDS = ['question_text', 'text_before', 'text_after'];

    /** Truncate a single string at the earliest boilerplate marker. Pure. */
    public function strip(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $cut = null;
        foreach (self::MARKERS as $marker) {
            $pos = mb_stripos($text, $marker);
            if ($pos !== false) {
                $cut = $cut === null ? $pos : min($cut, $pos);
            }
        }

        return $cut === null ? $text : rtrim(mb_substr($text, 0, $cut));
    }

    /**
     * Would-be change for a question, computed but NOT written. Returns a map of
     * field => ['from' => original, 'to' => stripped] for fields that are safe to
     * change, or null if nothing changes. Unsafe strips are silently skipped.
     *
     * @return array<string,array{from:string,to:string}>|null
     */
    public function plan(Question $question): ?array
    {
        $changes = [];

        foreach (self::FIELDS as $field) {
            $original = (string) $question->{$field};
            if ($original === '') {
                continue;
            }

            $stripped = (string) $this->strip($original);
            if ($stripped === $original) {
                continue;                       // no marker found
            }

            $removed = mb_substr($original, mb_strlen($stripped));

            // --- safety guards ---
            if (trim($stripped) === '') {
                continue;                       // would blank a populated field
            }
            if (mb_strpos($removed, '?') !== false) {
                continue;                       // a question mark lives past the marker
            }

            $changes[$field] = ['from' => $original, 'to' => $stripped];
        }

        return $changes ?: null;
    }

    /**
     * Apply the plan for one question. Returns the changed field names, or null.
     *
     * @return array{qid:int,fields:array<int,string>}|null
     */
    public function stripQuestion(Question $question): ?array
    {
        $plan = $this->plan($question);
        if (! $plan) {
            return null;
        }

        $question->update(array_map(fn ($c) => $c['to'], $plan));

        return ['qid' => $question->id, 'fields' => array_keys($plan)];
    }

    /**
     * All questions whose text contains footer boilerplate.
     *
     * @return \Illuminate\Support\Collection<int,Question>
     */
    public function candidates()
    {
        return Question::where(function ($q) {
            foreach (self::FIELDS as $field) {
                $q->orWhere($field, 'like', '%BLANK PAGE%')
                  ->orWhere($field, 'like', '%Permission to reproduce%')
                  ->orWhere($field, 'like', '%Every reasonable effort%');
            }
        })->get();
    }
}
