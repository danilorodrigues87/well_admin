<?php

namespace App\Service;

use App\Common\Helpers\ColetaMtrHelper;
use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\DmrDeclaracao as EntityDmrDeclaracao;
use PDO;

class DmrService
{
    /** @return array{ok:bool,message:string,id?:int} */
    public static function gerarOuAtualizar(int $clienteId, string $competencia, ?string $observacao = null): array
    {
        $competencia = self::normalizarCompetencia($competencia);
        if ($competencia === null) {
            return ['ok' => false, 'message' => 'Competência inválida (use YYYY-MM).'];
        }

        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente || $cliente->status !== 'ativo') {
            return ['ok' => false, 'message' => 'Cliente inválido.'];
        }

        $resumo = self::agregarColetas($clienteId, $competencia);
        $decl = EntityDmrDeclaracao::getByClienteCompetencia($clienteId, $competencia);
        if ($decl && $decl->status === 'fechada') {
            return ['ok' => false, 'message' => 'DMR fechada para esta competência. Reabra manualmente no banco se necessário.'];
        }

        $data = [
            'tot_coletas' => $resumo['tot_coletas'],
            'tot_kg' => $resumo['tot_kg'],
            'tot_com_mtr' => $resumo['tot_com_mtr'],
            'tot_com_cdf' => $resumo['tot_com_cdf'],
            'observacao' => $observacao !== null ? trim($observacao) : ($decl?->observacao ?? null),
        ];

        if ($decl) {
            EntityDmrDeclaracao::update($decl->id, $data);

            return ['ok' => true, 'message' => 'DMR atualizada.', 'id' => $decl->id];
        }

        $id = EntityDmrDeclaracao::insert([
            'operadora_id' => OperadoraScope::getOperadoraId(),
            'cliente_id' => $clienteId,
            'competencia' => $competencia,
            'status' => 'rascunho',
            ...$data,
        ]);

        return ['ok' => true, 'message' => 'DMR gerada.', 'id' => $id];
    }

    /** @return array{ok:bool,message:string} */
    public static function fechar(int $dmrId): array
    {
        $decl = EntityDmrDeclaracao::getById($dmrId);
        if (!$decl) {
            return ['ok' => false, 'message' => 'DMR não encontrada.'];
        }
        if ($decl->status === 'fechada') {
            return ['ok' => true, 'message' => 'DMR já estava fechada.'];
        }

        $resumo = self::agregarColetas($decl->cliente_id, $decl->competencia);
        EntityDmrDeclaracao::update($dmrId, [
            'status' => 'fechada',
            'fechada_em' => date('Y-m-d H:i:s'),
            'tot_coletas' => $resumo['tot_coletas'],
            'tot_kg' => $resumo['tot_kg'],
            'tot_com_mtr' => $resumo['tot_com_mtr'],
            'tot_com_cdf' => $resumo['tot_com_cdf'],
        ]);

        return ['ok' => true, 'message' => 'DMR fechada (snapshot da competência).'];
    }

    /**
     * @return array{tot_coletas:int,tot_kg:float,tot_com_mtr:int,tot_com_cdf:int,linhas:array<int,array<string,mixed>>}
     */
    public static function agregarColetas(int $clienteId, string $competencia): array
    {
        $competencia = self::normalizarCompetencia($competencia) ?? date('Y-m');
        [$inicio, $fim] = self::intervaloCompetencia($competencia);

        $db = new Database();
        $operadoraId = OperadoraScope::getOperadoraId();
        $stmt = $db->execute(
            'SELECT c.id, c.numero_relatorio, c.numero_mtr, c.data_coleta, c.sinir_status, c.sinir_cdf_codigo, c.cdf_tipo, c.cdf_path, c.status,
                    (SELECT COALESCE(SUM(ci.quantidade), 0) FROM coleta_itens ci WHERE ci.coleta_id = c.id AND ci.unidade = \'kg\') AS peso_kg
             FROM coletas c
             WHERE c.operadora_id = ? AND c.cliente_id = ? AND c.status = \'finalizada\'
               AND c.data_coleta BETWEEN ? AND ?
             ORDER BY c.data_coleta ASC, c.id ASC',
            [$operadoraId, $clienteId, $inicio, $fim]
        );

        $linhas = [];
        $totKg = 0.0;
        $totMtr = 0;
        $totCdf = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $coleta = EntityColeta::getById((int)$row['id']);
            $temMtr = $coleta && ColetaMtrHelper::temMtr($coleta);
            $temCdf = trim((string)($row['sinir_cdf_codigo'] ?? '')) !== ''
                || trim((string)($row['cdf_path'] ?? '')) !== '';
            if ($temMtr) {
                ++$totMtr;
            }
            if ($temCdf) {
                ++$totCdf;
            }
            $kg = (float)($row['peso_kg'] ?? 0);
            $totKg += $kg;
            $linhas[] = [
                'id' => (int)$row['id'],
                'numero_relatorio' => $row['numero_relatorio'],
                'numero_mtr' => $row['numero_mtr'],
                'data_coleta' => $row['data_coleta'],
                'peso_kg' => $kg,
                'tem_mtr' => $temMtr,
                'cdf_tipo' => $row['cdf_tipo'],
                'sinir_cdf_codigo' => $row['sinir_cdf_codigo'],
            ];
        }

        return [
            'tot_coletas' => count($linhas),
            'tot_kg' => round($totKg, 3),
            'tot_com_mtr' => $totMtr,
            'tot_com_cdf' => $totCdf,
            'linhas' => $linhas,
        ];
    }

    /** @return array{0:string,1:string} */
    public static function intervaloCompetencia(string $competencia): array
    {
        $competencia = self::normalizarCompetencia($competencia) ?? date('Y-m');
        $inicio = $competencia.'-01';
        $fim = date('Y-m-t', strtotime($inicio));

        return [$inicio, $fim];
    }

    public static function normalizarCompetencia(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})\/(\d{4})$/', $value, $m)) {
            return $m[2].'-'.$m[1];
        }

        return null;
    }
}
