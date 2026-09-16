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
}
