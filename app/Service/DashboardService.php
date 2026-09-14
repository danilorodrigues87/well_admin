<?php

namespace App\Service;

use App\Model\Db\Database;
use App\Model\Entity\Coleta as EntityColeta;

class DashboardService
{
    /** @return array<string, int> */
    public static function kpis(): array
    {
        $db = new Database();
        $hoje = date('Y-m-d');
        $mesInicio = date('Y-m-01');
        $mesFim = date('Y-m-t');

        $coletasMes = EntityColeta::count(
            "c.status = 'finalizada' AND c.data_coleta BETWEEN ? AND ?",
            [$mesInicio, $mesFim]
        );

        $rascunhos = EntityColeta::count("c.status = 'rascunho'", []);

        $urgentes = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes WHERE status = 'ativo' AND prioridade = 'urgente'"
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $atrasados = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes
             WHERE status = 'ativo' AND proxima_coleta IS NOT NULL AND proxima_coleta <= ?",
            [$hoje]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $semColetor = (int)$db->execute(
            'SELECT COUNT(*) AS qtd FROM rota_atribuicoes WHERE coletor_id IS NULL'
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $pendRecebimento = EntityColeta::count(
            "c.status = 'finalizada' AND c.situacao_recebimento = 'pendente'",
            []
        );

        $clientesAtivos = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes WHERE status = 'ativo'"
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        return [
            'coletas_mes' => $coletasMes,
            'rascunhos' => $rascunhos,
            'urgentes' => $urgentes,
            'atrasados' => $atrasados,
            'sem_coletor' => $semColetor,
            'pend_recebimento' => $pendRecebimento,
            'clientes_ativos' => $clientesAtivos,
        ];
    }

    /** KPIs resumidos para o app coletor (escopo do usuário). */
    public static function kpisColetor(int $userId, bool $isAdmin): array
    {
        $db = new Database();
        $hoje = date('Y-m-d');
        $mesInicio = date('Y-m-01');
        $mesFim = date('Y-m-t');

        if ($isAdmin) {
            $coletorFilter = '';
            $paramsMes = [$mesInicio, $mesFim];
            $paramsRasc = [];
        } else {
            $coletorFilter = ' AND c.coletor_id = ?';
            $paramsMes = [$mesInicio, $mesFim, $userId];
            $paramsRasc = [$userId];
        }

        $coletasMes = EntityColeta::count(
            "c.status = 'finalizada' AND c.data_coleta BETWEEN ? AND ?".$coletorFilter,
            $paramsMes
        );

        $rascunhos = EntityColeta::count(
            "c.status = 'rascunho'".$coletorFilter,
            $paramsRasc
        );

        $urgentes = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes WHERE status = 'ativo' AND prioridade = 'urgente'"
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        $atrasados = (int)$db->execute(
            "SELECT COUNT(*) AS qtd FROM clientes
             WHERE status = 'ativo' AND proxima_coleta IS NOT NULL AND proxima_coleta <= ?",
            [$hoje]
        )->fetch(\PDO::FETCH_ASSOC)['qtd'];

        return [
            'coletas_mes' => $coletasMes,
            'rascunhos' => $rascunhos,
            'urgentes' => $urgentes,
            'atrasados' => $atrasados,
        ];
    }
}
