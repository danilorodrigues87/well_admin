<?php
/**
 * Valida e faz push de um batch via stdin JSON para stdout resumo.
 * Uso interno: o agente chama MCP; este script só prepara.
 */
$batch = (int)($argv[1] ?? 0);
$path = __DIR__."/batch_{$batch}.json";
$j = json_decode(file_get_contents($path), true);
if (!is_array($j)) {
    fwrite(STDERR, "invalid batch\n");
    exit(1);
}
// Escreve um arquivo por chave para facilitar (opcional)
$outDir = __DIR__."/expanded_b{$batch}";
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
foreach ($j as $key => $yaml) {
    $safe = str_replace(['/', '\\'], '__', $key);
    file_put_contents("$outDir/$safe.yaml", $yaml);
}
echo "expanded=".count($j)." to $outDir\n";
