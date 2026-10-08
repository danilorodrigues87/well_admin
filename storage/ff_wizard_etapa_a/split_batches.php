<?php
$p = json_decode(file_get_contents(__DIR__.'/push_payload.json'), true)['fileKeyToContent'];
$order = [];
$priority = [
    'page/id-Scaffold_4rp06q2q',
    'page/id-Scaffold_4rp06q2q/page-widget-tree-outline',
];
foreach ($priority as $k) {
    if (isset($p[$k])) {
        $order[$k] = $p[$k];
    }
}
foreach ($p as $k => $v) {
    if (!isset($order[$k])) {
        $order[$k] = $v;
    }
}
$i = 0;
$b = 0;
$batch = [];
foreach ($order as $k => $v) {
    $batch[$k] = $v;
    $i++;
    if ($i % 12 === 0) {
        file_put_contents(__DIR__.'/batch_'.$b.'.json', json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo "batch $b: ".count($batch)." files\n";
        $b++;
        $batch = [];
    }
}
if ($batch !== []) {
    file_put_contents(__DIR__.'/batch_'.$b.'.json', json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "batch $b: ".count($batch)." files\n";
}
