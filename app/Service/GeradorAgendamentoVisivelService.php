<?php

namespace App\Service;

use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\ColetaSolicitacao;
use App\Model\Db\Database;

class GeradorAgendamentoVisivelService
{
    /** @return array<string, mixed>|null */
    public static function resumo(int $clienteId): ?array
    {
        $cliente = EntityCliente::getById($clienteId);
        if (!$cliente) {
            return null;
        }

        $hoje = date('Y-m-d');
        $proxima = trim((string)($cliente->proxima_coleta ?? ''));
        if ($proxima !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $proxima)) {
            return [
                'data' => $proxima,
                'situacao' => $proxima < $hoje ? 'atrasada' : ($proxima === $hoje ? 'hoje' : 'futura'),
                'origem' => 'operacao',
                'mensagem' => 'Coleta prevista pela equipe Well.',
            ];
        }

        $db = new Database();
        $pend = $db->execute(
            "SELECT data_desejada FROM coleta_solicitacoes
             WHERE cliente_id = ? AND status = 'pendente'
             ORDER BY created_at DESC LIMIT 1",
            [$clienteId]
        )->fetch(\PDO::FETCH_ASSOC);
        if ($pend) {
            return [
                'data' => (string)$pend['data_desejada'],
                'situacao' => 'pendente_aprovacao',
                'origem' => 'solicitacao',
                'mensagem' => 'Solicitação enviada — aguardando confirmação da equipe.',
            ];
        }

        $aprov = $db->execute(
            "SELECT COALESCE(data_aprovada, data_desejada) AS dt FROM coleta_solicitacoes
             WHERE cliente_id = ? AND status = 'aprovada' AND COALESCE(data_aprovada, data_desejada) >= ?
             ORDER BY aprovado_em DESC LIMIT 1",
            [$clienteId, $hoje]
        )->fetch(\PDO::FETCH_ASSOC);
        if ($aprov) {
            return [
                'data' => (string)$aprov['dt'],
                'situacao' => 'aprovada',
                'origem' => 'solicitacao',
                'mensagem' => 'Solicitação aprovada.',
            ];
        }

        return null;
    }
}
