<?php

namespace App\Common\Helpers;

use App\Common\CompanyConfig;
use App\Model\Entity\Cliente;
use App\Model\Entity\ClienteContrato;
use App\Model\Entity\Operadora;
use App\Model\Entity\Plano;

class ContratoVariaveisBuilder
{
    public static function montarFromContrato(int $contratoId): array
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato) {
            return [];
        }
        $cliente = Cliente::getById($contrato->cliente_id);
        $plano = Plano::getById($contrato->plano_id);
        $operadora = Operadora::getById($contrato->operadora_id);

        $clausula1 = trim((string)($plano->contrato_clausula_1 ?? ''));
        $clausula2 = trim((string)($plano->contrato_clausula_2 ?? ''));
        $clausula3 = trim((string)($plano->contrato_clausula_3 ?? ''));
        $extra = trim((string)($plano->contrato_clausula_extra ?? ''));
        if ($clausula1 === '') {
            $clausula1 = '<p><b>1ª — Serviços.</b> A CONTRATADA prestará serviços de coleta, transporte e destinação de resíduos conforme plano {{coletas_mensais}} coletas mensais.</p>';
        }
        if ($clausula2 === '') {
            $clausula2 = '<p><b>2ª — MTR.</b> Será emitido MTR-SINIR para cada coleta realizada.</p>';
        }
        if ($clausula3 === '') {
            $clausula3 = '<p><b>3ª — Vigência.</b> Contrato válido por {{qtd_meses}} meses a partir da assinatura.</p>';
        }

        $tokens = [
            '{{coletas_mensais}}' => number_format((float)($plano->coletas_mensais ?? 0), 0, ',', '.'),
            '{{valor_mensal}}' => FormatHelper::money($contrato->valor_mensal),
            '{{qtd_meses}}' => (string)$contrato->qtd_meses,
            '{{dia_vencimento}}' => (string)$contrato->dia_vencimento,
            '{{multa_atraso_pct}}' => number_format($contrato->multa_atraso_pct, 2, ',', '.'),
            '{{juros_mora_pct_mes}}' => number_format($contrato->juros_mora_pct_mes, 2, ',', '.'),
        ];
        foreach ($tokens as $token => $val) {
            $clausula1 = str_replace($token, $val, $clausula1);
            $clausula2 = str_replace($token, $val, $clausula2);
            $clausula3 = str_replace($token, $val, $clausula3);
            $extra = str_replace($token, $val, $extra);
        }

        $cidadeUf = trim(($cliente->cidade ?? '').'/'.($cliente->uf ?? ''), '/');
        $dataContrato = $cidadeUf !== ''
            ? $cidadeUf.', '.FormatHelper::dateBr($contrato->data_inicio)
            : FormatHelper::dateBr($contrato->data_inicio);

        return [
            'URL' => rtrim((string)URL, '/'),
            'contratada' => self::blocoContratada($operadora),
            'contratante' => self::blocoContratante($cliente),
            'plano' => self::blocoPlano($contrato, $plano),
            'clausula_1' => $clausula1,
            'clausula_2' => $clausula2,
            'clausula_3' => $clausula3,
            'clausula_extra' => $extra,
            'data_contrato' => '<p class="text-end"><em>'.$dataContrato.'</em></p>',
            'dia_vencimento' => (string)$contrato->dia_vencimento,
            'multa_atraso_pct' => number_format($contrato->multa_atraso_pct, 2, ',', '.'),
            'juros_mora_pct_mes' => number_format($contrato->juros_mora_pct_mes, 2, ',', '.'),
            'multa_cancelamento_pct' => number_format($contrato->multa_cancelamento_pct, 2, ',', '.'),
            'carencia_dias' => (string)$contrato->carencia_dias,
            'qtd_meses' => (string)$contrato->qtd_meses,
        ];
    }

    private static function blocoContratada(?Operadora $op): string
    {
        $nome = htmlspecialchars(CompanyConfig::name($op->id ?? 1), ENT_QUOTES, 'UTF-8');
        $cnpj = htmlspecialchars((string)($op->cnpj ?? ''), ENT_QUOTES, 'UTF-8');

        return '<p><strong>CONTRATADA:</strong> '.$nome
            .($cnpj !== '' ? ', CNPJ '.$cnpj : '')
            .', doravante denominada <strong>CONTRATADA</strong>.</p>';
    }

    private static function blocoContratante(?Cliente $c): string
    {
        if (!$c) {
            return '';
        }
        $nome = htmlspecialchars($c->razao_social ?: $c->nome_fantasia, ENT_QUOTES, 'UTF-8');
        $cnpj = htmlspecialchars($c->cnpj, ENT_QUOTES, 'UTF-8');
        $resp = htmlspecialchars($c->responsavel, ENT_QUOTES, 'UTF-8');
        $end = htmlspecialchars(Cliente::enderecoCompleto($c), ENT_QUOTES, 'UTF-8');

        return '<p><strong>CONTRATANTE/GERADOR:</strong> '.$nome
            .', CNPJ '.$cnpj
            .($resp !== '' ? ', representada por '.$resp : '')
            .', com endereço em '.$end
            .', doravante denominado <strong>CONTRATANTE</strong>.</p>';
    }

    private static function blocoPlano(ClienteContrato $contrato, ?Plano $plano): string
    {
        $nomePlano = htmlspecialchars($plano->nome ?? 'Plano', ENT_QUOTES, 'UTF-8');

        return '<p><strong>Plano contratado:</strong> '.$nomePlano
            .'<br><strong>Valor mensal:</strong> '.FormatHelper::money($contrato->valor_mensal)
            .'<br><strong>Vigência:</strong> '.FormatHelper::dateBr($contrato->data_inicio)
            .' a '.FormatHelper::dateBr($contrato->data_fim)
            .' ('.$contrato->qtd_meses.' meses)'
            .'<br><strong>Vencimento:</strong> dia '.$contrato->dia_vencimento.' de cada mês'
            .'</p>';
    }
}
