<?php
use Illuminate\Support\Facades\DB;

$subjects = DB::table('v2_subjects')->get(['id', 'code', 'name', 'level']);
foreach ($subjects as $s) {
    $t = DB::table('v2_questions')->where('subject_id', $s->id)->count();
    $tg = DB::table('v2_questions')->where('subject_id', $s->id)->whereNotNull('topic_id')->count();
    echo $s->code . ' | ' . $s->name . ' (' . $s->level . ') | total=' . $t . ' | tagged=' . $tg . "\n";
}
