<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Db\Pagination;
use PDO;

class RelatorioService
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, total: int, pagination: Pagination}
     */
    public static function coletas(array $filtros, int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = self::buildWhere($filtros);
        $total = self::countColetas($where, $params);
        $pagination = new Pagination($total, $page, $perPage);

        $db = new Database();
        $sql = 'SELECT c.id, c.numero_mtr, c.status, c.data_coleta, c.situacao_recebimento,
                       cl.nome_fantasia AS cliente_nome, u.nome AS coletor_nome,
                       (SELECT COALESCE(SUM(ci.quantidade), 0) FROM coleta_itens ci WHERE ci.coleta_id = c.id) AS peso_total
                FROM coletas c
                INNER JOIN clientes cl ON cl.id = c.cliente_id AND cl.operadora_id = c.operadora_id
                INNER JOIN usuarios u ON u.id = c.coletor_id AND u.operadora_id = c.operadora_id
                WHERE '.$where.'
                ORDER BY c.data_coleta DESC, c.id DESC
                LIMIT '.$pagination->getLimit();

        $rows = $db->execute($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return [
            'rows' => $rows,
            'total' => $total,
            'pagination' => $pagination,
        ];
    }

    /** @return array{0:string,1:array<int, mixed>} */
    private static function buildWhere(array $filtros): array
    {
        $where = "c.status != 'cancelada' AND c.operadora_id = ?";
        $params = [OperadoraScope::getOperadoraId()];

        $inicio = trim((string)($filtros['data_inicio'] ?? ''));
        $fim = trim((string)($filtros['data_fim'] ?? ''));
        if ($inicio !== '' && $fim !== '') {
            $where .= ' AND c.data_coleta BETWEEN ? AND ?';
            $params[] = $inicio;
            $params[] = $fim;
        } elseif ($inicio !== '') {
            $where .= ' AND c.data_coleta >= ?';
            $params[] = $inicio;
        } elseif ($fim !== '') {
            $where .= ' AND c.data_coleta <= ?';
            $params[] = $fim;
        }

        $clienteId = (int)($filtros['cliente_id'] ?? 0);
        if ($clienteId > 0) {
            $where .= ' AND c.cliente_id = ?';
            $params[] = $clienteId;
        }

        $status = trim((string)($filtros['status'] ?? ''));
        if (in_array($status, ['rascunho', 'finalizada'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        }

        return [$where, $params];
    }

    private static function countColetas(string $where, array $params): int
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT COUNT(*) AS qtd FROM coletas c WHERE '.$where,
            $params
        );

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function coletasParaExport(array $filtros, int $limit = 5000): array
    {
        [$where, $params] = self::buildWhere($filtros);

        $db = new Database();
        $sql = 'SELECT c.numero_mtr, c.status, c.data_coleta, c.situacao_recebimento,
                       cl.nome_fantasia AS cliente, cl.cnpj, u.nome AS coletor,
                       (SELECT COALESCE(SUM(ci.quantidade), 0) FROM coleta_itens ci WHERE ci.coleta_id = c.id) AS peso_kg
                FROM coletas c
                INNER JOIN clientes cl ON cl.id = c.cliente_id AND cl.operadora_id = c.operadora_id
                INNER JOIN usuarios u ON u.id = c.coletor_id AND u.operadora_id = c.operadora_id
                WHERE '.$where.'
                ORDER BY c.data_coleta DESC, c.id DESC
                LIMIT '.(int)$limit;

        return $db->execute($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function csvColetas(array $filtros): string
    {
        $rows = self::coletasParaExport($filtros);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }

        fputcsv($out, ['MTR', 'Status', 'Data', 'Recebimento', 'Cliente', 'CNPJ', 'Coletor', 'Peso (kg)'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['numero_mtr'] ?? '',
                $r['status'] ?? '',
                $r['data_coleta'] ?? '',
                $r['situacao_recebimento'] ?? '',
                $r['cliente'] ?? '',
                $r['cnpj'] ?? '',
                $r['coletor'] ?? '',
                $r['peso_kg'] ?? '',
            ], ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv !== false ? $csv : '';
    }

    /**
     * Coletas finalizadas de um cliente, agrupadas por mês e tipo de resíduo.
     *
     * @param array{modo?:string,competencia?:string,competencia_ini?:string,competencia_fim?:string,ano?:string,tipo_residuo_id?:int} $filtros
     * @return array{ok:bool,message:string,rows:array<int,array<string,mixed>>,total_kg:float,total_coletas:int,inicio:string,fim:string}
     */
    public static function coletasPorCliente(int $clienteId, array $filtros): array
    {
        $vazio = [
            'ok' => false,
            'message' => '',
            'rows' => [],
            'total_kg' => 0.0,
            'total_coletas' => 0,
            'inicio' => '',
            'fim' => '',
        ];
        if ($clienteId <= 0) {
            $vazio['message'] = 'Escolha o cliente.';

            return $vazio;
        }

        $periodo = self::periodoCliente($filtros);
        if (!$periodo['ok']) {
            $vazio['message'] = $periodo['message'];

            return $vazio;
        }

        $operadoraId = OperadoraScope::getOperadoraId();
        $tipoId = (int)($filtros['tipo_residuo_id'] ?? 0);
        $where = "c.operadora_id = ? AND c.cliente_id = ? AND c.status = 'finalizada'
               AND c.data_coleta BETWEEN ? AND ? AND ci.unidade = 'kg'";
        $params = [$operadoraId, $clienteId, $periodo['inicio'], $periodo['fim']];
        if ($tipoId > 0) {
            $where .= ' AND ci.tipo_residuo_id = ?';
            $params[] = $tipoId;
        }

        $db = new Database();
        $rows = $db->execute(
            'SELECT DATE_FORMAT(c.data_coleta, \'%Y-%m\') AS competencia,
                    COALESCE(tr.nome, ci.nome) AS tipo_nome,
                    COALESCE(NULLIF(rc.nome, \'\'), NULLIF(ci.classe_nome, \'\'), \'\') AS classe_nome,
                    COALESCE(NULLIF(rg.codigo, \'\'), NULLIF(ci.grupo_codigo, \'\'), \'\') AS grupo_codigo,
                    COUNT(DISTINCT c.id) AS coletas,
                    COALESCE(SUM(ci.quantidade), 0) AS kg
             FROM coletas c
             INNER JOIN coleta_itens ci ON ci.coleta_id = c.id
             LEFT JOIN tipos_residuos tr ON tr.id = ci.tipo_residuo_id
             LEFT JOIN residuo_classes rc ON rc.id = tr.classe_id
             LEFT JOIN residuo_grupos rg ON rg.id = tr.grupo_id
             WHERE '.$where.'
             GROUP BY DATE_FORMAT(c.data_coleta, \'%Y-%m\'),
                      COALESCE(tr.nome, ci.nome),
                      COALESCE(NULLIF(rc.nome, \'\'), NULLIF(ci.classe_nome, \'\'), \'\'),
                      COALESCE(NULLIF(rg.codigo, \'\'), NULLIF(ci.grupo_codigo, \'\'), \'\')
             ORDER BY competencia ASC, tipo_nome ASC',
            $params
        )->fetchAll(PDO::FETCH_ASSOC);

        $totalColetas = (int)$db->execute(
            'SELECT COUNT(DISTINCT c.id) AS qtd
             FROM coletas c
             INNER JOIN coleta_itens ci ON ci.coleta_id = c.id
             WHERE '.$where,
            $params
        )->fetch(PDO::FETCH_ASSOC)['qtd'];

        $totalKg = 0.0;
        foreach ($rows as $row) {
            $totalKg += (float)$row['kg'];
        }

        return [
            'ok' => true,
            'message' => '',
            'rows' => $rows,
            'total_kg' => $totalKg,
            'total_coletas' => $totalColetas,
            'inicio' => $periodo['inicio'],
            'fim' => $periodo['fim'],
        ];
    }

    /** @return array{ok:bool,message:string,inicio?:string,fim?:string} */
    public static function periodoCliente(array $filtros): array
    {
        $modo = (string)($filtros['modo'] ?? 'mes');
        if (!in_array($modo, ['mes', 'intervalo', 'ano'], true)) {
            $modo = 'mes';
        }

        if ($modo === 'ano') {
            $ano = (int)($filtros['ano'] ?? 0);
            if ($ano < 2000 || $ano > 2100) {
                return ['ok' => false, 'message' => 'Informe um ano válido.'];
            }

            return ['ok' => true, 'message' => '', 'inicio' => $ano.'-01-01', 'fim' => $ano.'-12-31'];
        }

        if ($modo === 'mes') {
            $comp = self::competencia($filtros['competencia'] ?? '');
            if ($comp === null) {
                return ['ok' => false, 'message' => 'Informe o mês.'];
            }

            return [
                'ok' => true,
                'message' => '',
                'inicio' => $comp.'-01',
                'fim' => date('Y-m-t', strtotime($comp.'-01')),
            ];
        }

        $ini = self::competencia($filtros['competencia_ini'] ?? '');
        $fim = self::competencia($filtros['competencia_fim'] ?? '');
        if ($ini === null || $fim === null) {
            return ['ok' => false, 'message' => 'Informe o mês inicial e o final.'];
        }
        if ($ini > $fim) {
            return ['ok' => false, 'message' => 'O mês inicial não pode ser depois do final.'];
        }
        $inicioTs = strtotime($ini.'-01');
        $fimTs = strtotime($fim.'-01');
        $meses = ((int)date('Y', $fimTs) - (int)date('Y', $inicioTs)) * 12
            + ((int)date('n', $fimTs) - (int)date('n', $inicioTs)) + 1;
        if ($meses > 36) {
            return ['ok' => false, 'message' => 'O intervalo máximo é de 36 meses.'];
        }

        return [
            'ok' => true,
            'message' => '',
            'inicio' => $ini.'-01',
            'fim' => date('Y-m-t', $fimTs),
        ];
    }

    public static function csvColetasPorCliente(int $clienteId, array $filtros, string $clienteNome): string
    {
        $result = self::coletasPorCliente($clienteId, $filtros);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }

        fputcsv($out, ['Cliente', 'Mês', 'Resíduo', 'Classificação', 'Coletas', 'Kg', 'Toneladas'], ';');
        foreach ($result['rows'] as $row) {
            $kg = (float)($row['kg'] ?? 0);
            fputcsv($out, [
                $clienteNome,
                self::mesExibicao((string)$row['competencia']),
                $row['tipo_nome'] ?? '',
                self::classificacaoExibicao((string)($row['classe_nome'] ?? ''), (string)($row['grupo_codigo'] ?? '')),
                $row['coletas'] ?? 0,
                number_format($kg, 3, ',', ''),
                number_format($kg / 1000, 3, ',', ''),
            ], ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv !== false ? $csv : '';
    }

    public static function classificacaoExibicao(string $classe, string $grupo): string
    {
        $classe = trim($classe);
        $grupo = trim($grupo);
        $curta = $classe;
        if (preg_match('/^Classe\s+(.+)$/u', $classe, $m)) {
            $curta = trim($m[1]);
        }
        if ($grupo !== '' && $curta !== '' && !preg_match('/(?:^|\s)'.preg_quote($grupo, '/').'(?:$|\s)/u', $curta)) {
            $curta = str_contains($curta, '—') ? $curta.' ('.$grupo.')' : $curta.' '.$grupo;
        }
        if ($curta === '' && $grupo !== '') {
            $curta = $grupo;
        }

        return $curta !== '' ? $curta : '—';
    }

    public static function mesExibicao(string $competencia): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})$/', $competencia, $m)) {
            return $competencia;
        }

        return $m[2].'/'.$m[1];
    }

    private static function competencia(mixed $valor): ?string
    {
        $valor = trim((string)$valor);
        if (!preg_match('/^(\d{4})-(\d{2})$/', $valor, $m)) {
            return null;
        }
        $mes = (int)$m[2];
        if ($mes < 1 || $mes > 12) {
            return null;
        }

        return $m[1].'-'.str_pad((string)$mes, 2, '0', STR_PAD_LEFT);
    }
}
