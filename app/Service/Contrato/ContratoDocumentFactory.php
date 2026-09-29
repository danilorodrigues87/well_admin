<?php

namespace App\Service\Contrato;

use App\Common\CompanyConfig;
use App\Common\Contrato\ContractType;
use App\Common\Contrato\ContratoModeloCatalog;
use App\Common\Helpers\FormatHelper;
use App\Model\Entity\Cliente;
use App\Model\Entity\ClienteContrato;
use App\Model\Entity\Operadora;
use App\Model\Entity\Plano;
use App\Model\Entity\PlanoItem;

class ContratoDocumentFactory
{
    /** @return array<string,mixed> */
    public static function fromContratoId(int $contratoId): array
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato) {
            throw new \InvalidArgumentException('Contrato não encontrado.');
        }
        $cliente = Cliente::getById($contrato->cliente_id);
        $plano = Plano::getById($contrato->plano_id);
        if (!$plano) {
            throw new \InvalidArgumentException('Plano não encontrado.');
        }

        $contractType = ContractType::normalize($plano->contrato_modelo_tipo ?? ContractType::GENERICO);
        $meta = ContratoModeloCatalog::meta($contractType);
        $itens = PlanoItem::getByPlanoId($plano->id);
        $snapshot = \App\Service\Contrato\ContratoComercialSnapshot::decode($contrato->comercial_snapshot_json);
        if ($snapshot !== null && !empty($snapshot['itens'])) {
            $itens = \App\Service\Contrato\ContratoComercialSnapshot::itensAsPlanoItems($snapshot);
        }

        $operadora = Operadora::getById($contrato->operadora_id);
        $contratadaEndereco = 'Av. Industrial, Quadra 09, Lote 15, Distrito Industrial, Alta Floresta - MT';

        $taxaAdesao = $contrato->taxa_adesao;
        if ($taxaAdesao === null) {
            $taxaAdesao = (float)($meta['taxa_adesao'] ?? 180);
        }
        if (empty($meta['flags']['taxa_adesao_aplicavel'])) {
            $taxaAdesao = 0.0;
        }

        $indice = trim($contrato->indice_reajuste ?? '') !== ''
            ? $contrato->indice_reajuste
            : (string)($meta['indice_reajuste'] ?? 'IPCA/IBGE');

        $foroCidade = trim($contrato->foro_cidade ?? '') !== '' ? $contrato->foro_cidade : (string)$meta['foro_cidade'];
        $foroUf = trim($contrato->foro_uf ?? '') !== '' ? $contrato->foro_uf : (string)$meta['foro_uf'];

        $flags = is_array($meta['flags'] ?? null) ? $meta['flags'] : [];
        if ($contrato->flags_json !== null && $contrato->flags_json !== '') {
            $decoded = json_decode($contrato->flags_json, true);
            if (is_array($decoded)) {
                $flags = array_merge($flags, $decoded);
            }
        }

        $frequencia = self::frequenciaColetaTexto($plano);

        $cidadeUf = trim(($cliente->cidade ?? '').'/'.($cliente->uf ?? ''), '/');
        $dataAssinatura = $cidadeUf !== ''
            ? $cidadeUf.', '.FormatHelper::dateBr($contrato->data_inicio)
            : FormatHelper::dateBr($contrato->data_inicio);

        $multaRescisao = trim((string)($contrato->multa_rescisao_texto ?? ''));
        if ($multaRescisao === '') {
            $multaRescisao = number_format($contrato->multa_cancelamento_pct, 2, ',', '.')
                .'% sobre o saldo remanescente do contrato';
        }

        return [
            'meta' => [
                'contrato_id' => $contrato->id,
                'numero' => $contrato->numero,
                'operadora_id' => $contrato->operadora_id,
                'contract_type' => $contractType,
                'plano_nome' => $plano->nome,
            ],
            'contratada' => [
                'razao_social' => CompanyConfig::name($contrato->operadora_id),
                'cnpj' => trim((string)($operadora->cnpj ?? '18.675.233/0001-50')),
                'endereco' => $contratadaEndereco,
            ],
            'contratante' => [
                'razao_social' => $cliente->razao_social ?: $cliente->nome_fantasia,
                'cnpj' => $cliente->cnpj,
                'endereco' => Cliente::enderecoCompleto($cliente),
                'representante_nome' => $cliente->responsavel,
                'representante_cargo' => $cliente->responsavel_cargo ?? '',
                'cpf' => $cliente->responsavel_cpf ?? '',
                'rg' => $cliente->responsavel_rg ?? '',
                'telefone' => $cliente->telefone ?: ($cliente->telefone_resp ?? ''),
                'email' => $cliente->email,
            ],
            'pricing' => [
                'model' => (string)($meta['pricing_model'] ?? 'FRANQUIA_EXCEDENTE_KG'),
                'valor_mensal' => $contrato->valor_mensal,
                'taxa_adesao' => $taxaAdesao,
                'indice_reajuste' => $indice,
                'linhas_franquia' => self::linhasFranquia($itens),
                'linhas_tonelada' => self::linhasTonelada($itens, $contractType),
            ],
            'vigencia' => [
                'data_inicio' => $contrato->data_inicio,
                'data_fim' => $contrato->data_fim,
                'qtd_meses' => $contrato->qtd_meses,
                'data_inicio_br' => FormatHelper::dateBr($contrato->data_inicio),
                'data_fim_br' => FormatHelper::dateBr($contrato->data_fim),
                'aviso_previo_dias' => $contrato->aviso_previo_dias ?? 30,
                'multa_rescisao' => $multaRescisao,
            ],
            'pagamento' => [
                'dia_vencimento' => $contrato->dia_vencimento,
                'multa_atraso_descricao' => $contrato->multa_atraso_descricao ?? '3% ao dia sobre o valor em atraso',
                'primeiro_mes_antecipado' => !empty($flags['primeiro_mes_antecipado']),
            ],
            'flags' => $flags,
            'juridico' => [
                'escopo_residuos' => (string)($meta['escopo_residuos'] ?? ''),
                'legislacao_especifica' => (string)($meta['legislacao_especifica'] ?? ''),
                'frequencia_coleta' => $frequencia,
                'foro_cidade' => $foroCidade,
                'foro_uf' => $foroUf,
                'data_assinatura' => $dataAssinatura,
            ],
            'clausulas_extra_html' => trim((string)($plano->contrato_clausula_extra ?? '')),
            'pagamento_plano_html' => self::pagamentoPlanoHtml($plano),
        ];
    }

    private static function frequenciaColetaTexto(Plano $plano): string
    {
        $periodo = $plano->coletas_periodo_meses;
        if ($periodo !== null && $periodo > 1) {
            return 'até '.$plano->coletas_por_periodo.' coleta(s) a cada '.$periodo.' meses';
        }
        $cm = (float)$plano->coletas_mensais;
        if ($cm <= 0) {
            return 'conforme demanda acordada entre as partes';
        }
        if ($cm === 1.0) {
            return 'mensal (1 coleta por mês)';
        }

        return 'até '.number_format($cm, 0, ',', '.').' coletas por mês';
    }

    /** @param list<PlanoItem> $itens
     * @return list<array<string,mixed>>
     */
    private static function linhasFranquia(array $itens): array
    {
        $out = [];
        foreach ($itens as $item) {
            if ($item->gera_credito) {
                continue;
            }
            $out[] = [
                'rotulo' => $item->tipo_nome,
                'cod_ibama' => $item->tipo_cod_ibama,
                'saldo_incluso' => $item->saldo_incluso,
                'unidade' => $item->unidade ?: 'kg',
                'valor_excedente' => $item->valor_excedente,
                'saldo_compartilhado' => $item->saldo_compartilhado,
            ];
        }

        return $out;
    }

    /** @param list<PlanoItem> $itens
     * @return list<array<string,mixed>>
     */
    private static function linhasTonelada(array $itens, string $contractType): array
    {
        if ($contractType !== ContractType::RCC_PADRAO) {
            return [];
        }
        $out = [];
        foreach ($itens as $item) {
            $unit = strtolower($item->unidade ?: 'kg');
            if ($unit === 'ton' || $unit === 't') {
                $out[] = [
                    'rotulo' => $item->tipo_nome,
                    'preco_por_ton' => $item->valor_excedente,
                    'gera_credito' => $item->gera_credito,
                ];
            }
        }

        return $out;
    }

    private static function pagamentoPlanoHtml(Plano $plano): string
    {
        $parts = array_filter([
            trim((string)($plano->contrato_pagamento_parcelado ?? '')),
            trim((string)($plano->contrato_pagamento_vista ?? '')),
            trim((string)($plano->contrato_obs_pontualidade ?? '')),
        ]);

        return implode("\n", $parts);
    }
}
