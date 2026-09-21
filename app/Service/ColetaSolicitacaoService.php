<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\ColetaSolicitacao;
use App\Model\Entity\Plano as EntityPlano;
use InvalidArgumentException;

class ColetaSolicitacaoService
{
    private const HORAS_MIN_APOS_COLETA = 48;
    private const DIAS_ANTECEDENCIA_MIN = 2;

    /** @return array{limite:int,usadas:int,pendentes:int,restantes:int,periodo_label:string,mes_ref:string} */
    public static function resumoCota(int $clienteId, ?string $refDate = null): array
    {
        $refDate = $refDate ?? date('Y-m-d');
        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente) {
            return self::emptyResumo($refDate);
        }

        $plano = (int)($cliente->plano_id ?? 0) > 0 ? EntityPlano::getById((int)$cliente->plano_id) : null;
        $quota = self::resolveQuota($plano);
        $range = self::periodRangeForDate($refDate, $quota['periodo_meses']);
        $usadas = self::countConsumo($clienteId, $range['inicio'], $range['fim'], false);
        $pendentes = self::countConsumo($clienteId, $range['inicio'], $range['fim'], true, true);
        $limite = $quota['limite'];
        $ocupadas = $usadas + $pendentes;
        $restantes = $limite > 0 ? max(0, $limite - $ocupadas) : 0;

        return [
            'limite' => $limite,
            'usadas' => $usadas,
            'pendentes' => $pendentes,
            'restantes' => $restantes,
            'periodo_label' => $range['label'],
            'mes_ref' => substr($refDate, 0, 7),
        ];
    }

    /** @return array<string, mixed> */
    public static function validarNovaSolicitacao(int $clienteId, string $dataDesejada, ?int $ignoreSolicitacaoId = null): array
    {
        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente || ($cliente->status ?? '') !== 'ativo') {
            throw new InvalidArgumentException('Cliente não encontrado ou inativo.');
        }

        $dataDesejada = self::normalizeDate($dataDesejada);
        self::assertDataDesejadaMinima($dataDesejada);
        self::assertIntervaloUltimaColeta($clienteId, $dataDesejada);

        if (ColetaSolicitacao::hasPendente($clienteId)) {
            throw new InvalidArgumentException('Já existe uma solicitação pendente. Aguarde a resposta da equipe.');
        }

        $plano = (int)($cliente->plano_id ?? 0) > 0 ? EntityPlano::getById((int)$cliente->plano_id) : null;
        $quota = self::resolveQuota($plano);
        $range = self::periodRangeForDate($dataDesejada, $quota['periodo_meses']);
        $consumo = self::countConsumo($clienteId, $range['inicio'], $range['fim'], true, false, $ignoreSolicitacaoId);
        $tipo = ($quota['limite'] <= 0 || $consumo + 1 > $quota['limite']) ? 'extra' : 'inclusa';

        return [
            'data_desejada' => $dataDesejada,
            'tipo' => $tipo,
            'cota' => self::resumoCota($clienteId, $dataDesejada),
        ];
    }

    public static function criar(int $clienteId, int $clienteUsuarioId, string $dataDesejada, string $motivo = ''): int
    {
        $check = self::validarNovaSolicitacao($clienteId, $dataDesejada);
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('Informe o motivo ou observação da solicitação.');
        }

        return ColetaSolicitacao::insert([
            'cliente_id' => $clienteId,
            'cliente_usuario_id' => $clienteUsuarioId,
            'data_desejada' => $check['data_desejada'],
            'status' => 'pendente',
            'tipo' => $check['tipo'],
            'motivo_gerador' => mb_substr($motivo, 0, 2000),
        ]);
    }

    public static function cancelar(int $solicitacaoId, int $clienteId): void
    {
        $s = ColetaSolicitacao::getById($solicitacaoId);
        if (!$s || $s->cliente_id !== $clienteId || $s->status !== 'pendente') {
            throw new InvalidArgumentException('Solicitação não encontrada ou não pode ser cancelada.');
        }
        ColetaSolicitacao::update($solicitacaoId, ['status' => 'cancelada']);
    }

    public static function recusar(int $solicitacaoId, int $adminUsuarioId, string $resposta): void
    {
        $s = ColetaSolicitacao::getById($solicitacaoId);
        if (!$s || $s->status !== 'pendente') {
            throw new InvalidArgumentException('Solicitação inválida.');
        }
        $resposta = trim($resposta);
        if ($resposta === '') {
            throw new InvalidArgumentException('Informe o motivo da recusa.');
        }
        ColetaSolicitacao::update($solicitacaoId, [
            'status' => 'recusada',
            'resposta_admin' => mb_substr($resposta, 0, 2000),
            'aprovado_por_usuario_id' => $adminUsuarioId,
            'aprovado_em' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function aprovar(
        int $solicitacaoId,
        int $adminUsuarioId,
        ?string $dataAprovada = null,
        ?float $valorExtra = null,
        string $respostaAdmin = ''
    ): void {
        $s = ColetaSolicitacao::getById($solicitacaoId);
        if (!$s || $s->status !== 'pendente') {
            throw new InvalidArgumentException('Solicitação inválida.');
        }

        $dataAprovada = self::normalizeDate($dataAprovada ?: $s->data_desejada);
        $check = self::validarNovaSolicitacao($s->cliente_id, $dataAprovada, $solicitacaoId);
        $tipo = $check['tipo'];

        if ($tipo === 'extra') {
            if ($valorExtra === null || $valorExtra <= 0) {
                throw new InvalidArgumentException('Informe o valor negociado para coleta extra.');
            }
        } else {
            $valorExtra = null;
        }

        ColetaSolicitacao::update($solicitacaoId, [
            'status' => 'aprovada',
            'tipo' => $tipo,
            'data_aprovada' => $dataAprovada,
            'valor_cobranca_extra' => $valorExtra,
            'resposta_admin' => trim($respostaAdmin) !== '' ? mb_substr(trim($respostaAdmin), 0, 2000) : null,
            'aprovado_por_usuario_id' => $adminUsuarioId,
            'aprovado_em' => date('Y-m-d H:i:s'),
        ]);

        EntityCliente::update($s->cliente_id, [
            'proxima_coleta' => $dataAprovada,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public static function listExtrasCompetencia(string $competenciaYm): array
    {
        $competenciaYm = preg_match('/^\d{4}-\d{2}$/', $competenciaYm) ? $competenciaYm : date('Y-m');
        $inicio = $competenciaYm.'-01';
        $fim = date('Y-m-t', strtotime($inicio));
        $db = new Database();
        $stmt = $db->execute(
            "SELECT cs.*, c.nome_fantasia AS cliente_nome
             FROM coleta_solicitacoes cs
             INNER JOIN clientes c ON c.id = cs.cliente_id AND c.operadora_id = cs.operadora_id
             WHERE cs.operadora_id = ? AND cs.status = 'aprovada' AND cs.tipo = 'extra'
             AND COALESCE(cs.data_aprovada, cs.data_desejada) BETWEEN ? AND ?
             ORDER BY cs.data_aprovada ASC, c.nome_fantasia ASC",
            [OperadoraScope::getOperadoraId(), $inicio, $fim]
        );
        $rows = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rows[] = [
                'id' => (int)$row['id'],
                'cliente_id' => (int)$row['cliente_id'],
                'cliente_nome' => (string)$row['cliente_nome'],
                'data' => (string)($row['data_aprovada'] ?? $row['data_desejada']),
                'valor' => (float)($row['valor_cobranca_extra'] ?? 0),
                'motivo' => (string)($row['motivo_gerador'] ?? ''),
            ];
        }

        return $rows;
    }

    /** @return array{limite:int,periodo_meses:int} */
    private static function resolveQuota(?EntityPlano $plano): array
    {
        if (!$plano) {
            return ['limite' => 0, 'periodo_meses' => 1];
        }
        $periodoMeses = (int)($plano->coletas_periodo_meses ?? 0);
        if ($periodoMeses > 1) {
            $porPeriodo = max(1, (int)($plano->coletas_por_periodo ?? 1));

            return ['limite' => $porPeriodo, 'periodo_meses' => $periodoMeses];
        }

        return ['limite' => max(0, (int)round((float)$plano->coletas_mensais)), 'periodo_meses' => 1];
    }

    /** @return array{inicio:string,fim:string,label:string} */
    private static function periodRangeForDate(string $date, int $periodoMeses): array
    {
        $ts = strtotime($date);
        $y = (int)date('Y', $ts);
        $m = (int)date('n', $ts);
        if ($periodoMeses <= 1) {
            $inicio = date('Y-m-01', $ts);
            $fim = date('Y-m-t', $ts);
            $label = 'Mês '.date('m/Y', $ts);

            return compact('inicio', 'fim', 'label');
        }
        $block = (int)floor(($m - 1) / $periodoMeses);
        $mesInicio = $block * $periodoMeses + 1;
        $inicio = sprintf('%04d-%02d-01', $y, $mesInicio);
        $mesFim = min(12, $mesInicio + $periodoMeses - 1);
        $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $y, $mesFim)));
        $label = 'Período '.date('m/Y', strtotime($inicio)).' — '.date('m/Y', strtotime($fim));

        return compact('inicio', 'fim', 'label');
    }

    private static function countConsumo(
        int $clienteId,
        string $inicio,
        string $fim,
        bool $incluirPendentes,
        bool $somentePendentes = false,
        ?int $ignoreSolicitacaoId = null
    ): int {
        $db = new Database();
        $opId = OperadoraScope::getOperadoraId();

        $coletas = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM coletas
             WHERE cliente_id = ? AND operadora_id = ? AND status = 'finalizada'
             AND data_coleta BETWEEN ? AND ?",
            [$clienteId, $opId, $inicio, $fim]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        if ($somentePendentes) {
            $sql = "SELECT COUNT(*) AS qtd FROM coleta_solicitacoes
                    WHERE cliente_id = ? AND operadora_id = ? AND status = 'pendente'
                    AND data_desejada BETWEEN ? AND ?";
            $params = [$clienteId, $opId, $inicio, $fim];
        } else {
            $statuses = $incluirPendentes ? "('aprovada','pendente')" : "('aprovada')";
            $sql = "SELECT COUNT(*) AS qtd FROM coleta_solicitacoes
                    WHERE cliente_id = ? AND operadora_id = ? AND status IN {$statuses}
                    AND COALESCE(data_aprovada, data_desejada) BETWEEN ? AND ?";
            $params = [$clienteId, $opId, $inicio, $fim];
        }
        if ($ignoreSolicitacaoId !== null && $ignoreSolicitacaoId > 0) {
            $sql .= ' AND id != ?';
            $params[] = $ignoreSolicitacaoId;
        }

        $solic = (int)$db->execute($sql, $params)->fetch(\PDO::FETCH_ASSOC)['qtd'];

        return $coletas + $solic;
    }

    private static function assertDataDesejadaMinima(string $dataDesejada): void
    {
        $min = date('Y-m-d', strtotime('+'.self::DIAS_ANTECEDENCIA_MIN.' days'));
        if ($dataDesejada < $min) {
            throw new InvalidArgumentException(
                'A data desejada deve ser com pelo menos '.self::DIAS_ANTECEDENCIA_MIN.' dias de antecedência.'
            );
        }
    }

    private static function assertIntervaloUltimaColeta(int $clienteId, string $dataDesejada): void
    {
        $db = new Database();
        $row = $db->execute(
            "SELECT data_coleta, finalized_at FROM coletas
             WHERE cliente_id = ? AND operadora_id = ? AND status = 'finalizada'
             ORDER BY COALESCE(finalized_at, CONCAT(data_coleta, ' 23:59:59')) DESC LIMIT 1",
            [$clienteId, OperadoraScope::getOperadoraId()]
        )->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $ref = !empty($row['finalized_at'])
            ? strtotime((string)$row['finalized_at'])
            : strtotime((string)$row['data_coleta'].' 23:59:59');
        $limite = $ref + self::HORAS_MIN_APOS_COLETA * 3600;
        $inicioDesejada = strtotime($dataDesejada.' 00:00:00');
        if ($inicioDesejada < $limite) {
            throw new InvalidArgumentException(
                'Aguarde pelo menos '.self::HORAS_MIN_APOS_COLETA.' horas após a última coleta finalizada.'
            );
        }
    }

    private static function normalizeDate(string $date): string
    {
        $date = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new InvalidArgumentException('Data inválida.');
        }

        return $date;
    }

    /** @return array{limite:int,usadas:int,pendentes:int,restantes:int,periodo_label:string,mes_ref:string} */
    private static function emptyResumo(string $refDate): array
    {
        return [
            'limite' => 0,
            'usadas' => 0,
            'pendentes' => 0,
            'restantes' => 0,
            'periodo_label' => '',
            'mes_ref' => substr($refDate, 0, 7),
        ];
    }
}
