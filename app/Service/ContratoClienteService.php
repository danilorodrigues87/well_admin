<?php

namespace App\Service;

use App\Common\CobrancaConfig;
use App\Common\Contrato\ContractType;
use App\Common\Contrato\ContratoModeloCatalog;
use App\Common\Helpers\ContratoTemplateHelper;
use App\Common\Helpers\ContratoVariaveisBuilder;
use App\Common\OperadoraScope;
use App\Model\Entity\ContratoModelo;
use App\Service\Contrato\ContratoComercialSnapshot;
use App\Service\Contrato\ContratoDocumentFactory;
use App\Service\Contrato\ContratoRenderService;
use App\Service\Contrato\ContratoTemplateService;
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

        $meta = ContratoModeloCatalog::meta($plano->contrato_modelo_tipo ?? ContractType::GENERICO);
        $qtdMeses = max(1, (int)($dados['qtd_meses'] ?? (int)($meta['default_meses'] ?? 12)));
        $dataInicio = (string)($dados['data_inicio'] ?? date('Y-m-d'));
        $ts = strtotime($dataInicio);
        if ($ts === false) {
            $dataInicio = date('Y-m-d');
            $ts = time();
        }
        $dataFim = date('Y-m-d', strtotime('+'.$qtdMeses.' months', $ts));

        $multa = CobrancaConfig::multa();
        $mora = CobrancaConfig::mora();

        $taxaRaw = $dados['taxa_adesao'] ?? null;
        $taxaAdesao = null;
        if ($taxaRaw !== null && $taxaRaw !== '') {
            $taxaAdesao = (float)str_replace(',', '.', (string)$taxaRaw);
        } elseif (!empty($meta['flags']['taxa_adesao_aplicavel'])) {
            $taxaAdesao = (float)($meta['taxa_adesao'] ?? 180);
        } else {
            $taxaAdesao = 0.0;
        }

        $insert = [
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
        ];
        $insertExtended = array_merge($insert, [
            'taxa_adesao' => $taxaAdesao,
            'indice_reajuste' => trim((string)($dados['indice_reajuste'] ?? $meta['indice_reajuste'] ?? 'IPCA')),
            'foro_cidade' => trim((string)($dados['foro_cidade'] ?? $meta['foro_cidade'] ?? 'Alta Floresta')),
            'foro_uf' => strtoupper(substr(trim((string)($dados['foro_uf'] ?? $meta['foro_uf'] ?? 'MT')), 0, 2)),
            'multa_atraso_descricao' => trim((string)($dados['multa_atraso_descricao'] ?? '3% ao dia sobre o valor em atraso')),
            'aviso_previo_dias' => max(0, (int)($dados['aviso_previo_dias'] ?? 30)),
        ]);

        $valorMensal = (float)$insert['valor_mensal'];
        $slug = ContractType::normalize($plano->contrato_modelo_tipo ?? ContractType::GENERICO);
        $modelo = $slug !== ContractType::GENERICO ? ContratoModelo::resolveAtivo(OperadoraScope::getOperadoraId(), $slug) : null;
        $snapshot = ContratoComercialSnapshot::fromPlano($planoId, $valorMensal, $taxaAdesao);

        $insertExtended['comercial_snapshot_json'] = ContratoComercialSnapshot::encode($snapshot);
        if ($modelo !== null) {
            $insertExtended['contrato_modelo_id'] = $modelo->id;
            $insertExtended['contrato_modelo_versao'] = $modelo->versao;
        }

        try {
            $id = ClienteContrato::insert($insertExtended);
        } catch (\Throwable) {
            unset($insertExtended['comercial_snapshot_json'], $insertExtended['contrato_modelo_id'], $insertExtended['contrato_modelo_versao']);
            try {
                $id = ClienteContrato::insert($insertExtended);
            } catch (\Throwable) {
                $id = ClienteContrato::insert($insert);
            }
        }

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
        $plano = Plano::getById($contrato->plano_id);
        $tipo = ContractType::normalize($plano?->contrato_modelo_tipo ?? ContractType::GENERICO);
        if ($tipo !== ContractType::GENERICO) {
            try {
                if (ContratoModelo::tabelaExiste() && ContratoModelo::resolveAtivo($contrato->operadora_id, $tipo) !== null) {
                    return ContratoTemplateService::renderFromContratoId($contratoId);
                }
                $doc = ContratoDocumentFactory::fromContratoId($contratoId);

                return ContratoRenderService::render($doc);
            } catch (\Throwable $e) {
                error_log('[ContratoClienteService] render dinâmico: '.$e->getMessage());
            }
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
        if ($contrato->comercial_snapshot_json === null || trim($contrato->comercial_snapshot_json) === '') {
            $plano = Plano::getById($contrato->plano_id);
            if ($plano) {
                $snap = ContratoComercialSnapshot::fromPlano(
                    $contrato->plano_id,
                    $contrato->valor_mensal,
                    $contrato->taxa_adesao
                );
                ClienteContrato::update($contratoId, [
                    'comercial_snapshot_json' => ContratoComercialSnapshot::encode($snap),
                ]);
            }
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

    public static function cancelar(int $contratoId, int $usuarioId, string $motivo): bool
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato || !in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            return false;
        }
        $motivo = trim($motivo);
        if ($motivo === '') {
            $motivo = 'Cancelado pelo administrador';
        }

        ClienteContrato::update($contratoId, [
            'status' => 'cancelado',
            'encerrado_em' => date('Y-m-d H:i:s'),
            'encerrado_motivo' => mb_substr($motivo, 0, 500),
            'encerrado_por_usuario_id' => $usuarioId,
        ]);

        return true;
    }

    public static function rescindir(int $contratoId, int $usuarioId, string $motivo): bool
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato || $contrato->status !== 'ativo') {
            return false;
        }
        $motivo = trim($motivo);
        if ($motivo === '') {
            $motivo = 'Rescisão registrada pelo administrador';
        }

        ClienteContrato::update($contratoId, [
            'status' => 'encerrado',
            'encerrado_em' => date('Y-m-d H:i:s'),
            'encerrado_motivo' => mb_substr($motivo, 0, 500),
            'encerrado_por_usuario_id' => $usuarioId,
        ]);

        $db = new Database();
        $db->execute(
            'UPDATE clientes SET contrato_ativo_id = NULL WHERE id = ? AND operadora_id = ? AND contrato_ativo_id = ?',
            [$contrato->cliente_id, $contrato->operadora_id, $contratoId]
        );

        return true;
    }

    /** @param array<string,mixed> $dados */
    public static function atualizarPersonalizacao(int $contratoId, array $dados): bool
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato || !in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            return false;
        }

        $vars = [];
        if ($contrato->variables_json) {
            $decoded = json_decode($contrato->variables_json, true);
            if (is_array($decoded)) {
                $vars = $decoded;
            }
        }

        $freq = trim((string)($dados['frequencia_coleta'] ?? ''));
        if ($freq !== '') {
            $vars['frequencia_coleta'] = $freq;
        }
        $promo = trim((string)($dados['promocao_html'] ?? ''));
        $vars['promocao_html'] = $promo;
        $extra = trim((string)($dados['clausulas_extra_html'] ?? ''));
        $vars['clausulas_extra_html'] = $extra;

        $update = [
            'variables_json' => json_encode($vars, JSON_UNESCAPED_UNICODE),
        ];

        $valorRaw = trim((string)($dados['valor_mensal'] ?? ''));
        if ($valorRaw !== '') {
            $update['valor_mensal'] = (float)str_replace(',', '.', $valorRaw);
        }
        if (array_key_exists('taxa_adesao', $dados)) {
            $taxaStr = trim((string)$dados['taxa_adesao']);
            if ($taxaStr !== '') {
                $update['taxa_adesao'] = (float)str_replace(',', '.', $taxaStr);
            }
        }
        foreach (['indice_reajuste', 'foro_cidade', 'foro_uf', 'data_inicio'] as $field) {
            if (!array_key_exists($field, $dados)) {
                continue;
            }
            $v = trim((string)$dados[$field]);
            if ($v === '') {
                continue;
            }
            $update[$field] = $v;
        }
        if (array_key_exists('qtd_meses', $dados) && (int)$dados['qtd_meses'] > 0) {
            $update['qtd_meses'] = (int)$dados['qtd_meses'];
        }
        if (array_key_exists('dia_vencimento', $dados) && (int)$dados['dia_vencimento'] > 0) {
            $update['dia_vencimento'] = (int)$dados['dia_vencimento'];
        }

        if (isset($update['valor_mensal']) || !empty($dados['atualizar_snapshot'])) {
            $plano = Plano::getById($contrato->plano_id);
            if ($plano) {
                $valor = (float)($update['valor_mensal'] ?? $contrato->valor_mensal);
                $taxa = array_key_exists('taxa_adesao', $update)
                    ? (float)$update['taxa_adesao']
                    : $contrato->taxa_adesao;
                $snap = ContratoComercialSnapshot::fromPlano($contrato->plano_id, $valor, $taxa);
                $update['comercial_snapshot_json'] = ContratoComercialSnapshot::encode($snap);
            }
        }

        $inicio = (string)($update['data_inicio'] ?? $contrato->data_inicio);
        $meses = (int)($update['qtd_meses'] ?? $contrato->qtd_meses);
        if ($inicio !== '' && $meses > 0) {
            $ts = strtotime($inicio);
            if ($ts !== false) {
                $update['data_fim'] = date('Y-m-d', strtotime('+'.$meses.' months', $ts));
            }
        }

        ClienteContrato::update($contratoId, $update);

        return true;
    }

    public static function recarregarSnapshotPlano(int $contratoId): bool
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato || !in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            return false;
        }
        $snap = ContratoComercialSnapshot::fromPlano(
            $contrato->plano_id,
            $contrato->valor_mensal,
            $contrato->taxa_adesao
        );
        ClienteContrato::update($contratoId, [
            'comercial_snapshot_json' => ContratoComercialSnapshot::encode($snap),
        ]);

        return true;
    }
}
