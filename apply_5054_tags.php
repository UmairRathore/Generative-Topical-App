<?php
use Illuminate\Support\Facades\DB;

$topicMap = [
    '1' => 103,
    '2' => 104,
    '3' => 105,
    '4' => 106,
    '5' => 107,
    '6' => 108,
];
$subtopicMap = [
    '1.1'   => 77,  '1.2'   => 78,  '1.3'   => 79,  '1.4'   => 80,
    '1.5.1' => 81,  '1.5.2' => 82,  '1.5.3' => 83,  '1.5.4' => 84,
    '1.5.5' => 85,  '1.5.6' => 86,  '1.6'   => 87,
    '1.7.1' => 88,  '1.7.2' => 89,  '1.7.3' => 90,  '1.7.4' => 91,
    '1.7.5' => 92,  '1.8'   => 93,
    '2.1.1' => 94,  '2.1.2' => 95,
    '2.2.1' => 96,  '2.2.2' => 97,  '2.2.3' => 98,
    '2.3.1' => 99,  '2.3.2' => 100, '2.3.3' => 101, '2.3.4' => 102,
    '3.1'   => 103, '3.2.1' => 104, '3.2.2' => 105, '3.2.3' => 106,
    '3.2.4' => 107, '3.3'   => 108, '3.4'   => 109,
    '4.1'   => 110,
    '4.2.1' => 111, '4.2.2' => 112, '4.2.3' => 113, '4.2.4' => 114,
    '4.3.1' => 115, '4.3.2' => 116, '4.3.3' => 117,
    '4.4.1' => 118, '4.4.2' => 119,
    '4.5.1' => 120, '4.5.2' => 121, '4.5.3' => 122, '4.5.4' => 123,
    '4.5.5' => 124, '4.5.6' => 125, '4.6'   => 126,
    '5.1.1' => 127, '5.1.2' => 128,
    '5.2.1' => 129, '5.2.2' => 130, '5.2.3' => 131, '5.2.4' => 132,
    '5.2.5' => 133, '5.2.6' => 134,
    '6.1.1' => 135, '6.1.2' => 136,
    '6.2.1' => 137, '6.2.2' => 138, '6.2.3' => 139,
];

$jsonPath = storage_path('app/tagging/assignments_5054.json');
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

echo "\n=== PHYSICS 5054 TAGGING RESULTS ===\n";
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
