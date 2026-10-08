<?php
$batch = (int)($argv[1] ?? 0);
$j = json_decode(file_get_contents(__DIR__."/batch_{$batch}.json"), true);
echo implode("\n", array_keys($j))."\n";
echo "COUNT=".count($j)."\n";
