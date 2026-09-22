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
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Transportadora as EntityTransportadora;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Model\Entity\Veiculo as EntityVeiculo;
use PDO;

class ColetaService
{
    public static function proximoNumeroRelatorio(Database $db, ?int $operadoraId = null): int
    {
        $operadoraId = $operadoraId ?? OperadoraScope::getOperadoraId();
        $db->execute(
            'INSERT INTO coleta_sequencia (operadora_id, ultimo_mtr, ultimo_relatorio) VALUES (?, 0, 0)
             ON DUPLICATE KEY UPDATE operadora_id = operadora_id',
            [$operadoraId]
        );
        $db->execute(
            'UPDATE coleta_sequencia SET ultimo_relatorio = ultimo_relatorio + 1 WHERE operadora_id = ?',
            [$operadoraId]
        );
        $row = $db->execute(
            'SELECT ultimo_relatorio FROM coleta_sequencia WHERE operadora_id = ?',
            [$operadoraId]
        )->fetch(PDO::FETCH_ASSOC);

        return (int)($row['ultimo_relatorio'] ?? 1);
    }

    /** @deprecated Use proximoNumeroRelatorio — mantido para scripts legados */
    public static function proximoNumeroMtr(Database $db, ?int $operadoraId = null): int
    {
        return self::proximoNumeroRelatorio($db, $operadoraId);
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

        $transportadora = EntityTransportadora::getPadrao();
        $destinador = EntityDestinador::getPadrao();
        if (!$transportadora || !$destinador) {
            throw new \InvalidArgumentException('Cadastre transportadora e destinador padrão antes de lançar coletas.');
        }
        $motoristaNome = ColetorSelectHelper::isColetorAtivo($coletorId)
            ? ColetorSelectHelper::nomeById($coletorId)
            : '';

        $db = new Database();
        $db->beginTransaction();
        try {
            $operadoraId = OperadoraScope::getOperadoraId();
            $db->execute(
                'INSERT INTO coletas (operadora_id, cliente_id, coletor_id, transportadora_id, destinador_id, status, doc_referencia, data_coleta, hora)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $operadoraId,
                    $clienteId,
                    $coletorId,
                    $transportadora->id,
                    $destinador->id,
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
                    $transportadora->nome,
                    $transportadora->cnpj,
                    $motoristaNome,
                    $destinador->nome,
                    $destinador->cnpj,
                    $destinador->endereco,
                    $destinador->telefone,
                    $destinador->responsavel,
                ]
            );

            $db->commit();
            return $coletaId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function salvarEtapaTransporte(int $coletaId, array $dados): void
    {
        self::assertRascunho($coletaId);

        $transportadoraId = (int)($dados['transportadora_id'] ?? 0);
        $transportadora = $transportadoraId > 0 ? EntityTransportadora::getById($transportadoraId) : null;
        if (!$transportadora || !$transportadora->ativo) {
            throw new \InvalidArgumentException('Selecione uma transportadora válida.');
        }

        $veiculoId = (int)($dados['veiculo_id'] ?? 0);
        $veiculo = $veiculoId > 0 ? EntityVeiculo::getById($veiculoId) : null;
        $motoristaNome = trim((string)($dados['motorista_nome'] ?? ''));
        if ($motoristaNome === '') {
            throw new \InvalidArgumentException('Selecione o coletor (motorista).');
        }

        $coletorId = (int)($dados['coletor_id'] ?? 0);
        $updateColeta = [
            'transportadora_id' => $transportadora->id,
            'veiculo_id' => $veiculo ? $veiculoId : null,
            'tratamento' => trim((string)($dados['tratamento'] ?? '')),
        ];
        if ($coletorId > 0 && ColetorSelectHelper::isColetorAtivo($coletorId)) {
            $updateColeta['coletor_id'] = $coletorId;
        }
        EntityColeta::update($coletaId, $updateColeta);

        EntityColetaSnapshot::update($coletaId, [
            'transportador_nome' => $transportadora->nome,
            'transportador_cnpj' => $transportadora->cnpj,
            'motorista_nome' => $motoristaNome,
            'veiculo_descricao' => $veiculo ? trim($veiculo->marca.' '.$veiculo->modelo) : trim((string)($dados['veiculo_descricao'] ?? '')),
            'veiculo_placa' => $veiculo ? $veiculo->placa : trim((string)($dados['veiculo_placa'] ?? '')),
        ]);
    }

    public static function salvarEtapaDestinador(int $coletaId, array $dados): void
    {
        self::assertRascunho($coletaId);

        $destinadorId = (int)($dados['destinador_id'] ?? 0);
        $destinador = $destinadorId > 0 ? EntityDestinador::getById($destinadorId) : null;
        if (!$destinador || !$destinador->ativo) {
            throw new \InvalidArgumentException('Selecione um destinador válido.');
        }

        EntityColeta::update($coletaId, [
            'destinador_id' => $destinador->id,
            'situacao_recebimento' => ($dados['situacao_recebimento'] ?? '') === 'recebido' ? 'recebido' : 'pendente',
            'data_recebimento' => ($dados['data_recebimento'] ?? '') ?: null,
        ]);

        EntityColetaSnapshot::update($coletaId, [
            'destinador_nome' => $destinador->nome,
            'destinador_cnpj' => $destinador->cnpj,
            'destinador_endereco' => $destinador->endereco,
            'destinador_telefone' => $destinador->telefone,
            'destinador_responsavel' => $destinador->responsavel,
            'observacao_destinador' => trim((string)($dados['observacao_destinador'] ?? '')),
        ]);
    }

    /** @deprecated Use salvarEtapaTransporte + salvarEtapaDestinador */
    public static function salvarTransporte(int $coletaId, array $dados): void
    {
        self::salvarEtapaTransporte($coletaId, $dados);
        if (!empty($dados['destinador_id']) || !empty($dados['data_recebimento'])) {
            self::salvarEtapaDestinador($coletaId, $dados);
        }
    }

    public static function salvarAssinaturaCliente(int $coletaId, string $dataUrl): void
    {
        self::assertRascunho($coletaId);
        if (!preg_match('#^data:image/(png|jpeg);base64,#i', $dataUrl, $m)) {
            throw new \InvalidArgumentException('Assinatura inválida.');
        }
        $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($raw === false || strlen($raw) > 500000) {
            throw new \InvalidArgumentException('Assinatura inválida ou muito grande.');
        }
        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : 'png';
        $dir = dirname(__DIR__, 2).'/storage/coletas/'.$coletaId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $rel = 'coletas/'.$coletaId.'/assinatura_cliente.'.$ext;
        $path = dirname(__DIR__, 2).'/storage/'.$rel;
        if (file_put_contents($path, $raw) === false) {
            throw new \RuntimeException('Não foi possível salvar a assinatura.');
        }
        EntityColeta::update($coletaId, ['assinatura_cliente_path' => $rel]);
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
     * @return array{numero_relatorio:int,numero_mtr:?int}
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
                'Salve o relatório (etapa 3) e confira os dados antes de concluir.'
            );
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            throw new \InvalidArgumentException('Coleta não encontrada.');
        }
        if (!$coleta->transportadora_id || !$coleta->destinador_id) {
            throw new \InvalidArgumentException('Salve transportadora (etapa 1) e destinador (etapa 4).');
        }

        $db = new Database();
        $db->beginTransaction();
        try {
            $numeroRelatorio = self::proximoNumeroRelatorio($db);
            $db->execute(
                'UPDATE coletas SET status = ?, finalized_at = NOW(), numero_relatorio = ?, sinir_status = NULL WHERE id = ?',
                ['finalizada', $numeroRelatorio, $coletaId]
            );

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

        return [
            'numero_relatorio' => $numeroRelatorio,
            'numero_mtr' => null,
        ];
    }

    /**
     * Emite MTR no SINIR (sob demanda, após relatório finalizado).
     *
     * @return array{ok:bool,message:string,numero_mtr:?int,details?:array<string,mixed>}
     */
    public static function gerarMtrSinir(int $coletaId, bool $force = false): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            throw new \InvalidArgumentException('Somente coletas finalizadas podem gerar MTR.');
        }

        $result = Sinir\SinirService::enviarColeta($coletaId, $force);
        $numeroMtr = null;
        $coletaAtual = EntityColeta::getById($coletaId);
        if ($coletaAtual && $coletaAtual->numero_mtr) {
            $numeroMtr = (int)$coletaAtual->numero_mtr;
        }

        return array_merge($result, ['numero_mtr' => $numeroMtr]);
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
                'Informe a data de encerramento no destinador (etapa 4) e salve antes de concluir o relatório.'
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
