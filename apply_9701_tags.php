<?php
use Illuminate\Support\Facades\DB;

$topicMap = [
    '1'=>147,'2'=>148,'3'=>149,'4'=>150,'5'=>151,'6'=>152,'7'=>153,
    '8'=>154,'9'=>155,'10'=>156,'11'=>157,'12'=>158,'13'=>159,'14'=>160,
    '15'=>161,'16'=>162,'17'=>163,'18'=>164,'19'=>165,'20'=>166,
    '21'=>167,'22'=>168,'23'=>169,
];
$subtopicMap = [
    '1.1'=>278,'1.2'=>279,'1.3'=>280,'1.4'=>281,'1.5'=>282,
    '2.1'=>283,'2.2'=>284,'2.3'=>285,
    '3.1'=>286,'3.2'=>287,'3.3'=>288,'3.4'=>289,'3.5'=>290,
    '4.1'=>291,'4.2'=>292,'4.3'=>293,
    '5.1'=>294,'5.2'=>295,'5.3'=>296,'5.4'=>297,
    '6.1'=>298,'6.2'=>299,'6.3'=>300,'6.4'=>301,
    '7.1'=>302,'7.2'=>303,'7.3'=>304,
    '8.1'=>305,'8.2'=>306,'8.3'=>307,
    '9.1'=>308,'9.2'=>309,'9.3'=>310,
    '10.1'=>311,'10.2'=>312,
    '11.1'=>313,'11.2'=>314,'11.3'=>315,'11.4'=>316,'11.5'=>317,
    '12.1'=>318,'12.2'=>319,'12.3'=>320,'12.4'=>321,'12.5'=>322,
    '13.1'=>323,'13.2'=>324,
    '14.1'=>325,'14.2'=>326,'14.3'=>327,'14.4'=>328,
    '15.1'=>329,'15.2'=>330,'15.3'=>331,'15.4'=>332,
    '16.1'=>333,'16.2'=>334,
    '17.1'=>335,'17.2'=>336,
    '18.1'=>337,
    '19.1'=>338,'19.2'=>339,'19.3'=>340,
    '20.1'=>341,'20.2'=>342,'20.3'=>343,
    '21.1'=>344,'21.2'=>345,'21.3'=>346,'21.4'=>347,
    '22.1'=>348,'22.2'=>349,'22.3'=>350,'22.4'=>351,'22.5'=>352,
    '23.1'=>353,'23.2'=>354,
];

$jsonPath = storage_path('app/tagging/assignments_9701.json');
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

echo "\n=== CHEMISTRY 9701 TAGGING RESULTS ===\n";
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
