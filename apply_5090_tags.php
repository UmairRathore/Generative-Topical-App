<?php
use Illuminate\Support\Facades\DB;

$topicMap = [
    '1'=>170,'2'=>171,'3'=>172,'4'=>173,'5'=>174,'6'=>175,'7'=>176,
    '8'=>177,'9'=>178,'10'=>179,'11'=>180,'12'=>181,'13'=>182,'14'=>183,
    '15'=>184,'16'=>185,'17'=>186,'18'=>187,'19'=>188,
];
$subtopicMap = [
    '1.1'=>355,'1.2'=>356,
    '2.1'=>357,'2.2'=>358,
    '3.1'=>359,'3.2'=>360,
    '4.1'=>361,
    '5.1'=>362,'5.2'=>363,
    '6.1'=>364,'6.2'=>365,'6.3'=>366,
    '7.1'=>367,'7.2'=>368,
    '8.1'=>369,'8.2'=>370,'8.3'=>371,
    '9.1'=>372,
    '10.1'=>373,'10.2'=>374,'10.3'=>375,
    '11.1'=>376,'11.2'=>377,'11.3'=>378,'11.4'=>379,
    '12.1'=>380,'12.2'=>381,'12.3'=>382,
    '13.1'=>383,'13.2'=>384,
    '14.1'=>385,'14.2'=>386,'14.3'=>387,'14.4'=>388,'14.5'=>389,'14.6'=>390,
    '15.1'=>391,
    '16.1'=>392,'16.2'=>393,'16.3'=>394,'16.4'=>395,
    '17.1'=>396,'17.2'=>397,'17.3'=>398,'17.4'=>399,
    '18.1'=>400,'18.2'=>401,
    '19.1'=>402,'19.2'=>403,'19.3'=>404,'19.4'=>405,'19.5'=>406,
];

$jsonPath = storage_path('app/tagging/assignments_5090.json');
if (!file_exists($jsonPath)) { echo "ERROR: file not found\n"; exit(1); }
$assignments = json_decode(file_get_contents($jsonPath), true);
if (!$assignments) { echo "ERROR: failed to parse JSON\n"; exit(1); }

$updated = 0; $notFound = 0; $badIds = 0; $badList = [];

foreach ($assignments as $a) {
    $id = $a['id']; $tExt = $a['topic_external_id']; $stExt = $a['subtopic_external_id'];
    if (!isset($topicMap[$tExt]) || !isset($subtopicMap[$stExt])) {
        $badList[] = "q#{$id}: topic={$tExt} subtopic={$stExt}"; $badIds++; continue;
    }
    $rows = DB::table('v2_questions')->where('id', $id)->update([
        'topic_id' => $topicMap[$tExt], 'subtopic_id' => $subtopicMap[$stExt],
    ]);
    if ($rows > 0) $updated++; else $notFound++;
}

echo "\n=== BIOLOGY 5090 TAGGING RESULTS ===\n";
echo "Total assignments: " . count($assignments) . "\n";
echo "Successfully updated: {$updated}\n";
echo "Not found: {$notFound}\n";
echo "Unknown external_id: {$badIds}\n";
if (!empty($badList)) { foreach (array_slice($badList, 0, 10) as $b) echo "  {$b}\n"; }
echo "\nDone.\n";
