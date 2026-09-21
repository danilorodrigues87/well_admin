<?php

namespace App\Service;

use App\Common\ColetaDefaults;
use App\Common\Helpers\ColetorSelectHelper;
use App\Common\OperadoraScope;
use App\Common\SinirConfig;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Model\Entity\Veiculo as EntityVeiculo;
use PDO;

class ColetaService
{
    public static function proximoNumeroMtr(Database $db, ?int $operadoraId = null): int
    {
        $operadoraId = $operadoraId ?? OperadoraScope::getOperadoraId();
        $db->execute(
            'INSERT INTO coleta_sequencia (operadora_id, ultimo_mtr) VALUES (?, 0)
             ON DUPLICATE KEY UPDATE operadora_id = operadora_id',
            [$operadoraId]
        );
        $db->execute(
            'UPDATE coleta_sequencia SET ultimo_mtr = ultimo_mtr + 1 WHERE operadora_id = ?',
            [$operadoraId]
        );
        $row = $db->execute(
            'SELECT ultimo_mtr FROM coleta_sequencia WHERE operadora_id = ?',
            [$operadoraId]
        )->fetch(PDO::FETCH_ASSOC);

        return (int)($row['ultimo_mtr'] ?? 1);
    }

    public static function iniciarRascunho(int $clienteId, int $coletorId, bool $isAdmin = false): int
    {
        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente || $cliente->status !== 'ativo') {
            throw new \InvalidArgumentException('Cliente inválido ou inativo.');
        }
        RotaScopeService::assertClientePermitido($clienteId, $coletorId, $isAdmin);

        $endereco = trim(implode(', ', array_filter([
            $cliente->logradouro,
            $cliente->numero,
            $cliente->bairro,
            $cliente->cidade,
            $cliente->uf,
        ])));

        $transporte = ColetaDefaults::transportador();
        $destino = ColetaDefaults::destinador();
        $motoristaNome = ColetorSelectHelper::isColetorAtivo($coletorId)
            ? ColetorSelectHelper::nomeById($coletorId)
            : '';

        $db = new Database();
        $db->beginTransaction();
        try {
            $operadoraId = OperadoraScope::getOperadoraId();
            $db->execute(
                'INSERT INTO coletas (operadora_id, cliente_id, coletor_id, status, doc_referencia, data_coleta, hora)
                 VALUES (?,?,?,?,?,?,?)',
                [
                    $operadoraId,
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
                    $motoristaNome,
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
        $motoristaNome = trim((string)($dados['motorista_nome'] ?? ''));
        if ($motoristaNome === '') {
            throw new \InvalidArgumentException('Selecione o motorista (coletor).');
        }

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
            'motorista_nome' => $motoristaNome,
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

    /**
     * Salva relatório e evidências no rascunho (sem gerar MTR).
     *
     * @param array<string, array{name:string,type:string,tmp_name:string,error:int}> $filesByKey evidencia_1..3
     * @return array{itens:int,evidencias:int,motorista:string}
     */
    public static function salvarRascunhoFinal(int $coletaId, string $relatorio, array $filesByKey = []): array
    {
        self::assertRascunho($coletaId);
        self::assertRequisitosBasicos($coletaId);

        EntityColeta::update($coletaId, ['relatorio' => trim($relatorio)]);

        foreach ($filesByKey as $key => $file) {
            if (!is_array($file) || empty($file['tmp_name'])) {
                continue;
            }
            $ordem = (int)preg_replace('/\D/', '', (string)$key);
            if ($ordem < 1 || $ordem > 3) {
                continue;
            }
            self::substituirEvidencia($coletaId, $ordem, $file);
        }

        if (!self::rascunhoFinalConferido($coletaId)) {
            throw new \InvalidArgumentException('Informe o relatório ou envie ao menos uma foto antes de salvar.');
        }

        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);

        return [
            'itens' => EntityColetaItem::countByColeta($coletaId),
            'evidencias' => EntityColetaEvidencia::countByColeta($coletaId),
            'motorista' => trim((string)($snapshot->motorista_nome ?? '')),
        ];
    }

    public static function rascunhoFinalConferido(int $coletaId): bool
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'rascunho') {
            return false;
        }

        return trim((string)($coleta->relatorio ?? '')) !== ''
            || EntityColetaEvidencia::countByColeta($coletaId) > 0;
    }

    /**
     * Finaliza a coleta (operacional). Com SINIR ativo, o número MTR só é gravado após registro no SINIR.
     *
     * @return array{numero_mtr:?int,sinir:array{ok:bool,skipped?:bool,message:string}|null}
     */
    public static function finalizar(int $coletaId, array $files = []): array
    {
        self::assertRascunho($coletaId);
        self::assertRequisitosBasicos($coletaId);
        self::assertDataRecebimentoParaFinalizar($coletaId);

        if (!empty($files)) {
            EvidenceStorageService::saveBatchForColeta($coletaId, $files);
        } elseif (!self::rascunhoFinalConferido($coletaId)) {
            throw new \InvalidArgumentException(
                'Salve o rascunho (relatório e/ou fotos) e confira os dados antes de finalizar.'
            );
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            throw new \InvalidArgumentException('Coleta não encontrada.');
        }

        $db = new Database();
        $db->beginTransaction();
        try {
            $db->execute(
                'UPDATE coletas SET status = ?, finalized_at = NOW() WHERE id = ?',
                ['finalizada', $coletaId]
            );

            $numeroMtr = null;
            if (!SinirConfig::isEnabled()) {
                $numeroMtr = self::proximoNumeroMtr($db);
                $db->execute(
                    'UPDATE coletas SET numero_mtr = ? WHERE id = ?',
                    [$numeroMtr, $coletaId]
                );
            } else {
                $db->execute(
                    'UPDATE coletas SET sinir_status = ? WHERE id = ?',
                    ['pendente', $coletaId]
                );
            }

            $dias = ColetaDefaults::diasProximaColeta();
            $db->execute(
                'UPDATE clientes SET proxima_coleta = DATE_ADD(CURDATE(), INTERVAL ? DAY), prioridade = ? WHERE id = ?',
                [$dias, 'normal', $coleta->cliente_id]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $sinirResult = null;
        if (SinirConfig::isEnabled()) {
            $sinirResult = Sinir\SinirService::enviarColeta($coletaId, false);
            $coletaAtual = EntityColeta::getById($coletaId);
            if ($coletaAtual && $coletaAtual->numero_mtr) {
                $numeroMtr = (int)$coletaAtual->numero_mtr;
            }
        }

        return [
            'numero_mtr' => $numeroMtr,
            'sinir' => $sinirResult,
        ];
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
        $q = RotaScopeService::paradasDoDiaQuery($coletorId, $isAdmin);
        $join = $q['join'];
        $where = $q['where'];
        $params = $q['params'];

        if (!$somentePendentes) {
            $where = "c.status = 'ativo' AND c.operadora_id = ?";
            $params = [OperadoraScope::getOperadoraId()];
            if (!$isAdmin) {
                if (!RotaScopeService::operadoraTemClientesEmRotas()) {
                    $where .= ' AND 1=0';
                } else {
                    $join = ' INNER JOIN rota_atribuicoes ra ON ra.cliente_id = c.id AND ra.operadora_id = c.operadora_id';
                }
            }
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

    private static function assertRequisitosBasicos(int $coletaId): void
    {
        if (EntityColetaItem::countByColeta($coletaId) === 0) {
            throw new \InvalidArgumentException('Adicione ao menos um resíduo antes de continuar.');
        }

        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);
        if (!$snapshot || trim((string)$snapshot->motorista_nome) === '') {
            throw new \InvalidArgumentException('Salve os dados de transporte (motorista) antes de continuar.');
        }
    }

    /** Rascunho pode ficar sem data; finalização exige data de chegada no destinador. */
    private static function assertDataRecebimentoParaFinalizar(int $coletaId): void
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            throw new \InvalidArgumentException('Coleta não encontrada.');
        }

        $data = trim((string)($coleta->data_recebimento ?? ''));
        if ($data === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            throw new \InvalidArgumentException(
                'Informe a data de recebimento no destinador (aba Transporte) e clique em "Salvar e continuar" antes de finalizar.'
            );
        }

        if ($coleta->situacao_recebimento !== 'recebido') {
            EntityColeta::update($coletaId, ['situacao_recebimento' => 'recebido']);
        }
    }

    /** @param array{name:string,type:string,tmp_name:string,error:int,size?:int} $file */
    private static function substituirEvidencia(int $coletaId, int $ordem, array $file): void
    {
        $existente = EntityColetaEvidencia::getByColetaOrdem($coletaId, $ordem);
        if ($existente) {
            self::unlinkEvidenciaArquivo($existente->arquivo);
            EntityColetaEvidencia::deleteById($existente->id);
        }

        $saved = EvidenceStorageService::saveUploaded($coletaId, $ordem, $file);
        if ($saved === null) {
            throw new \InvalidArgumentException('Foto '.$ordem.' inválida ou maior que 5 MB (JPG/PNG/WebP).');
        }

        EntityColetaEvidencia::insert([
            'coleta_id' => $coletaId,
            'ordem' => $ordem,
            'arquivo' => $saved['arquivo'],
            'mime' => $saved['mime'],
        ]);
    }

    private static function unlinkEvidenciaArquivo(string $arquivoRelativo): void
    {
        $path = dirname(__DIR__, 2).'/storage/'.ltrim($arquivoRelativo, '/');
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
