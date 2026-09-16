<?php

namespace App\Service;

use App\Common\CobrancaConfig;
use App\Common\Helpers\ContratoTemplateHelper;
use App\Common\Helpers\ContratoVariaveisBuilder;
use App\Common\OperadoraScope;
use App\Model\Entity\Cliente;
use App\Model\Entity\ClienteContrato;
use App\Model\Entity\Plano;
use App\Model\Db\Database;

class ContratoClienteService
{
    /** @param array<string,mixed> $dados */
    public static function criar(int $clienteId, int $criadoPorUsuarioId, array $dados): int
    {
        $cliente = Cliente::getById($clienteId);
        if (!$cliente) {
            return 0;
        }
        $planoId = (int)($dados['plano_id'] ?? 0);
        $plano = Plano::getById($planoId);
        if (!$plano) {
            return 0;
        }

        $qtdMeses = max(1, (int)($dados['qtd_meses'] ?? 12));
        $dataInicio = (string)($dados['data_inicio'] ?? date('Y-m-d'));
        $ts = strtotime($dataInicio);
        if ($ts === false) {
            $dataInicio = date('Y-m-d');
            $ts = time();
        }
        $dataFim = date('Y-m-d', strtotime('+'.$qtdMeses.' months', $ts));

        $multa = CobrancaConfig::multa();
        $mora = CobrancaConfig::mora();

        $id = ClienteContrato::insert([
            'operadora_id' => OperadoraScope::getOperadoraId(),
            'cliente_id' => $clienteId,
            'plano_id' => $planoId,
            'numero' => 'TMP-'.bin2hex(random_bytes(4)),
            'valor_mensal' => self::resolverValorMensal($dados, $plano),
            'qtd_meses' => $qtdMeses,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'dia_vencimento' => min(28, max(1, (int)($dados['dia_vencimento'] ?? 10))),
            'primeira_competencia' => (string)($dados['primeira_competencia'] ?? date('Y-m')),
            'multa_atraso_pct' => (float)($dados['multa_atraso_pct'] ?? $multa['taxa']),
            'juros_mora_pct_mes' => (float)($dados['juros_mora_pct_mes'] ?? $mora['taxa']),
            'multa_cancelamento_pct' => (float)($dados['multa_cancelamento_pct'] ?? 10),
            'carencia_dias' => (int)($dados['carencia_dias'] ?? 5),
            'status' => (string)($dados['status'] ?? 'rascunho'),
            'criado_por_usuario_id' => $criadoPorUsuarioId,
        ]);

        if ($id > 0) {
            $numero = 'WELL-CTR-'.date('Y').'-'.str_pad((string)$id, 5, '0', STR_PAD_LEFT);
            ClienteContrato::update($id, ['numero' => $numero]);
        }

        return $id;
    }

    public static function enviarParaAssinatura(int $contratoId): bool
    {
        $c = ClienteContrato::getById($contratoId);
        if (!$c || $c->status !== 'rascunho') {
            return false;
        }
        ClienteContrato::update($contratoId, ['status' => 'aguardando_assinatura']);

        return true;
    }

    public static function renderHtml(int $contratoId): string
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato) {
            return '';
        }
        if ($contrato->html_snapshot !== null && trim($contrato->html_snapshot) !== '') {
            return $contrato->html_snapshot;
        }
        $vars = ContratoVariaveisBuilder::montarFromContrato($contratoId);

        return ContratoTemplateHelper::render($vars, $contrato->operadora_id);
    }

    public static function registrarAssinaturaGerador(int $contratoId, int $clienteUsuarioId): bool
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato || $contrato->status !== 'aguardando_assinatura') {
            return false;
        }

        $html = self::renderHtml($contratoId);
        ClienteContrato::update($contratoId, [
            'status' => 'ativo',
            'html_snapshot' => $html,
            'assinado_em' => date('Y-m-d H:i:s'),
            'assinado_por_cliente_usuario_id' => $clienteUsuarioId,
        ]);

        $db = new Database();
        $db->execute(
            'UPDATE clientes SET plano_id = ?, contrato_ativo_id = ? WHERE id = ? AND operadora_id = ?',
            [$contrato->plano_id, $contratoId, $contrato->cliente_id, $contrato->operadora_id]
        );

        return true;
    }

    /** @param array<string,mixed> $dados */
    private static function resolverValorMensal(array $dados, Plano $plano): float
    {
        $raw = $dados['valor_mensal'] ?? null;
        if ($raw === null || $raw === '') {
            return (float)$plano->valor_mensal;
        }
        $valor = (float)str_replace(',', '.', (string)$raw);

        return $valor > 0 ? $valor : (float)$plano->valor_mensal;
    }
}
