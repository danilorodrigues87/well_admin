<?php

namespace App\Service;

use App\Common\CobrancaConfig;
use App\Common\InterConfig;
use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Db\Pagination;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\InterCobranca as EntityInterCobranca;
use PDO;
use App\Service\Inter\InterCobrancaService;
use App\Service\Inter\InterPayloadBuilder;
use App\Service\Inter\InterWebhookService;

class FaturamentoService
{
    /**
     * @param array{plano_id?:int,situacao?:string,busca?:string} $filtros
     * @return array{rows:list<array<string,mixed>>,competencia:string,total:int,page:int,pages:int}
     */
    public static function relatorioCompetencia(
        string $competenciaYm,
        array $filtros = [],
        int $page = 1,
        int $perPage = 25
    ): array {
        $competenciaYm = self::normalizeCompetencia($competenciaYm);
        $perPage = max(10, min(50, $perPage));
        $total = self::countFaturamentoClientes($competenciaYm, $filtros);
        $pagination = new Pagination($total, $page, $perPage);
        $clientes = self::listFaturamentoClientes($competenciaYm, $filtros, $pagination->getLimit());
        $rows = [];

        foreach ($clientes as $row) {
            $cliente = $row['cliente'];
            $calculo = PlanoCobrancaService::calcularMes($cliente->id, $competenciaYm);
            $emitida = $row['emitida'];

            $rows[] = [
                'cliente_id' => $cliente->id,
                'cliente_nome' => $cliente->nome_fantasia,
                'plano_nome' => $cliente->plano_nome,
                'email' => $cliente->email,
                'valor_fixo' => $calculo['valor_fixo'],
                'valor_residuos' => $calculo['valor_residuos'],
                'valor_total' => $calculo['valor_total'],
                'situacao' => $emitida ? 'emitida' : 'pendente',
                'inter_cobranca_id' => $row['inter_cobranca_id'],
                'inter_status' => $row['inter_status'],
                'validacao' => InterPayloadBuilder::build(
                    $cliente,
                    $competenciaYm,
                    max(InterPayloadBuilder::VALOR_MINIMO_INTER, $calculo['valor_total']),
                    date('Y-m-d', strtotime('+10 days')),
                    null
                )['error'],
            ];
        }

        return [
            'rows' => $rows,
            'competencia' => $competenciaYm,
            'total' => $total,
            'page' => $pagination->getCurrentPage(),
            'pages' => $pagination->getTotalPages(),
        ];
    }

    /** @param array{plano_id?:int,situacao?:string,busca?:string} $filtros */
    public static function countFaturamentoClientes(string $competenciaYm, array $filtros = []): int
    {
        [$where, $params] = self::faturamentoWhere($competenciaYm, $filtros);
        $db = new Database();
        $row = $db->execute(
            'SELECT COUNT(*) AS qtd
             FROM clientes c
             LEFT JOIN inter_cobrancas ic ON ic.cliente_id = c.id
               AND ic.competencia = ?
               AND ic.operadora_id = c.operadora_id
             WHERE '.$where,
            $params
        )->fetch(PDO::FETCH_ASSOC);

        return (int)($row['qtd'] ?? 0);
    }

    /**
     * @param array{plano_id?:int,situacao?:string,busca?:string} $filtros
     * @return list<array{cliente:EntityCliente,emitida:bool,inter_cobranca_id:?int,inter_status:?string}>
     */
    private static function listFaturamentoClientes(string $competenciaYm, array $filtros, string $limit): array
    {
        [$where, $params] = self::faturamentoWhere($competenciaYm, $filtros);
        $db = new Database();
        $stmt = $db->execute(
            'SELECT c.*, p.nome AS plano_nome, ic.id AS inter_cobranca_id, ic.status AS inter_status
             FROM clientes c
             LEFT JOIN planos p ON p.id = c.plano_id AND p.operadora_id = c.operadora_id
             LEFT JOIN inter_cobrancas ic ON ic.cliente_id = c.id
               AND ic.competencia = ?
               AND ic.operadora_id = c.operadora_id
             WHERE '.$where.'
             ORDER BY c.nome_fantasia ASC
             LIMIT '.$limit,
            $params
        );

        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cliente = EntityCliente::fromRow($row);
            $items[] = [
                'cliente' => $cliente,
                'emitida' => !empty($row['inter_cobranca_id']),
                'inter_cobranca_id' => isset($row['inter_cobranca_id']) ? (int)$row['inter_cobranca_id'] : null,
                'inter_status' => isset($row['inter_status']) ? (string)$row['inter_status'] : null,
            ];
        }

        return $items;
    }

    /**
     * @param array{plano_id?:int,situacao?:string,busca?:string} $filtros
     * @return array{0:string,1:array<int,mixed>}
     */
    private static function faturamentoWhere(string $competenciaYm, array $filtros): array
    {
        $competenciaYm = self::normalizeCompetencia($competenciaYm);
        $where = "c.status = 'ativo' AND c.plano_id IS NOT NULL AND c.operadora_id = ?";
        $params = [$competenciaYm, OperadoraScope::getOperadoraId()];

        $planoId = (int)($filtros['plano_id'] ?? 0);
        if ($planoId > 0) {
            $where .= ' AND c.plano_id = ?';
            $params[] = $planoId;
        }

        $busca = trim((string)($filtros['busca'] ?? ''));
        if ($busca !== '') {
            $where .= ' AND (c.nome_fantasia LIKE ? OR c.razao_social LIKE ?)';
            $like = '%'.$busca.'%';
            $params[] = $like;
            $params[] = $like;
        }

        $situacaoFiltro = trim((string)($filtros['situacao'] ?? ''));
        if ($situacaoFiltro === 'pendente') {
            $where .= ' AND ic.id IS NULL';
        } elseif ($situacaoFiltro === 'emitida') {
            $where .= ' AND ic.id IS NOT NULL';
        }

        return [$where, $params];
    }

    /**
     * @param list<array{cliente_id:int,valor_final:float,observacao?:string}> $itens
     * @return array{ok:bool,emitidos:int,erros:list<array{cliente_id:int,cliente_nome:string,error:string}>,detalhes:list<array<string,mixed>>}
     */
    public static function emitirLote(
        string $competenciaYm,
        string $dataVencimento,
        array $itens,
        ?array $multaMoraOverride = null,
        bool $enviarEmail = false
    ): array {
        if (!InterConfig::isConfigured()) {
            return [
                'ok' => false,
                'emitidos' => 0,
                'erros' => [['cliente_id' => 0, 'cliente_nome' => '—', 'error' => 'Integração Inter não configurada']],
                'detalhes' => [],
            ];
        }

        $competenciaYm = self::normalizeCompetencia($competenciaYm);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataVencimento)) {
            return [
                'ok' => false,
                'emitidos' => 0,
                'erros' => [['cliente_id' => 0, 'cliente_nome' => '—', 'error' => 'Data de vencimento inválida']],
                'detalhes' => [],
            ];
        }

        $inter = new InterCobrancaService();
        $emitidos = 0;
        $erros = [];
        $detalhes = [];
        $multaMoraSnapshot = CobrancaConfig::snapshot($multaMoraOverride);

        foreach ($itens as $item) {
            $clienteId = (int)($item['cliente_id'] ?? 0);
            $valorFinal = round((float)($item['valor_final'] ?? 0), 2);
            $observacao = trim((string)($item['observacao'] ?? ''));

            $cliente = EntityCliente::getById($clienteId);
            if (!$cliente) {
                $erros[] = ['cliente_id' => $clienteId, 'cliente_nome' => '#'.$clienteId, 'error' => 'Cliente não encontrado'];
                continue;
            }

            if (EntityInterCobranca::getByClienteCompetencia($clienteId, $competenciaYm)) {
                $erros[] = [
                    'cliente_id' => $clienteId,
                    'cliente_nome' => $cliente->nome_fantasia,
                    'error' => 'Já existe boleto para esta competência',
                ];
                continue;
            }

            $calculo = PlanoCobrancaService::calcularMes($clienteId, $competenciaYm);
            $built = InterPayloadBuilder::build($cliente, $competenciaYm, $valorFinal, $dataVencimento, $multaMoraOverride);
            if (!$built['ok'] || !is_array($built['payload'])) {
                $erros[] = [
                    'cliente_id' => $clienteId,
                    'cliente_nome' => $cliente->nome_fantasia,
                    'error' => $built['error'] ?? 'Payload inválido',
                ];
                continue;
            }

            $result = $inter->emitirEPersistir($clienteId, $built['payload'], [
                'competencia' => $competenciaYm,
                'valor_calculado' => $calculo['valor_total'],
                'detalhes_json' => json_encode([
                    'valor_fixo' => $calculo['valor_fixo'],
                    'valor_residuos' => $calculo['valor_residuos'],
                    'itens' => $calculo['itens'],
                ], JSON_UNESCAPED_UNICODE),
                'multa_mora_json' => json_encode($multaMoraSnapshot, JSON_UNESCAPED_UNICODE),
                'observacao_ajuste' => $observacao !== '' ? $observacao : null,
            ]);

            if (!$result['ok'] || !$result['entity']) {
                $erros[] = [
                    'cliente_id' => $clienteId,
                    'cliente_nome' => $cliente->nome_fantasia,
                    'error' => $result['error'] ?? 'Falha na emissão',
                ];
                continue;
            }

            $enrich = self::enriquecerCobranca($result['entity']->id);
            $entity = EntityInterCobranca::getById($result['entity']->id);

            $emailOk = null;
            $emailError = null;
            if ($enviarEmail && $entity) {
                $mail = MailService::enviarBoleto($entity, $cliente);
                $emailOk = $mail['ok'];
                $emailError = $mail['error'];
            }

            $emitidos++;
            $detalhes[] = [
                'cliente_id' => $clienteId,
                'cliente_nome' => $cliente->nome_fantasia,
                'inter_cobranca_id' => $result['entity']->id,
                'codigo_solicitacao' => $result['entity']->codigo_solicitacao,
                'enriquecido' => $enrich['ok'],
                'email_ok' => $emailOk,
                'email_error' => $emailError,
            ];
        }

        return [
            'ok' => $emitidos > 0 && $erros === [],
            'emitidos' => $emitidos,
            'erros' => $erros,
            'detalhes' => $detalhes,
        ];
    }

    /**
     * Consulta o Inter e atualiza status, linha, PIX e PDF local.
     *
     * @return array{ok:bool,error:?string,status:?string,status_anterior:?string,alterou:bool}
     */
    public static function sincronizarCobranca(int $interCobrancaId): array
    {
        $entity = EntityInterCobranca::getById($interCobrancaId);
        if (!$entity) {
            return ['ok' => false, 'error' => 'Cobrança não encontrada', 'status' => null, 'status_anterior' => null, 'alterou' => false];
        }

        $statusAnterior = $entity->status;
        $result = self::enriquecerCobranca($interCobrancaId);
        if (!$result['ok']) {
            return [
                'ok' => false,
                'error' => $result['error'],
                'status' => $statusAnterior,
                'status_anterior' => $statusAnterior,
                'alterou' => false,
            ];
        }

        $atualizada = EntityInterCobranca::getById($interCobrancaId);
        $statusNovo = $atualizada?->status ?? $statusAnterior;

        return [
            'ok' => true,
            'error' => null,
            'status' => $statusNovo,
            'status_anterior' => $statusAnterior,
            'alterou' => $statusNovo !== $statusAnterior,
        ];
    }

    /**
     * Baixa manual — apenas registro local (webhook falhou ou pagamento confirmado fora do fluxo).
     *
     * @return array{ok:bool,error:?string,status:?string}
     */
    public static function baixaManual(int $interCobrancaId): array
    {
        $entity = EntityInterCobranca::getById($interCobrancaId);
        if (!$entity) {
            return ['ok' => false, 'error' => 'Cobrança não encontrada', 'status' => null];
        }

        $status = mb_strtoupper(trim($entity->status));
        if ($status === 'PAGO') {
            return ['ok' => false, 'error' => 'Cobrança já está paga', 'status' => $status];
        }
        if ($status === 'CANCELADO') {
            return ['ok' => false, 'error' => 'Cobrança cancelada não pode receber baixa manual', 'status' => $status];
        }

        $entity->update(['status' => 'PAGO']);

        return ['ok' => true, 'error' => null, 'status' => 'PAGO'];
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public static function enriquecerCobranca(int $interCobrancaId): array
    {
        $entity = EntityInterCobranca::getById($interCobrancaId);
        if (!$entity) {
            return ['ok' => false, 'error' => 'Cobrança não encontrada'];
        }

        $inter = new InterCobrancaService();
        $consulta = $inter->consultarCobranca($entity->codigo_solicitacao);
        if (!$consulta['ok'] || !is_array($consulta['body'])) {
            return ['ok' => false, 'error' => $consulta['error'] ?? 'Falha ao consultar cobrança'];
        }

        $body = $consulta['body'];
        $linha = (string)($body['boleto']['linhaDigitavel'] ?? $body['linhaDigitavel'] ?? '');
        $pix = (string)($body['pix']['pixCopiaECola'] ?? $body['pixCopiaECola'] ?? '');
        $statusRaw = (string)($body['cobranca']['situacao'] ?? $body['situacao'] ?? '');
        $status = InterWebhookService::resolveStatus($statusRaw, $entity->status);

        $pdfPath = self::salvarPdf($inter, $entity->codigo_solicitacao, $entity->id);

        $entity->update([
            'linha_digitavel' => $linha !== '' ? $linha : null,
            'pix_copia_cola' => $pix !== '' ? $pix : null,
            'pdf_path' => $pdfPath,
            'status' => $status,
            'payload_response' => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);

        return ['ok' => true, 'error' => null];
    }

    private static function salvarPdf(InterCobrancaService $inter, string $codigoSolicitacao, int $entityId): ?string
    {
        $pdfResponse = $inter->obterPdfBase64($codigoSolicitacao);
        if (!$pdfResponse['ok'] || !is_array($pdfResponse['body'])) {
            return null;
        }

        $base64 = (string)($pdfResponse['body']['pdf'] ?? $pdfResponse['body']['PDF'] ?? '');
        if ($base64 === '') {
            return null;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            return null;
        }

        $dir = dirname(__DIR__, 2).'/storage/boletos';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $relative = 'storage/boletos/'.$entityId.'.pdf';
        $absolute = dirname(__DIR__, 2).'/'.$relative;
        file_put_contents($absolute, $binary);

        return $relative;
    }

    private static function normalizeCompetencia(string $competenciaYm): string
    {
        $competenciaYm = trim($competenciaYm);
        if (preg_match('/^\d{4}-\d{2}$/', $competenciaYm)) {
            return $competenciaYm;
        }
        if (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $competenciaYm, $m)) {
            return $m[1].'-'.$m[2];
        }

        return date('Y-m', strtotime('first day of last month'));
    }
}
