<?php
use Illuminate\Support\Facades\DB;

$topicMap = [
    '1'=>189,'2'=>190,'3'=>191,'4'=>192,'5'=>193,'6'=>194,'7'=>195,
    '8'=>196,'9'=>197,'10'=>198,'11'=>199,'12'=>200,'13'=>201,'14'=>202,
    '15'=>203,'16'=>204,'17'=>205,'18'=>206,'19'=>207,
];
$subtopicMap = [
    '1.1'=>407,'1.2'=>408,
    '2.1'=>409,'2.2'=>410,'2.3'=>411,
    '3.1'=>412,'3.2'=>413,
    '4.1'=>414,'4.2'=>415,
    '5.1'=>416,'5.2'=>417,
    '6.1'=>418,'6.2'=>419,
    '7.1'=>420,'7.2'=>421,
    '8.1'=>422,'8.2'=>423,
    '9.1'=>424,'9.2'=>425,
    '10.1'=>426,'10.2'=>427,
    '11.1'=>428,'11.2'=>429,
    '12.1'=>430,'12.2'=>431,
    '13.1'=>432,'13.2'=>433,'13.3'=>434,
    '14.1'=>435,'14.2'=>436,
    '15.1'=>437,'15.2'=>438,
    '16.1'=>439,'16.2'=>440,'16.3'=>441,
    '17.1'=>442,'17.2'=>443,'17.3'=>444,
    '18.1'=>445,'18.2'=>446,'18.3'=>447,
    '19.1'=>448,'19.2'=>449,'19.3'=>450,
];

$jsonPath = storage_path('app/tagging/assignments_9700.json');
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

echo "\n=== BIOLOGY 9700 TAGGING RESULTS ===\n";
echo "Total assignments: " . count($assignments) . "\n";
echo "Successfully updated: {$updated}\n";
echo "Not found: {$notFound}\n";
echo "Unknown external_id: {$badIds}\n";
if (!empty($badList)) { foreach (array_slice($badList, 0, 10) as $b) echo "  {$b}\n"; }
echo "\nDone.\n";
