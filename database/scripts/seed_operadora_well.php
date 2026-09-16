<?php
/**
 * Preenche operadora id=1 com dados do .env (Well principal).
 * Uso: php database/scripts/seed_operadora_well.php
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__, 2).'/includes/app.php';

use App\Common\Environment;
use App\Model\Db\Database;

$db = new Database();

$cnpj = preg_replace('/\D/', '', (string)Environment::get('INTER_CNPJ', Environment::get('SINIR_CNPJ', '18675233000150'))) ?? '';

$data = [
    'nome' => (string)Environment::get('COMPANY_NAME', 'Well Soluções Ambientais'),
    'razao_social' => (string)Environment::get('COMPANY_NAME', 'Well Soluções Ambientais'),
    'cnpj' => $cnpj !== '' ? $cnpj : '18675233000150',
    'nome_fantasia' => (string)Environment::get('COMPANY_NAME', 'Well Soluções Ambientais'),
    'nome_curto' => (string)Environment::get('COMPANY_SHORT_NAME', 'Well S.A.'),
    'transportador_nome' => (string)Environment::get('COLETA_TRANSPORTADOR', 'Well Soluções Ambientais'),
    'transportador_cnpj' => (string)Environment::get('COLETA_TRANSPORTADOR_CNPJ', '18.675.233/0001-50'),
    'destinador_nome' => (string)Environment::get('COLETA_DESTINADOR', 'Well Soluções Ambientais'),
    'destinador_cnpj' => (string)Environment::get('COLETA_DESTINADOR_CNPJ', '18.675.233/0001-50'),
    'destinador_endereco' => (string)Environment::get('COLETA_DESTINADOR_END', 'Av. Industrial Q09 L15'),
    'destinador_telefone' => (string)Environment::get('COLETA_DESTINADOR_FONE', '66996800006'),
    'destinador_responsavel' => (string)Environment::get('COLETA_DESTINADOR_RESP', ''),
    'sinir_integration_token' => (string)Environment::get('SINIR_INTEGRATION_TOKEN', ''),
    'sinir_unidade' => (string)Environment::get('SINIR_UNIDADE', ''),
    'sinir_unidade_destinador' => (string)Environment::get('SINIR_UNIDADE_DESTINADOR', ''),
    'sinir_cnpj' => (string)Environment::get('SINIR_CNPJ', $cnpj),
    'ativo' => 1,
];

$exists = $db->execute('SELECT id FROM operadoras WHERE id = 1 LIMIT 1')->fetchColumn();
if ($exists) {
    $sets = [];
    $params = [];
    foreach ($data as $k => $v) {
        $sets[] = "`{$k}` = ?";
        $params[] = $v;
    }
    $params[] = 1;
    $db->execute('UPDATE operadoras SET '.implode(', ', $sets).' WHERE id = ?', $params);
    echo "Operadora #1 atualizada a partir do .env\n";
} else {
    $cols = array_keys($data);
    $db->execute(
        'INSERT INTO operadoras (id, '.implode(', ', $cols).') VALUES (1, '.implode(', ', array_fill(0, count($cols), '?')).')',
        array_values($data)
    );
    echo "Operadora #1 inserida a partir do .env\n";
}

// Config cobrança
$configKeys = [
    'cobranca.multa_tipo' => (string)Environment::get('COBRANCA_MULTA_TIPO', 'PERCENTUAL'),
    'cobranca.multa_taxa' => (string)Environment::get('COBRANCA_MULTA_TAXA', '2.00'),
    'cobranca.multa_valor' => (string)Environment::get('COBRANCA_MULTA_VALOR', '0.00'),
    'cobranca.mora_tipo' => (string)Environment::get('COBRANCA_MORA_TIPO', 'TAXAMENSAL'),
    'cobranca.mora_taxa' => (string)Environment::get('COBRANCA_MORA_TAXA', '1.00'),
    'cobranca.mora_valor' => (string)Environment::get('COBRANCA_MORA_VALOR', '0.00'),
];

foreach ($configKeys as $chave => $valor) {
    $db->execute(
        'INSERT INTO operadora_config (operadora_id, chave, valor) VALUES (1, ?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
        [$chave, $valor]
    );
}

echo "operadora_config sincronizado.\n";
