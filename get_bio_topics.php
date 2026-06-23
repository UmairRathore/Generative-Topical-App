<?php
use Illuminate\Support\Facades\DB;

foreach (['5090', '9700'] as $code) {
    $subjectId = DB::table('v2_subjects')->where('code', $code)->value('id');
    echo "\n=== {$code} (subject_id={$subjectId}) ===\n";
    $topics = DB::table('v2_topics')->where('subject_id', $subjectId)->orderBy('sort_order')->get(['id', 'external_id', 'title']);
    foreach ($topics as $t) {
        echo $t->id . " | " . $t->external_id . " | " . $t->title . "\n";
        $subs = DB::table('v2_subtopics')->where('topic_id', $t->id)->orderBy('sort_order')->get(['id', 'external_id', 'title']);
        foreach ($subs as $s) {
            echo "  " . $s->id . " | " . $s->external_id . " | " . $s->title . "\n";
        }
    }
}
