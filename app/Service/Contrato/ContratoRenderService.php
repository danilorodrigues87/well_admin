<?php

namespace App\Service\Contrato;

use App\Common\Helpers\ContratoBrandingHelper;
use App\Common\Helpers\FormatHelper;

class ContratoRenderService
{
    /** @param array<string,mixed> $doc */
    public static function render(array $doc): string
    {
        $opId = (int)($doc['meta']['operadora_id'] ?? $doc['contratada']['operadora_id'] ?? 1);
        $vars = [
            'URL' => rtrim((string)URL, '/'),
            'logo_html' => ContratoBrandingHelper::logoHtml($opId),
            'titulo' => 'Contrato de Prestação de Serviços de Coleta, Transporte e Destinação Final de Resíduos',
            'qualificacao_contratada' => self::qualificacaoContratada($doc),
            'qualificacao_contratante' => self::qualificacaoContratante($doc),
            'clausula_objeto' => self::clausulaObjeto($doc),
            'clausula_legislacao' => self::clausulaLegislacao($doc),
            'clausula_execucao' => self::clausulaExecucao($doc),
            'clausula_preco' => self::clausulaPreco($doc),
            'clausula_pagamento' => self::clausulaPagamento($doc),
            'clausula_vigencia' => self::clausulaVigencia($doc),
            'clausula_obrigacoes' => self::clausulaObrigacoes($doc),
            'blocos_compliance' => self::blocosCompliance($doc),
            'clausula_foro' => self::clausulaForo($doc),
            'data_assinatura' => '<p class="text-end"><em>'.htmlspecialchars((string)($doc['juridico']['data_assinatura'] ?? ''), ENT_QUOTES, 'UTF-8').'</em></p>',
            'clausulas_extra' => (string)($doc['clausulas_extra_html'] ?? ''),
        ];

        $path = dirname(__DIR__, 3).'/resources/view/contratos/document.html';
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException('Template de contrato não encontrado.');
        }

        foreach ($vars as $key => $val) {
            $html = str_replace('{{'.$key.'}}', (string)$val, $html);
        }

        return $html;
    }

    /** @param array<string,mixed> $doc */
    private static function qualificacaoContratada(array $doc): string
    {
        $c = $doc['contratada'] ?? [];
        $nome = htmlspecialchars((string)($c['razao_social'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cnpj = htmlspecialchars((string)($c['cnpj'] ?? ''), ENT_QUOTES, 'UTF-8');
        $end = htmlspecialchars((string)($c['endereco'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<p><strong>CONTRATADA:</strong> '.$nome.', CNPJ nº '.$cnpj
            .', com sede em '.$end.', doravante denominada <strong>CONTRATADA</strong>.</p>';
    }

    /** @param array<string,mixed> $doc */
    private static function qualificacaoContratante(array $doc): string
    {
        $c = $doc['contratante'] ?? [];
        $nome = htmlspecialchars((string)($c['razao_social'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cnpj = htmlspecialchars((string)($c['cnpj'] ?? ''), ENT_QUOTES, 'UTF-8');
        $end = htmlspecialchars((string)($c['endereco'] ?? ''), ENT_QUOTES, 'UTF-8');
        $rep = htmlspecialchars((string)($c['representante_nome'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cargo = htmlspecialchars((string)($c['representante_cargo'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cpf = htmlspecialchars((string)($c['cpf'] ?? ''), ENT_QUOTES, 'UTF-8');
        $rg = htmlspecialchars((string)($c['rg'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tel = htmlspecialchars((string)($c['telefone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string)($c['email'] ?? ''), ENT_QUOTES, 'UTF-8');

        $extra = '';
        if ($rep !== '') {
            $extra = ', representada por '.$rep;
            if ($cargo !== '') {
                $extra .= ', '.$cargo;
            }
            if ($cpf !== '') {
                $extra .= ', CPF nº '.$cpf;
            }
            if ($rg !== '') {
                $extra .= ', RG nº '.$rg;
            }
        }
        $contato = [];
        if ($tel !== '') {
            $contato[] = 'Telefone: '.$tel;
        }
        if ($email !== '') {
            $contato[] = 'E-mail: '.$email;
        }
        if ($contato !== []) {
            $extra .= ', '.implode(', ', $contato);
        }

        return '<p><strong>CONTRATANTE/GERADOR:</strong> '.$nome.', CNPJ nº '.$cnpj
            .', com sede em '.$end.$extra
            .', doravante denominado <strong>CONTRATANTE</strong>.</p>';
    }

    /** @param array<string,mixed> $doc */
    private static function clausulaObjeto(array $doc): string
    {
        $escopo = htmlspecialchars((string)($doc['juridico']['escopo_residuos'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<p><strong>CLÁUSULA PRIMEIRA – DO OBJETO</strong></p>'
            .'<p>Prestação de serviços de coleta, transporte, armazenamento temporário e destinação final ambientalmente adequada de '
            .$escopo.' conforme normas técnicas e legislação vigente. A CONTRATADA declara possuir licenças ambientais e autorizações exigidas.</p>';
    }

    /** @param array<string,mixed> $doc */
    private static function clausulaLegislacao(array $doc): string
    {
        $esp = htmlspecialchars((string)($doc['juridico']['legislacao_especifica'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<p><strong>CLÁUSULA SEGUNDA – DA LEGISLAÇÃO PERTINENTE</strong></p>'
            .'<p>Este contrato observa a Lei Federal nº 12.305/2010 (PNRS), a Lei nº 9.605/1998 e '.$esp.'.</p>';
    }

    /** @param array<string,mixed> $doc */
    private static function clausulaExecucao(array $doc): string
    {
        $freq = htmlspecialchars((string)($doc['juridico']['frequencia_coleta'] ?? ''), ENT_QUOTES, 'UTF-8');
        $rt = !empty($doc['flags']['exige_responsavel_tecnico'])
            ? '<p>A CONTRATANTE designará Responsável Técnico pelo gerenciamento interno dos resíduos, conforme exigências aplicáveis.</p>'
            : '';

        return '<p><strong>CLÁUSULA TERCEIRA – DA FORMA DE EXECUÇÃO</strong></p>'
            .'<p>A coleta será realizada no endereço da CONTRATANTE com frequência '.$freq.'.</p>'
            .'<ul><li>Não haverá coleta em domingos e feriados, salvo emergência sob cobrança extra.</li>'
            .'<li>A aferição de pesagem/volume poderá ser realizada na presença de representante da CONTRATANTE.</li></ul>'
            .$rt;
    }

    /** @param array<string,mixed> $doc */
    private static function clausulaPreco(array $doc): string
    {
        $pricing = $doc['pricing'] ?? [];
        $model = (string)($pricing['model'] ?? 'FRANQUIA_EXCEDENTE_KG');
        $indice = htmlspecialchars((string)($pricing['indice_reajuste'] ?? 'IPCA/IBGE'), ENT_QUOTES, 'UTF-8');
        $detalhe = match ($model) {
            'TABELA_TONELADA_CACAMBA' => self::htmlPrecoRcc($pricing),
            'VALOR_GLOBAL_OBRA' => self::htmlPrecoObra($pricing, $doc),
            default => self::htmlPrecoFranquia($pricing),
        };

        return '<p><strong>CLÁUSULA QUARTA – DO PREÇO E REAJUSTE</strong></p>'
            .$detalhe
            .'<p>Os valores serão reajustados anualmente pelo índice '.$indice.' ou por readequação de equilíbrio econômico-financeiro.</p>';
    }

    /** @param array<string,mixed> $pricing */
    private static function htmlPrecoFranquia(array $pricing): string
    {
        $valor = FormatHelper::money((float)($pricing['valor_mensal'] ?? 0));
        $html = '<p>Mensalidade: <strong>'.$valor.'</strong>, conforme franquia e excedentes abaixo:</p>';
        $linhas = $pricing['linhas_franquia'] ?? [];
        if ($linhas === []) {
            return $html.'<p>Valores conforme tabela comercial acordada entre as partes.</p>';
        }
        $html .= '<table class="table table-sm table-bordered"><thead><tr><th>Resíduo</th><th>Franquia</th><th>Excedente</th></tr></thead><tbody>';
        foreach ($linhas as $l) {
            $nome = htmlspecialchars((string)($l['rotulo'] ?? ''), ENT_QUOTES, 'UTF-8');
            $saldo = number_format((float)($l['saldo_incluso'] ?? 0), 2, ',', '.');
            $un = htmlspecialchars((string)($l['unidade'] ?? 'kg'), ENT_QUOTES, 'UTF-8');
            $exc = FormatHelper::money((float)($l['valor_excedente'] ?? 0)).' / '.$un;
            $pool = !empty($l['saldo_compartilhado']) ? ' <small>(saldo compartilhado)</small>' : '';
            $html .= '<tr><td>'.$nome.$pool.'</td><td>'.$saldo.' '.$un.'</td><td>'.$exc.'</td></tr>';
        }

        return $html.'</tbody></table>';
    }

    /** @param array<string,mixed> $pricing */
    private static function htmlPrecoRcc(array $pricing): string
    {
        $html = '<p>Medição por peso/tipo de RCC e locação de caçambas conforme tabela:</p>';
        $tons = $pricing['linhas_tonelada'] ?? [];
        if ($tons !== []) {
            $html .= '<table class="table table-sm table-bordered"><thead><tr><th>Tipo</th><th>Preço (R$/ton)</th></tr></thead><tbody>';
            foreach ($tons as $t) {
                $html .= '<tr><td>'.htmlspecialchars((string)($t['rotulo'] ?? ''), ENT_QUOTES, 'UTF-8').'</td><td>'
                    .FormatHelper::money((float)($t['preco_por_ton'] ?? 0)).'</td></tr>';
            }
            $html .= '</tbody></table>';
        } else {
            foreach ($pricing['linhas_franquia'] ?? [] as $l) {
                $html .= '<p>'.htmlspecialchars((string)($l['rotulo'] ?? ''), ENT_QUOTES, 'UTF-8').': '
                    .FormatHelper::money((float)($l['valor_excedente'] ?? 0)).' / '
                    .htmlspecialchars((string)($l['unidade'] ?? 'kg'), ENT_QUOTES, 'UTF-8').'</p>';
            }
        }
        $html .= '<p>Metais recicláveis poderão gerar crédito dedutível da medição, conforme plano.</p>';

        return $html;
    }

    /** @param array<string,mixed> $pricing
     * @param array<string,mixed> $doc
     */
    private static function htmlPrecoObra(array $pricing, array $doc): string
    {
        $valor = FormatHelper::money((float)($pricing['valor_mensal'] ?? 0));
        $ret = !empty($doc['flags']['retencao_garantia_medicao'])
            ? '<p>Retenção de garantia de '.htmlspecialchars((string)($doc['flags']['retencao_garantia_pct'] ?? '5'), ENT_QUOTES, 'UTF-8')
                .'% sobre cada medição mensal, restituível ao término do contrato.</p>'
            : '';

        return '<p>Valor global de referência: <strong>'.$valor.'</strong>, pago mediante medições mensais de volume/tonelada '
            .'e eventuais retiradas extras de caçamba conforme acordado.</p>'.$ret;
    }

    /** @param array<string,mixed> $doc */
    private static function clausulaPagamento(array $doc): string
    {
        $pag = $doc['pagamento'] ?? [];
        $dia = (int)($pag['dia_vencimento'] ?? 10);
        $multa = htmlspecialchars((string)($pag['multa_atraso_descricao'] ?? ''), ENT_QUOTES, 'UTF-8');
        $taxa = (float)($doc['pricing']['taxa_adesao'] ?? 0);
        $taxaLinha = !empty($doc['flags']['taxa_adesao_aplicavel']) && $taxa > 0
            ? '<li>Taxa de adesão (cadastro/logística): '.FormatHelper::money($taxa).'.</li>'
            : '';
        $antecipado = !empty($pag['primeiro_mes_antecipado'])
            ? '<li>Pagamento do primeiro mês realizado antecipadamente.</li>'
            : '';
        $extraPlano = trim((string)($doc['pagamento_plano_html'] ?? ''));

        return '<p><strong>CLÁUSULA QUINTA – DA FORMA DE PAGAMENTO</strong></p><ul>'
            .'<li>Pagamento até o '.$dia.'º dia útil de cada mês via boleto bancário.</li>'
            .$antecipado
            .$taxaLinha
            .'<li>Multa por atraso: '.$multa.'.</li>'
            .'</ul>'
            .($extraPlano !== '' ? '<div class="contrato-extra-pagamento">'.$extraPlano.'</div>' : '');
    }

    /** @param array<string,mixed> $doc */
    private static function clausulaVigencia(array $doc): string
    {
        $v = $doc['vigencia'] ?? [];
        $ren = !empty($doc['flags']['renovacao_automatica'])
            ? '<li>Renovação automática por igual período, salvo manifestação contrária.</li>'
            : '';

        return '<p><strong>CLÁUSULA SEXTA – DA VIGÊNCIA E RESCISÃO</strong></p><ul>'
            .'<li>Vigência de '.(int)($v['qtd_meses'] ?? 12).' meses, de '.htmlspecialchars((string)($v['data_inicio_br'] ?? ''), ENT_QUOTES, 'UTF-8')
            .' até '.htmlspecialchars((string)($v['data_fim_br'] ?? ''), ENT_QUOTES, 'UTF-8').'.</li>'
            .$ren
            .'<li>Aviso prévio de rescisão sem ônus: '.(int)($v['aviso_previo_dias'] ?? 30).' dias.</li>'
            .'<li>Multa por rescisão imotivada: '.htmlspecialchars((string)($v['multa_rescisao'] ?? ''), ENT_QUOTES, 'UTF-8').'.</li>'
            .'</ul>';
    }

    /** @param array<string,mixed> $doc */
    private static function clausulaObrigacoes(array $doc): string
    {
        return '<p><strong>CLÁUSULA SÉTIMA – OBRIGAÇÕES DA CONTRATADA</strong></p>'
            .'<p>Veículos licenciados, equipe capacitada e uniformizada, EPIs, emissão de MTR/CDF e cumprimento do SINIR.</p>'
            .'<p><strong>CLÁUSULA OITAVA – OBRIGAÇÕES DO CONTRATANTE</strong></p>'
            .'<p>Acondicionamento e segregação corretos, lançamentos no SINIR, pontualidade no pagamento. '
            .'Infração grave por resíduos estranhos ou não segregados: multa de 5% sobre o valor estimado do contrato.</p>';
    }

    /** @param array<string,mixed> $doc */
    private static function blocosCompliance(array $doc): string
    {
        $html = '';
        if (!empty($doc['flags']['exige_docs_trabalhistas_mensais'])) {
            $html .= '<p><strong>Fiscalização trabalhista</strong></p>'
                .'<p>Pagamento condicionado à entrega mensal de rol de funcionários, folha, FGTS, GPS, SEFIP, '
                .'cartões de ponto e exames demissionais quando aplicável.</p>';
        }
        if (!empty($doc['flags']['clausula_anticorrupcao'])) {
            $html .= '<p><strong>Anticorrupção</strong></p>'
                .'<p>As partes comprometem-se com a Lei nº 12.846/2013 (Lei Anticorrupção).</p>';
        }
        if (!empty($doc['flags']['clausula_lgpd'])) {
            $html .= '<p><strong>LGPD</strong></p>'
                .'<p>Tratamento de dados pessoais conforme a Lei nº 13.709/2018 e política de privacidade da CONTRATADA.</p>';
        }

        return $html;
    }

    /** @param array<string,mixed> $doc */
    private static function clausulaForo(array $doc): string
    {
        $j = $doc['juridico'] ?? [];
        $cidade = htmlspecialchars((string)($j['foro_cidade'] ?? 'Alta Floresta'), ENT_QUOTES, 'UTF-8');
        $uf = htmlspecialchars((string)($j['foro_uf'] ?? 'MT'), ENT_QUOTES, 'UTF-8');

        return '<p><strong>CLÁUSULA DÉCIMA QUINTA – DO FORO</strong></p>'
            .'<p>Fica eleito o Foro da Comarca de '.$cidade.' - '.$uf.', com renúncia a qualquer outro.</p>';
    }
}
