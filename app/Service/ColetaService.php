<?php

namespace App\Service;

use App\Common\ColetaDefaults;
use App\Common\SinirConfig;
use App\Model\Db\Database;
use App\Service\Sinir\SinirManifestoService;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Veiculo as EntityVeiculo;
use PDO;

class ColetaService
{
    public static function proximoNumeroMtr(Database $db): int
    {
        $db->execute('UPDATE coleta_sequencia SET ultimo_mtr = ultimo_mtr + 1 WHERE id = 1');
        $row = $db->execute('SELECT ultimo_mtr FROM coleta_sequencia WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        return (int)($row['ultimo_mtr'] ?? 1);
    }

    public static function iniciarRascunho(int $clienteId, int $coletorId): int
    {
        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente || $cliente->status !== 'ativo') {
            throw new \InvalidArgumentException('Cliente inválido ou inativo.');
        }

        $endereco = trim(implode(', ', array_filter([
            $cliente->logradouro,
            $cliente->numero,
            $cliente->bairro,
            $cliente->cidade,
            $cliente->uf,
        ])));

        $transporte = ColetaDefaults::transportador();
        $destino = ColetaDefaults::destinador();

        $db = new Database();
        $db->beginTransaction();
        try {
            $db->execute(
                'INSERT INTO coletas (cliente_id, coletor_id, status, doc_referencia, data_coleta, hora)
                 VALUES (?,?,?,?,?,?)',
                [
                    $clienteId,
                    $coletorId,
                    'rascunho',
                    $cliente->proxima_coleta ?? date('Y-m-d'),
                    date('Y-m-d'),
                    date('H:i:s'),
                ]
            );
            $coletaId = (int)$db->lastInsertId();

            $db->execute(
                'INSERT INTO coleta_snapshot (
                    coleta_id, gerador_nome_fantasia, gerador_razao_social, gerador_cnpj,
                    gerador_endereco, gerador_responsavel, gerador_plano,
                    transportador_nome, transportador_cnpj, motorista_nome,
                    destinador_nome, destinador_cnpj, destinador_endereco,
                    destinador_telefone, destinador_responsavel
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $coletaId,
                    $cliente->nome_fantasia,
                    $cliente->razao_social,
                    $cliente->cnpj,
                    $endereco,
                    $cliente->responsavel,
                    $cliente->plano_nome,
                    $transporte['nome'],
                    $transporte['cnpj'],
                    $transporte['motorista'],
                    $destino['nome'],
                    $destino['cnpj'],
                    $destino['endereco'],
                    $destino['telefone'],
                    $destino['responsavel'],
                ]
            );

            $db->commit();
            return $coletaId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function salvarTransporte(int $coletaId, array $dados): void
    {
        self::assertRascunho($coletaId);

        $veiculoId = (int)($dados['veiculo_id'] ?? 0);
        $veiculo = $veiculoId > 0 ? EntityVeiculo::getById($veiculoId) : null;

        EntityColeta::update($coletaId, [
            'veiculo_id' => $veiculo ? $veiculoId : null,
            'relatorio' => trim((string)($dados['relatorio'] ?? '')),
            'tratamento' => trim((string)($dados['tratamento'] ?? '')),
            'situacao_recebimento' => ($dados['situacao_recebimento'] ?? '') === 'recebido' ? 'recebido' : 'pendente',
            'data_recebimento' => ($dados['data_recebimento'] ?? '') ?: null,
        ]);

        EntityColetaSnapshot::update($coletaId, [
            'transportador_nome' => trim((string)($dados['transportador_nome'] ?? '')),
            'transportador_cnpj' => trim((string)($dados['transportador_cnpj'] ?? '')),
            'motorista_nome' => trim((string)($dados['motorista_nome'] ?? '')),
            'veiculo_descricao' => $veiculo ? trim($veiculo->marca.' '.$veiculo->modelo) : trim((string)($dados['veiculo_descricao'] ?? '')),
            'veiculo_placa' => $veiculo ? $veiculo->placa : trim((string)($dados['veiculo_placa'] ?? '')),
            'destinador_nome' => trim((string)($dados['destinador_nome'] ?? '')),
            'destinador_cnpj' => trim((string)($dados['destinador_cnpj'] ?? '')),
            'destinador_endereco' => trim((string)($dados['destinador_endereco'] ?? '')),
            'destinador_telefone' => trim((string)($dados['destinador_telefone'] ?? '')),
            'destinador_responsavel' => trim((string)($dados['destinador_responsavel'] ?? '')),
            'observacao_destinador' => trim((string)($dados['observacao_destinador'] ?? '')),
        ]);
    }

    public static function adicionarItem(int $coletaId, int $tipoResiduoId, float $quantidade, string $unidade): int
    {
        self::assertRascunho($coletaId);

        $tipo = EntityTipoResiduo::getById($tipoResiduoId);
        if (!$tipo) {
            throw new \InvalidArgumentException('Tipo de resíduo não encontrado.');
        }
        if ($quantidade <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser maior que zero.');
        }
        if (!in_array($unidade, ['kg', 'l', 'un'], true)) {
            $unidade = 'kg';
        }

        return EntityColetaItem::insert([
            'coleta_id' => $coletaId,
            'tipo_residuo_id' => $tipoResiduoId,
            'nome' => $tipo->nome,
            'classe_nome' => $tipo->classe_nome,
            'grupo_codigo' => $tipo->grupo_codigo,
            'cod_ibama' => $tipo->cod_ibama,
            'quantidade' => $quantidade,
            'unidade' => $unidade,
        ]);
    }

    public static function removerItem(int $coletaId, int $itemId): void
    {
        self::assertRascunho($coletaId);
        EntityColetaItem::delete($itemId, $coletaId);
    }

    public static function finalizar(int $coletaId, array $files = []): int
    {
        self::assertRascunho($coletaId);

        if (EntityColetaItem::countByColeta($coletaId) === 0) {
            throw new \InvalidArgumentException('Adicione ao menos um resíduo antes de finalizar.');
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            throw new \InvalidArgumentException('Coleta não encontrada.');
        }

        $db = new Database();
        $db->beginTransaction();
        try {
            $numeroMtr = self::proximoNumeroMtr($db);

            $db->execute(
                'UPDATE coletas SET numero_mtr = ?, status = ?, finalized_at = NOW() WHERE id = ?',
                [$numeroMtr, 'finalizada', $coletaId]
            );

            EvidenceStorageService::saveBatchForColeta($coletaId, $files);

            $dias = ColetaDefaults::diasProximaColeta();
            $db->execute(
                'UPDATE clientes SET proxima_coleta = DATE_ADD(CURDATE(), INTERVAL ? DAY), prioridade = ? WHERE id = ?',
                [$dias, 'normal', $coleta->cliente_id]
            );

            if (SinirConfig::isEnabled()) {
                $db->execute(
                    'UPDATE coletas SET sinir_status = ? WHERE id = ?',
                    ['pendente', $coletaId]
                );
            }

            $db->commit();

            if (SinirConfig::isEnabled()) {
                try {
                    (new SinirManifestoService())->enviarColeta($coletaId);
                } catch (\Throwable) {
                    // Falha SINIR não reverte a finalização local.
                }
            }

            return $numeroMtr;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function cancelar(int $coletaId): void
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'rascunho') {
            throw new \InvalidArgumentException('Somente rascunhos podem ser cancelados.');
        }
        EntityColeta::update($coletaId, ['status' => 'cancelada']);
    }

    /** @return array{coleta: EntityColeta, snapshot: ?EntityColetaSnapshot, itens: ColetaItem[], evidencias: ColetaEvidencia[]} */
    public static function detalhar(int $coletaId): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            throw new \InvalidArgumentException('Coleta não encontrada.');
        }
        return [
            'coleta' => $coleta,
            'snapshot' => EntityColetaSnapshot::getByColetaId($coletaId),
            'itens' => EntityColetaItem::getByColetaId($coletaId),
            'evidencias' => EntityColetaEvidencia::getByColetaId($coletaId),
        ];
    }

    /** @return array{join:string,where:string,params:array} */
    private static function clientesColetaQuery(
        int $coletorId,
        bool $isAdmin,
        string $busca = '',
        string $prioridade = '',
        bool $somentePendentes = true
    ): array {
        $hoje = date('Y-m-d');
        $join = '';
        $where = 'c.status = ?';
        $params = ['ativo'];

        if (!$isAdmin) {
            $db = new Database();
            $temRota = (bool)$db->execute(
                'SELECT 1 FROM rota_atribuicoes WHERE coletor_id = ? LIMIT 1',
                [$coletorId]
            )->fetch();
            if ($temRota) {
                $join = ' INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.coletor_id = ?';
                $params[] = $coletorId;
            }
        }

        if ($somentePendentes) {
            $where .= ' AND (c.prioridade = ? OR c.proxima_coleta IS NULL OR c.proxima_coleta <= ?)';
            $params[] = 'urgente';
            $params[] = $hoje;
        }
        if ($prioridade === 'normal' || $prioridade === 'urgente') {
            $where .= ' AND c.prioridade = ?';
            $params[] = $prioridade;
        }
        if ($busca !== '') {
            $where .= ' AND (c.nome_fantasia LIKE ? OR c.cidade LIKE ? OR c.cnpj LIKE ?)';
            $params[] = '%'.$busca.'%';
            $params[] = '%'.$busca.'%';
            $params[] = '%'.$busca.'%';
        }

        return ['join' => $join, 'where' => $where, 'params' => $params];
    }

    /** @return EntityCliente[] */
    public static function clientesParaColeta(int $coletorId, bool $isAdmin): array
    {
        $q = self::clientesColetaQuery($coletorId, $isAdmin);
        $db = new Database();
        $sql = 'SELECT DISTINCT c.*, p.nome AS plano_nome FROM clientes c
                LEFT JOIN planos p ON p.id = c.plano_id'.$q['join'].'
                WHERE '.$q['where'].'
                ORDER BY c.prioridade DESC, c.proxima_coleta ASC, c.nome_fantasia ASC';
        $stmt = $db->execute($sql, $q['params']);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = EntityCliente::fromRow($row);
        }

        return $items;
    }

    /** @return array{total:int,items:EntityCliente[]} */
    public static function clientesParaColetaPaginado(
        int $coletorId,
        bool $isAdmin,
        int $page,
        int $perPage,
        string $busca = '',
        string $prioridade = '',
        bool $somentePendentes = true
    ): array {
        $q = self::clientesColetaQuery($coletorId, $isAdmin, $busca, $prioridade, $somentePendentes);
        $db = new Database();
        $countSql = 'SELECT COUNT(DISTINCT c.id) AS qtd FROM clientes c'.$q['join'].' WHERE '.$q['where'];
        $total = (int)$db->execute($countSql, $q['params'])->fetch(PDO::FETCH_ASSOC)['qtd'];

        $pagination = new \App\Model\Db\Pagination($total, $page, $perPage);
        $sql = 'SELECT DISTINCT c.*, p.nome AS plano_nome FROM clientes c
                LEFT JOIN planos p ON p.id = c.plano_id'.$q['join'].'
                WHERE '.$q['where'].'
                ORDER BY c.prioridade DESC, c.proxima_coleta ASC, c.nome_fantasia ASC
                LIMIT '.$pagination->getLimit();
        $stmt = $db->execute($sql, $q['params']);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = EntityCliente::fromRow($row);
        }

        return ['total' => $total, 'items' => $items, 'pagination' => $pagination];
    }

    private static function assertRascunho(int $coletaId): void
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'rascunho') {
            throw new \InvalidArgumentException('Coleta não está em rascunho.');
        }
    }
}
