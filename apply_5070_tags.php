<?php
use Illuminate\Support\Facades\DB;

$topicMap = [
    '1'=>135,'2'=>136,'3'=>137,'4'=>138,'5'=>139,'6'=>140,
    '7'=>141,'8'=>142,'9'=>143,'10'=>144,'11'=>145,'12'=>146,
];
$subtopicMap = [
    '1.1'=>229,'1.2'=>230,
    '2.1'=>231,'2.2'=>232,'2.3'=>233,'2.4'=>234,'2.5'=>235,'2.6'=>236,'2.7'=>237,
    '3.1'=>238,'3.2'=>239,'3.3'=>240,
    '4.1'=>241,'4.2'=>242,
    '5.1'=>243,
    '6.1'=>244,'6.2'=>245,'6.3'=>246,'6.4'=>247,
    '7.1'=>248,'7.2'=>249,'7.3'=>250,
    '8.1'=>251,'8.2'=>252,'8.3'=>253,'8.4'=>254,'8.5'=>255,
    '9.1'=>256,'9.2'=>257,'9.3'=>258,'9.4'=>259,'9.5'=>260,'9.6'=>261,
    '10.1'=>262,'10.2'=>263,'10.3'=>264,
    '11.1'=>265,'11.2'=>266,'11.3'=>267,'11.4'=>268,'11.5'=>269,'11.6'=>270,'11.7'=>271,'11.8'=>272,
    '12.1'=>273,'12.2'=>274,'12.3'=>275,'12.4'=>276,'12.5'=>277,
];

$jsonPath = storage_path('app/tagging/assignments_5070.json');
if (!file_exists($jsonPath)) {
    echo "ERROR: assignments JSON file not found at {$jsonPath}\n";
    exit(1);
}
$assignments = json_decode(file_get_contents($jsonPath), true);
if (!$assignments) {
    echo "ERROR: failed to parse JSON\n";
    exit(1);
}

$updated = 0;
$notFound = 0;
$badIds = 0;
$badList = [];

foreach ($assignments as $a) {
    $id = $a['id'];
    $tExt = $a['topic_external_id'];
    $stExt = $a['subtopic_external_id'];

    if (!isset($topicMap[$tExt]) || !isset($subtopicMap[$stExt])) {
        $badList[] = "q#{$id}: topic={$tExt} subtopic={$stExt}";
        $badIds++;
        continue;
    }

    $rows = DB::table('v2_questions')->where('id', $id)->update([
        'topic_id'    => $topicMap[$tExt],
        'subtopic_id' => $subtopicMap[$stExt],
    ]);

    if ($rows > 0) {
        $updated++;
    } else {
        $notFound++;
    }
}

echo "\n=== CHEMISTRY 5070 TAGGING RESULTS ===\n";
echo "Total assignments in JSON: " . count($assignments) . "\n";
echo "Successfully updated:      {$updated}\n";
echo "Question ID not found:     {$notFound}\n";
echo "Unknown external_id:       {$badIds}\n";
if (!empty($badList)) {
    echo "\nFirst 10 unknown IDs:\n";
    foreach (array_slice($badList, 0, 10) as $b) {
        echo "  {$b}\n";
    }
}
echo "\nDone.\n";
