<?php
use Illuminate\Support\Facades\DB;

$topicMap = [
    '1'=>109,'2'=>110,'3'=>111,'4'=>112,'5'=>113,'6'=>114,'7'=>115,
    '8'=>116,'9'=>117,'10'=>118,'11'=>119,'12'=>120,'13'=>121,'14'=>122,
    '15'=>123,'16'=>124,'17'=>125,'18'=>126,'19'=>127,'20'=>128,'21'=>129,
    '22'=>130,'23'=>131,'24'=>132,'25'=>133,'26'=>134,
];
$subtopicMap = [
    '1.1'=>140,'1.2'=>141,'1.3'=>142,'1.4'=>143,
    '2.1'=>144,'2.2'=>145,
    '3.1'=>146,
    '4.1'=>147,'4.2'=>148,'4.3'=>149,
    '5.1'=>150,'5.2'=>151,'5.3'=>152,'5.4'=>153,
    '6.1'=>154,'6.2'=>155,'6.3'=>156,'6.4'=>157,
    '7.1'=>158,'7.2'=>159,
    '8.1'=>160,'8.2'=>161,'8.3'=>162,'8.4'=>163,
    '9.1'=>164,'9.2'=>165,
    '10.1'=>166,'10.2'=>167,'10.3'=>168,
    '11.1'=>169,'11.2'=>170,'11.3'=>171,
    '12.1'=>172,'12.2'=>173,
    '13.1'=>174,'13.2'=>175,'13.3'=>176,
    '14.1'=>177,'14.2'=>178,'14.3'=>179,'14.4'=>180,'14.5'=>181,'14.6'=>182,
    '15.1'=>183,'15.2'=>184,'15.3'=>185,'15.4'=>186,
    '16.1'=>187,'16.2'=>188,'16.3'=>189,'16.4'=>190,'16.5'=>191,
    '17.1'=>192,'17.2'=>193,'17.3'=>194,'17.4'=>195,'17.5'=>196,
    '18.1'=>197,'18.2'=>198,
    '19.1'=>199,'19.2'=>200,'19.3'=>201,'19.4'=>202,
    '20.1'=>203,'20.2'=>204,'20.3'=>205,
    '21.1'=>206,'21.2'=>207,'21.3'=>208,
    '22.1'=>209,'22.2'=>210,'22.3'=>211,'22.4'=>212,'22.5'=>213,
    '23.1'=>214,
    '24.1'=>215,'24.2'=>216,'24.3'=>217,'24.4'=>218,
    '25.1'=>219,'25.2'=>220,'25.3'=>221,'25.4'=>222,'25.5'=>223,'25.6'=>224,
    '26.1'=>225,'26.2'=>226,'26.3'=>227,'26.4'=>228,
];

$jsonPath = storage_path('app/tagging/assignments_9702.json');
if (!file_exists($jsonPath)) {
    echo "ERROR: assignments JSON file not found at {$jsonPath}\n";
    exit(1);
}
$assignments = json_decode(file_get_contents($jsonPath), true);
if (!$assignments) {
    echo "ERROR: failed to parse JSON\n";
    exit(1);
}

$updated = 0; $notFound = 0; $badIds = 0; $badList = [];

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

    if ($rows > 0) $updated++; else $notFound++;
}

echo "\n=== PHYSICS 9702 TAGGING RESULTS ===\n";
echo "Total assignments in JSON: " . count($assignments) . "\n";
echo "Successfully updated:      {$updated}\n";
echo "Question ID not found:     {$notFound}\n";
echo "Unknown external_id:       {$badIds}\n";
if (!empty($badList)) {
    echo "\nFirst 10 unknown IDs:\n";
    foreach (array_slice($badList, 0, 10) as $b) echo "  {$b}\n";
}
echo "\nDone.\n";
