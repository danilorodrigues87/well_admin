<?php

/**
 * Remove coletas canceladas de um cliente, com resíduos, fotos, assinatura e a pasta em storage.
 * Itens, evidências, snapshot e envios SINIR saem pelo CASCADE ao apagar a coleta.
 *
 * Uso (na raiz do projeto):
 *   php database/scripts/purge_coletas_canceladas_cliente.php --cliente-id=15
 *   php database/scripts/purge_coletas_canceladas_cliente.php --cliente-id=15 --apply
 *
 * Sem --apply apenas lista. Não mexe em rascunho nem em coleta finalizada.
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\StoragePath;
use App\Model\Db\Database;

$apply = in_array('--apply', $argv, true);
$clienteId = 0;
$operadoraId = 1;

foreach ($argv as $arg) {
    if (preg_match('/^--cliente-id=(\d+)$/', $arg, $m)) {
        $clienteId = (int)$m[1];
    }
    if (preg_match('/^--operadora-id=(\d+)$/', $arg, $m)) {
        $operadoraId = (int)$m[1];
    }
}

if ($clienteId <= 0) {
    fwrite(STDERR, "Informe o cliente: --cliente-id=NUMERO\n");
    exit(1);
}

$db = new Database();

$cliente = $db->execute(
    'SELECT id, nome_fantasia FROM clientes WHERE id = ? AND operadora_id = ? LIMIT 1',
    [$clienteId, $operadoraId]
)->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    fwrite(STDERR, "Cliente {$clienteId} não encontrado na operadora {$operadoraId}.\n");
    exit(1);
}

$rows = $db->execute(
    'SELECT c.id, c.numero_relatorio, c.numero_mtr, c.status, c.assinatura_cliente_path, c.created_at,
            (SELECT COUNT(*) FROM coleta_itens i WHERE i.coleta_id = c.id) AS itens,
            (SELECT COUNT(*) FROM coleta_evidencias e WHERE e.coleta_id = c.id) AS fotos
     FROM coletas c
     WHERE c.operadora_id = ? AND c.cliente_id = ? AND c.status = \'cancelada\'
     ORDER BY c.id',
    [$operadoraId, $clienteId]
)->fetchAll(PDO::FETCH_ASSOC);

$prefix = $apply ? '' : '[lista] ';
echo "{$prefix}Cliente {$cliente['id']} — {$cliente['nome_fantasia']}\n";
echo "{$prefix}Coletas canceladas: ".count($rows)."\n";

if ($rows === []) {
    exit(0);
}

foreach ($rows as $row) {
    $rel = $row['numero_relatorio'] !== null ? (string)$row['numero_relatorio'] : '—';
    $assinatura = trim((string)($row['assinatura_cliente_path'] ?? '')) !== '' ? 'sim' : 'não';
    echo "  - id={$row['id']} relatorio={$rel} residuos={$row['itens']} fotos={$row['fotos']} assinatura={$assinatura}\n";
}

if (!$apply) {
    echo "Nada foi apagado. Para excluir, rode de novo com --apply\n";
    exit(0);
}

$ids = array_map(static fn (array $r): int => (int)$r['id'], $rows);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
    $db->beginTransaction();
    $db->execute('DELETE FROM coletas WHERE id IN ('.$placeholders.') AND status = \'cancelada\' AND cliente_id = ? AND operadora_id = ?', array_merge($ids, [$clienteId, $operadoraId]));
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Erro: '.$e->getMessage()."\n");
    exit(1);
}

$storageRoot = rtrim(str_replace('\\', '/', StoragePath::root()), '/');
$pastas = 0;
foreach ($ids as $id) {
    $dir = StoragePath::join('coletas/'.$id);
    $normalizado = rtrim(str_replace('\\', '/', $dir), '/');
    if (!str_starts_with($normalizado, $storageRoot.'/coletas/')) {
        fwrite(STDERR, "Pasta ignorada (fora de storage/coletas): {$dir}\n");
        continue;
    }
    if (apagarPasta($normalizado)) {
        $pastas++;
    }
}

echo 'Removidas '.count($ids).' coleta(s) canceladas. Pastas de arquivo removidas: '.$pastas.".\n";

function apagarPasta(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }
    $itens = scandir($dir);
    if ($itens === false) {
        return false;
    }
    foreach ($itens as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $caminho = $dir.'/'.$item;
        if (is_dir($caminho)) {
            apagarPasta($caminho);
            continue;
        }
        if (is_file($caminho)) {
            @unlink($caminho);
        }
    }

    return @rmdir($dir);
}
