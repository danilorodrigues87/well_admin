<?php

namespace App\Service;

use App\Model\Db\Database;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Plano as EntityPlano;
use App\Model\Entity\PlanoItem as EntityPlanoItem;
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
     *   itens: list<array{nome:string,coletado:float,saldo:float,excedente:float,valor:float,unidade:string}>
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

        $coletado = self::coletasPorResiduo($clienteId, $inicio, $fim);
        $planoItens = EntityPlanoItem::getByPlanoId($plano->id);

        $valorResiduos = 0.0;
        $detalhes = [];

        foreach ($planoItens as $item) {
            $key = self::matchKey($item);
            $qtd = (float)($coletado[$key] ?? 0);
            $excedente = max(0, $qtd - $item->saldo_incluso);
            $valor = round($excedente * $item->valor_excedente, 2);
            $valorResiduos += $valor;
            $detalhes[] = [
                'nome' => $item->nome,
                'coletado' => $qtd,
                'saldo' => $item->saldo_incluso,
                'excedente' => $excedente,
                'valor' => $valor,
                'unidade' => $item->unidade,
            ];
        }

        $valorFixo = (float)$plano->valor_mensal;

        return [
            'valor_fixo' => $valorFixo,
            'valor_residuos' => round($valorResiduos, 2),
            'valor_total' => round($valorFixo + $valorResiduos, 2),
            'itens' => $detalhes,
        ];
    }

    /** @return array<string,float> */
    private static function coletasPorResiduo(int $clienteId, string $inicio, string $fim): array
    {
        $db = new Database();
        $stmt = $db->execute(
            "SELECT ci.nome, ci.cod_ibama, ci.unidade, SUM(ci.quantidade) AS total
             FROM coleta_itens ci
             INNER JOIN coletas c ON c.id = ci.coleta_id
             WHERE c.cliente_id = ?
               AND c.status = 'finalizada'
               AND c.data_coleta BETWEEN ? AND ?
             GROUP BY ci.nome, ci.cod_ibama, ci.unidade",
            [$clienteId, $inicio, $fim]
        );

        $map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $key = self::rowKey($row);
            $map[$key] = ($map[$key] ?? 0) + (float)$row['total'];
        }
        return $map;
    }

    private static function matchKey(EntityPlanoItem $item): string
    {
        if ($item->cod_ibama) {
            return 'cod:'.trim($item->cod_ibama);
        }
        return 'nome:'.mb_strtolower(trim($item->nome));
    }

    /** @param array<string,mixed> $row */
    private static function rowKey(array $row): string
    {
        $cod = trim((string)($row['cod_ibama'] ?? ''));
        if ($cod !== '') {
            return 'cod:'.$cod;
        }
        return 'nome:'.mb_strtolower(trim((string)$row['nome']));
    }
}
