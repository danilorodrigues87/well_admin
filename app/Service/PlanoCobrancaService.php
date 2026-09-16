<?php

namespace App\Service;

use App\Common\Helpers\IbamaCodigoHelper;
use App\Common\OperadoraScope;
use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Plano as EntityPlano;
use App\Model\Entity\PlanoItem as EntityPlanoItem;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use PDO;

/**
 * Cobrança mensal = valor_mensal do plano + excedentes de resíduo coletados no mês.
 */
class PlanoCobrancaService
{
    /**
     * @return array{
     *   valor_fixo:float,
     *   valor_residuos:float,
     *   valor_total:float,
     *   itens: list<array{nome:string,coletado:float,saldo:float,excedente:float,valor:float,unidade:string,saldo_info?:string,origens?:list<array{mtr:int,data:string,quantidade:float}>}>,
     *   coletas_no_mes: int
     * }
     */
    public static function calcularMes(int $clienteId, string $competenciaYm): array
    {
        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente || !$cliente->plano_id) {
            return ['valor_fixo' => 0, 'valor_residuos' => 0, 'valor_total' => 0, 'itens' => []];
        }

        $plano = EntityPlano::getById((int)$cliente->plano_id);
        if (!$plano) {
            return ['valor_fixo' => 0, 'valor_residuos' => 0, 'valor_total' => 0, 'itens' => []];
        }

        [$ano, $mes] = array_map('intval', explode('-', $competenciaYm.'-01'));
        $inicio = sprintf('%04d-%02d-01', $ano, $mes);
        $fim = date('Y-m-t', strtotime($inicio));

        $coletadoMap = self::coletasPorTipoMap($clienteId, $inicio, $fim);
        $legacyByCod = self::coletasLegacyPorCodMap($clienteId, $inicio, $fim);
        $origensPorTipo = self::coletasPorTipoDetalhe($clienteId, $inicio, $fim);
        $coletasNoMes = self::countColetasFinalizadas($clienteId, $inicio, $fim);

        $planoItens = EntityPlanoItem::getByPlanoId($plano->id);
        usort($planoItens, fn (EntityPlanoItem $a, EntityPlanoItem $b) => $a->ordem <=> $b->ordem);

        /** @var list<EntityPlanoItem> $creditItems */
        $creditItems = [];
        /** @var list<EntityPlanoItem> $individual */
        $individual = [];
        /** @var array<string,list<EntityPlanoItem>> $sharedGroups */
        $sharedGroups = [];

        foreach ($planoItens as $item) {
            if ($item->gera_credito) {
                $creditItems[] = $item;
            } elseif ($item->saldo_compartilhado) {
                $key = (string)round($item->valor_excedente, 2);
                $sharedGroups[$key][] = $item;
            } else {
                $individual[] = $item;
            }
        }

        $valorResiduos = 0.0;
        /** @var list<array<string,mixed>> $detalhesOrder */
        $detalhesOrder = [];

        foreach ($sharedGroups as $items) {
            $poolSaldo = 0.0;
            /** @var array<int,float> $coletadoPorItem */
            $coletadoPorItem = [];
            $totalColetado = 0.0;

            foreach ($items as $item) {
                $poolSaldo += $item->saldo_incluso;
                $qtd = self::takeColetado($item->tipo_residuo_id, $coletadoMap, $legacyByCod);
                $coletadoPorItem[$item->id] = $qtd;
                $totalColetado += $qtd;
            }

            $excedenteGrupo = max(0.0, $totalColetado - $poolSaldo);
            $valorGrupo = round($excedenteGrupo * $items[0]->valor_excedente, 2);
            $valorResiduos += $valorGrupo;

            $poolLabel = count($items) > 1
                ? number_format($poolSaldo, 2, ',', '.').' (saldo compartilhado)'
                : null;

            $first = true;
            foreach ($items as $item) {
                $detalhesOrder[] = [
                    'ordem' => $item->ordem,
                    'nome' => $item->tipo_nome,
                    'coletado' => $coletadoPorItem[$item->id] ?? 0.0,
                    'saldo' => $item->saldo_incluso,
                    'saldo_info' => $poolLabel,
                    'excedente' => $first ? $excedenteGrupo : 0.0,
                    'valor' => $first ? $valorGrupo : 0.0,
                    'unidade' => $item->unidade,
                    'origens' => $origensPorTipo[$item->tipo_residuo_id] ?? [],
                ];
                $first = false;
            }
        }

        foreach ($creditItems as $item) {
            $qtd = self::takeColetado($item->tipo_residuo_id, $coletadoMap, $legacyByCod);
            $tarifa = abs($item->valor_excedente);
            $valor = round(-1 * $qtd * $tarifa, 2);
            $valorResiduos += $valor;
            $detalhesOrder[] = [
                'ordem' => $item->ordem,
                'nome' => $item->tipo_nome,
                'coletado' => $qtd,
                'saldo' => 0.0,
                'saldo_info' => 'Crédito — desconto na mensalidade',
                'excedente' => $qtd,
                'valor' => $valor,
                'unidade' => $item->unidade,
                'gera_credito' => true,
                'origens' => $origensPorTipo[$item->tipo_residuo_id] ?? [],
            ];
        }

        foreach ($individual as $item) {
            $qtd = self::takeColetado($item->tipo_residuo_id, $coletadoMap, $legacyByCod);
            $excedente = max(0.0, $qtd - $item->saldo_incluso);
            $valor = round($excedente * $item->valor_excedente, 2);
            $valorResiduos += $valor;
            $detalhesOrder[] = [
                'ordem' => $item->ordem,
                'nome' => $item->tipo_nome,
                'coletado' => $qtd,
                'saldo' => $item->saldo_incluso,
                'excedente' => $excedente,
                'valor' => $valor,
                'unidade' => $item->unidade,
                'origens' => $origensPorTipo[$item->tipo_residuo_id] ?? [],
            ];
        }

        usort($detalhesOrder, fn ($a, $b) => ($a['ordem'] ?? 0) <=> ($b['ordem'] ?? 0));
        $detalhes = [];
        foreach ($detalhesOrder as $row) {
            unset($row['ordem']);
            $detalhes[] = $row;
        }

        $valorFixo = (float)$plano->valor_mensal;

        return [
            'valor_fixo' => $valorFixo,
            'valor_residuos' => round($valorResiduos, 2),
            'valor_total' => round($valorFixo + $valorResiduos, 2),
            'itens' => $detalhes,
            'coletas_no_mes' => $coletasNoMes,
        ];
    }

    /** @return array<int, list<array{mtr:int,data:string,quantidade:float}>> */
    private static function coletasPorTipoDetalhe(int $clienteId, string $inicio, string $fim): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT ci.tipo_residuo_id, c.numero_mtr, c.data_coleta, ci.quantidade
             FROM coleta_itens ci
             INNER JOIN coletas c ON c.id = ci.coleta_id
             WHERE c.cliente_id = ?
               AND c.operadora_id = ?
               AND c.status = \'finalizada\'
               AND c.data_coleta BETWEEN ? AND ?
               AND ci.tipo_residuo_id IS NOT NULL
             ORDER BY ci.tipo_residuo_id, c.data_coleta, c.id',
            [$clienteId, OperadoraScope::getOperadoraId(), $inicio, $fim]
        );

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tipoId = (int)$row['tipo_residuo_id'];
            $map[$tipoId][] = [
                'mtr' => (int)$row['numero_mtr'],
                'data' => (string)$row['data_coleta'],
                'quantidade' => (float)$row['quantidade'],
            ];
        }

        return $map;
    }

    private static function countColetasFinalizadas(int $clienteId, string $inicio, string $fim): int
    {
        $db = new Database();
        $row = $db->execute(
            'SELECT COUNT(*) AS qtd FROM coletas
             WHERE cliente_id = ? AND operadora_id = ? AND status = \'finalizada\' AND data_coleta BETWEEN ? AND ?',
            [$clienteId, OperadoraScope::getOperadoraId(), $inicio, $fim]
        )->fetch(PDO::FETCH_ASSOC);

        return (int)($row['qtd'] ?? 0);
    }

    /** @return array<int,float> tipo_residuo_id => total */
    private static function coletasPorTipoMap(int $clienteId, string $inicio, string $fim): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT ci.tipo_residuo_id, SUM(ci.quantidade) AS total
             FROM coleta_itens ci
             INNER JOIN coletas c ON c.id = ci.coleta_id
             WHERE c.cliente_id = ?
               AND c.operadora_id = ?
               AND c.status = \'finalizada\'
               AND c.data_coleta BETWEEN ? AND ?
               AND ci.tipo_residuo_id IS NOT NULL
             GROUP BY ci.tipo_residuo_id',
            [$clienteId, OperadoraScope::getOperadoraId(), $inicio, $fim]
        );

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int)$row['tipo_residuo_id']] = (float)$row['total'];
        }

        return $map;
    }

    /** @return array<string,float> cod_ibama normalizado => total (coletas sem tipo_residuo_id) */
    private static function coletasLegacyPorCodMap(int $clienteId, string $inicio, string $fim): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT ci.cod_ibama, SUM(ci.quantidade) AS total
             FROM coleta_itens ci
             INNER JOIN coletas c ON c.id = ci.coleta_id
             WHERE c.cliente_id = ?
               AND c.operadora_id = ?
               AND c.status = \'finalizada\'
               AND c.data_coleta BETWEEN ? AND ?
               AND ci.tipo_residuo_id IS NULL
               AND ci.cod_ibama IS NOT NULL AND ci.cod_ibama != \'\'
             GROUP BY ci.cod_ibama',
            [$clienteId, OperadoraScope::getOperadoraId(), $inicio, $fim]
        );

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $cod = IbamaCodigoHelper::normalize((string)$row['cod_ibama']);
            if ($cod === '') {
                continue;
            }
            $map[$cod] = ($map[$cod] ?? 0) + (float)$row['total'];
        }

        return $map;
    }

    /** @param array<int,float> $coletadoMap @param array<string,float> $legacyByCod */
    private static function takeColetado(int $tipoResiduoId, array &$coletadoMap, array &$legacyByCod): float
    {
        if (isset($coletadoMap[$tipoResiduoId])) {
            $q = $coletadoMap[$tipoResiduoId];
            unset($coletadoMap[$tipoResiduoId]);

            return $q;
        }

        $tipo = EntityTipoResiduo::getById($tipoResiduoId);
        if (!$tipo) {
            return 0.0;
        }

        $cod = IbamaCodigoHelper::normalize($tipo->cod_ibama);
        if ($cod !== '' && isset($legacyByCod[$cod])) {
            $q = $legacyByCod[$cod];
            unset($legacyByCod[$cod]);

            return $q;
        }

        return 0.0;
    }
}
