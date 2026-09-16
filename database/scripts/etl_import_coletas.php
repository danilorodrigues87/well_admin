<?php

/**
 * Etapa 5 — Importa coletas/MTR históricos de well_antigo → well_admin.
 *
 * Uso:
 *   php database/scripts/etl_import_coletas.php [--dry-run] [--limit=100] [--purge-local] [--operadora-id=1]
 */

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Environment;
use App\Model\Db\Database;
use App\Service\LegacyPesoParser;

$dryRun = in_array('--dry-run', $argv, true);
$purgeLocal = in_array('--purge-local', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int)substr($arg, 8));
    }
}

$coletorPadrao = (int)Environment::get('ETL_COLETOR_ID', 1);
$operadoraId = 1;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--operadora-id=')) {
        $operadoraId = max(1, (int)substr($arg, 15));
    }
}
$db = new Database();

$col = $db->execute(
    "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'coletas' AND COLUMN_NAME = 'legacy_manifesto'"
)->fetch(\PDO::FETCH_ASSOC);

if ((int)($col['c'] ?? 0) === 0) {
    fwrite(STDERR, "Erro: aplique database/migrations/007_coletas_legacy_prep.sql antes.\n");
    exit(1);
}

/** @var array<string, array{id:int,classe_nome:?string,grupo_codigo:?string,cod_ibama:?string}> $tiposCache */
$tiposCache = [];
foreach ($db->execute(
    'SELECT t.id, t.nome, t.cod_ibama, rc.nome AS classe_nome, rg.codigo AS grupo_codigo
     FROM tipos_residuos t
     LEFT JOIN residuo_classes rc ON rc.id = t.classe_id
     LEFT JOIN residuo_grupos rg ON rg.id = t.grupo_id'
)->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    $key = normalizeKey((string)$row['nome']);
    $tiposCache[$key] = [
        'id' => (int)$row['id'],
        'classe_nome' => $row['classe_nome'],
        'grupo_codigo' => $row['grupo_codigo'],
        'cod_ibama' => $row['cod_ibama'],
    ];
}

/** @var array<string, int> $veiculosPorPlaca */
$veiculosPorPlaca = [];
foreach ($db->execute('SELECT id, placa FROM veiculos')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
    $placa = normalizePlaca((string)$row['placa']);
    if ($placa !== '') {
        $veiculosPorPlaca[$placa] = (int)$row['id'];
    }
}

if ($purgeLocal && !$dryRun) {
    $db->execute('DELETE FROM coletas WHERE legacy_manifesto IS NULL');
    echo "Coletas locais de teste removidas.\n";
}

$sql = 'SELECT l.* FROM well_antigo.coletas l
        WHERE NOT EXISTS (
            SELECT 1 FROM coletas c WHERE c.legacy_manifesto = l.manifesto
        )
        ORDER BY l.manifesto ASC';
if ($limit > 0) {
    $sql .= ' LIMIT '.(int)$limit;
}

$rows = $db->execute($sql)->fetchAll(\PDO::FETCH_ASSOC);
$total = count($rows);
echo "Importando {$total} coleta(s) (operadora_id={$operadoraId})...\n";

$imported = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $row) {
    $manifesto = (int)$row['manifesto'];
    $clienteId = (int)$row['id_cliente'];

    if (!$dryRun) {
        $existsCliente = $db->execute('SELECT id FROM clientes WHERE id = ? LIMIT 1', [$clienteId])->fetchColumn();
        if (!$existsCliente) {
            fwrite(STDERR, "Skip manifesto {$manifesto}: cliente {$clienteId} inexistente.\n");
            $skipped++;
            continue;
        }

        $dupMtr = $db->execute(
            'SELECT id FROM coletas WHERE numero_mtr = ? AND (legacy_manifesto IS NULL OR legacy_manifesto != ?) LIMIT 1',
            [$manifesto, $manifesto]
        )->fetchColumn();
        if ($dupMtr) {
            fwrite(STDERR, "Skip manifesto {$manifesto}: numero_mtr já usado por coleta local id={$dupMtr}.\n");
            $skipped++;
            continue;
        }
    }

    $dataColeta = validDate($row['data_coleta'] ?? null);
    $docRef = validDate($row['doc_referencia'] ?? null);
    $dataReceb = validDate($row['data_recebimento'] ?? null);
    if ($dataReceb === null && $dataColeta !== null) {
        $dataReceb = $dataColeta;
    }
    $hora = parseHora((string)($row['hora'] ?? ''));
    $placa = normalizePlaca((string)($row['placa'] ?? ''));
    $veiculoId = $veiculosPorPlaca[$placa] ?? null;
    $situacao = strtolower(trim((string)($row['situacao'] ?? ''))) === 'true' ? 'recebido' : 'pendente';
    $itens = LegacyPesoParser::parse((string)($row['peso'] ?? ''));

    if ($dryRun) {
        echo "[dry-run] MTR {$manifesto} cliente={$clienteId} itens=".count($itens)." placa={$placa}\n";
        $imported++;
        continue;
    }

    $db->beginTransaction();
    try {
        $db->execute(
            'INSERT INTO coletas (
                operadora_id, numero_mtr, legacy_manifesto, cliente_id, coletor_id, veiculo_id, status,
                doc_referencia, data_coleta, hora, relatorio, situacao_recebimento,
                data_recebimento, tratamento, finalized_at
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $operadoraId,
                $manifesto,
                $manifesto,
                $clienteId,
                $coletorPadrao,
                $veiculoId,
                'finalizada',
                $docRef,
                $dataColeta,
                $hora,
                nullIfEmpty($row['relatorio'] ?? ''),
                $situacao,
                $dataReceb,
                nullIfEmpty($row['tratamento'] ?? ''),
                ($dataColeta ?? date('Y-m-d')).' '.($hora ?? '12:00:00'),
            ]
        );
        $coletaId = (int)$db->lastInsertId();

        $endereco = trim((string)($row['endereco_cliente'] ?? ''));

        $db->execute(
            'INSERT INTO coleta_snapshot (
                coleta_id, gerador_nome_fantasia, gerador_razao_social, gerador_cnpj,
                gerador_endereco, gerador_responsavel, gerador_plano,
                transportador_nome, transportador_cnpj, motorista_nome,
                veiculo_descricao, veiculo_placa,
                destinador_nome, destinador_cnpj, destinador_endereco,
                destinador_telefone, destinador_responsavel, observacao_destinador
             ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $coletaId,
                nullIfEmpty($row['emp_cliente'] ?? ''),
                nullIfEmpty($row['emp_cliente'] ?? ''),
                nullIfEmpty($row['emp_cnpj'] ?? ''),
                $endereco !== '' ? $endereco : null,
                nullIfEmpty($row['cliente_resp'] ?? ''),
                nullIfEmpty($row['plano'] ?? ''),
                nullIfEmpty($row['transportador'] ?? ''),
                nullIfEmpty($row['trans_cnpj'] ?? ''),
                nullIfEmpty($row['motorista'] ?? ''),
                nullIfEmpty($row['veiculo'] ?? ''),
                nullIfEmpty($row['placa'] ?? ''),
                nullIfEmpty($row['emp_df'] ?? ''),
                nullIfEmpty($row['emp_df_cnpj'] ?? ''),
                nullIfEmpty($row['end_df'] ?? ''),
                nullIfEmpty($row['fone_df'] ?? ''),
                nullIfEmpty($row['resp_df'] ?? ''),
                nullIfEmpty($row['obs2'] ?? ''),
            ]
        );

        foreach ($itens as $item) {
            $tipo = matchTipo($tiposCache, $item['nome'], $item['cod_ibama']);
            $db->execute(
                'INSERT INTO coleta_itens (
                    coleta_id, tipo_residuo_id, nome, classe_nome, grupo_codigo, cod_ibama, quantidade, unidade
                 ) VALUES (?,?,?,?,?,?,?,?)',
                [
                    $coletaId,
                    ($tipo['id'] ?? 0) > 0 ? $tipo['id'] : null,
                    $item['nome'],
                    $tipo['classe_nome'] ?? null,
                    $tipo['grupo_codigo'] ?? null,
                    $item['cod_ibama'] ?? $tipo['cod_ibama'] ?? null,
                    $item['quantidade'],
                    $item['unidade'],
                ]
            );
        }

        $db->commit();
        $imported++;
        if ($imported % 500 === 0) {
            echo "  ... {$imported}/{$total}\n";
        }
    } catch (Throwable $e) {
        $db->rollBack();
        fwrite(STDERR, "Erro MTR {$manifesto}: ".$e->getMessage()."\n");
        $errors++;
    }
}

if (!$dryRun && $imported > 0) {
    $maxMtr = (int)$db->execute('SELECT MAX(numero_mtr) FROM coletas')->fetchColumn();
    $db->execute(
        'INSERT INTO coleta_sequencia (operadora_id, ultimo_mtr) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE ultimo_mtr = GREATEST(ultimo_mtr, VALUES(ultimo_mtr))',
        [$operadoraId, $maxMtr]
    );
    echo "Sequência MTR atualizada para {$maxMtr}.\n";
}

echo "Concluído. Importadas: {$imported}, ignoradas: {$skipped}, erros: {$errors}\n";

function normalizeKey(string $value): string
{
    $value = mb_strtoupper(trim($value), 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return $value;
}

function normalizePlaca(string $placa): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $placa) ?? '');
}

function validDate(?string $date): ?string
{
    $date = trim((string)$date);
    if ($date === '' || $date === '0000-00-00') {
        return null;
    }

    return $date;
}

function parseHora(string $hora): ?string
{
    $hora = trim($hora);
    if ($hora === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) {
        return sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
    }

    return null;
}

function nullIfEmpty(?string $value): ?string
{
    $value = trim((string)$value);

    return $value === '' ? null : $value;
}

/** @param array<string, array{id:int,classe_nome:?string,grupo_codigo:?string,cod_ibama:?string}> $cache */
function matchTipo(array $cache, string $nome, ?string $codIbama): array
{
    if ($codIbama) {
        foreach ($cache as $tipo) {
            if ($tipo['cod_ibama'] && strcasecmp((string)$tipo['cod_ibama'], $codIbama) === 0) {
                return $tipo;
            }
        }
    }

    $key = normalizeKey($nome);
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    foreach ($cache as $cacheKey => $tipo) {
        if (str_contains($cacheKey, $key) || str_contains($key, $cacheKey)) {
            return $tipo;
        }
    }

    return ['id' => 0, 'classe_nome' => null, 'grupo_codigo' => null, 'cod_ibama' => null];
}
