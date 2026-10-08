<?php
// Emite JSON para um batch no formato exato do argumento MCP fileKeyToContent
$batch = (int)($argv[1] ?? 0);
$j = json_decode(file_get_contents(__DIR__."/batch_{$batch}.json"), true);
// Validar YAML tree se batch 0
if ($batch === 0 && isset($j['page/id-Scaffold_4rp06q2q/page-widget-tree-outline'])) {
    // ok
}
file_put_contents(__DIR__."/mcp_args_{$batch}.json", json_encode([
    'projectId' => 'well-coletas-by2777',
    'fileKeyToContent' => $j,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo 'wrote mcp_args_'.$batch.'.json bytes='.filesize(__DIR__."/mcp_args_{$batch}.json")."\n";
