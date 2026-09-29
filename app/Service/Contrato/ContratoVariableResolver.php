<?php

namespace App\Service\Contrato;

use App\Common\CompanyConfig;
use App\Common\Helpers\ContratoBrandingHelper;
use App\Common\Contrato\ContratoModeloCatalog;
use App\Common\Helpers\FormatHelper;
use App\Model\Entity\Cliente;
use App\Model\Entity\ClienteContrato;
use App\Model\Entity\ContratoModelo;
use App\Model\Entity\Operadora;
use App\Model\Entity\OperadoraConfig;
use App\Model\Entity\Plano;
use App\Model\Db\Database;

final class ContratoVariableResolver
{
    /** @return array<string,string> */
    public static function forContrato(ClienteContrato $contrato, ContratoModelo $modelo): array
    {
        $cliente = Cliente::getById($contrato->cliente_id);
        $plano = Plano::getById($contrato->plano_id);
        if (!$cliente || !$plano) {
            throw new \InvalidArgumentException('Cliente ou plano inválido.');
        }

        $meta = ContratoModeloCatalog::meta($plano->contrato_modelo_tipo ?? $modelo->slug);
        $flags = is_array($meta['flags'] ?? null) ? $meta['flags'] : [];
        if ($contrato->flags_json) {
            $decoded = json_decode($contrato->flags_json, true);
            if (is_array($decoded)) {
                $flags = array_merge($flags, $decoded);
            }
        }
        if ($modelo->flags_json) {
            $decoded = json_decode($modelo->flags_json, true);
            if (is_array($decoded)) {
                $flags = array_merge($flags, $decoded);
            }
        }

        $varsOverride = [];
        if ($contrato->variables_json) {
            $decoded = json_decode($contrato->variables_json, true);
            if (is_array($decoded)) {
                $varsOverride = $decoded;
            }
        }

        $snapshot = ContratoComercialSnapshot::decode($contrato->comercial_snapshot_json);
        if ($snapshot === null) {
            $taxa = $contrato->taxa_adesao;
            $snapshot = ContratoComercialSnapshot::fromPlano(
                $contrato->plano_id,
                $contrato->valor_mensal,
                $taxa
            );
        }

        $operadora = Operadora::getById($contrato->operadora_id);
        $enderecoSede = self::configOperadora($contrato->operadora_id, 'contrato_endereco_sede')
            ?? 'Avenida Industrial, Quadra nº 09, Lote nº 15, Distrito Industrial, Alta Floresta - MT';

        $contratadaNome = CompanyConfig::name($contrato->operadora_id);
        $contratadaCnpj = trim((string)($operadora->cnpj ?? '18.675.233/0001-50'));

        $rep = trim($cliente->responsavel);
        $cargo = trim($cliente->responsavel_cargo);
        $cpf = trim($cliente->responsavel_cpf);
        $rg = trim($cliente->responsavel_rg);
        $tel = trim($cliente->telefone ?: $cliente->telefone_resp);
        $email = trim($cliente->email);
        $contratanteNome = $cliente->razao_social ?: $cliente->nome_fantasia;

        $qualificacao = self::qualificacaoPreambulo(
            $contratadaNome,
            $contratadaCnpj,
            $enderecoSede,
            $contratanteNome,
            $cliente->cnpj,
            Cliente::enderecoCompleto($cliente),
            $rep,
            $cargo,
            $cpf,
            $rg,
            $tel,
            $email
        );

        $frequencia = (string)($varsOverride['frequencia_coleta'] ?? self::frequenciaPlano($plano));
        $valorMensal = FormatHelper::money((float)($snapshot['valor_mensal'] ?? $contrato->valor_mensal));
        $taxaAdesao = (float)($snapshot['taxa_adesao'] ?? $contrato->taxa_adesao ?? 0);
        $taxaAplicavel = !empty($flags['taxa_adesao_aplicavel']);

        $foroCidade = $contrato->foro_cidade ?: (string)($meta['foro_cidade'] ?? 'Alta Floresta');
        $foroUf = $contrato->foro_uf ?: (string)($meta['foro_uf'] ?? 'MT');
        $dataAssinatura = 'Alta Floresta - MT, '.FormatHelper::dateBr($contrato->data_inicio);

        $multaRescisao = trim((string)($contrato->multa_rescisao_texto ?? ''));
        if ($multaRescisao === '') {
            $multaRescisao = '20% (vinte por cento) do valor residual do contrato';
        }

        $promocaoHtml = (string)($varsOverride['promocao_html'] ?? '');
        $grupoCHtml = (string)($varsOverride['grupo_c_html'] ?? '');
        $clausulasExtra = trim((string)($varsOverride['clausulas_extra_html'] ?? ($plano->contrato_clausula_extra ?? '')));

        $testemunhas = $modelo->pricing_variant === 'TABELA_TONELADA_CACAMBA'
            ? self::blocoTestemunhas()
            : '';

        $tituloRaw = str_replace(
            '{{contratante_razao}}',
            $contratanteNome,
            $modelo->titulo
        );
        $tituloEsc = htmlspecialchars($tituloRaw, ENT_QUOTES, 'UTF-8');

        $map = [
            'URL' => rtrim((string)URL, '/'),
            'logo_html' => ContratoBrandingHelper::logoHtml($contrato->operadora_id),
            'titulo' => $tituloEsc,
            'titulo_completo' => $tituloEsc,
            'qualificacao_preambulo' => $qualificacao,
            'contratada_razao' => htmlspecialchars($contratadaNome, ENT_QUOTES, 'UTF-8'),
            'contratada_cnpj' => htmlspecialchars($contratadaCnpj, ENT_QUOTES, 'UTF-8'),
            'contratada_endereco' => htmlspecialchars($enderecoSede, ENT_QUOTES, 'UTF-8'),
            'contratante_razao' => htmlspecialchars($contratanteNome, ENT_QUOTES, 'UTF-8'),
            'contratante_cnpj' => htmlspecialchars($cliente->cnpj, ENT_QUOTES, 'UTF-8'),
            'contratante_endereco' => htmlspecialchars(Cliente::enderecoCompleto($cliente), ENT_QUOTES, 'UTF-8'),
            'frequencia_coleta' => htmlspecialchars($frequencia, ENT_QUOTES, 'UTF-8'),
            'valor_mensal' => $valorMensal,
            'franquia_resumo' => htmlspecialchars(ContratoPricingPartial::franquiaResumoTexto($snapshot), ENT_QUOTES, 'UTF-8'),
            'linhas_excedente_html' => ContratoPricingPartial::linhasExcedenteHtml($snapshot, $modelo->pricing_variant),
            'taxa_adesao_bloco' => ContratoPricingPartial::taxaAdesaoBloco($taxaAdesao, $taxaAplicavel),
            'indice_reajuste' => htmlspecialchars($contrato->indice_reajuste ?: (string)($meta['indice_reajuste'] ?? 'IPCA/IBGE'), ENT_QUOTES, 'UTF-8'),
            'data_inicio_br' => FormatHelper::dateBr($contrato->data_inicio),
            'data_fim_br' => FormatHelper::dateBr($contrato->data_fim),
            'qtd_meses' => (string)$contrato->qtd_meses,
            'aviso_previo_dias' => (string)($contrato->aviso_previo_dias ?? 30),
            'multa_rescisao_texto' => htmlspecialchars($multaRescisao, ENT_QUOTES, 'UTF-8'),
            'multa_atraso_descricao' => htmlspecialchars($contrato->multa_atraso_descricao, ENT_QUOTES, 'UTF-8'),
            'foro_cidade' => htmlspecialchars($foroCidade, ENT_QUOTES, 'UTF-8'),
            'foro_uf' => htmlspecialchars($foroUf, ENT_QUOTES, 'UTF-8'),
            'data_assinatura_linha' => htmlspecialchars($dataAssinatura, ENT_QUOTES, 'UTF-8'),
            'promocao_html' => $promocaoHtml,
            'grupo_c_html' => $grupoCHtml,
            'clausulas_extra_html' => nl2br(htmlspecialchars($clausulasExtra, ENT_QUOTES, 'UTF-8')),
            'tabela_rcc_html' => ContratoPricingPartial::linhasExcedenteHtml($snapshot, 'TABELA_TONELADA_CACAMBA'),
            'assinaturas_html' => self::blocoAssinaturas($contratadaNome, $contratadaCnpj, $contratanteNome, $cliente->cnpj),
            'testemunhas_html' => $testemunhas,
            'numero_contrato' => htmlspecialchars($contrato->numero, ENT_QUOTES, 'UTF-8'),
        ];

        foreach ($varsOverride as $k => $v) {
            if (is_string($k) && is_scalar($v) && !isset($map[$k])) {
                $map[$k] = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
            }
        }

        return $map;
    }

    private static function configOperadora(int $operadoraId, string $chave): ?string
    {
        try {
            $db = new Database();
            $row = $db->execute(
                'SELECT valor FROM operadora_config WHERE operadora_id = ? AND chave = ? LIMIT 1',
                [$operadoraId, $chave]
            )->fetch(\PDO::FETCH_ASSOC);

            return is_array($row) ? (string)$row['valor'] : null;
        } catch (\Throwable) {
            return OperadoraConfig::get($chave);
        }
    }

    private static function qualificacaoPreambulo(
        string $contratadaNome,
        string $contratadaCnpj,
        string $enderecoContratada,
        string $contratanteRazao,
        string $contratanteCnpj,
        string $enderecoContratante,
        string $rep,
        string $cargo,
        string $cpf,
        string $rg,
        string $tel,
        string $email
    ): string {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $repPart = '';
        if ($rep !== '') {
            $repPart = ', neste ato representada por '.$e($rep);
            if ($cargo !== '') {
                $repPart .= ', '.$e($cargo);
            }
            $repPart .= ', portador(a) do CPF nº '.$e($cpf ?: '—');
            if ($rg !== '') {
                $repPart .= ' e RG nº '.$e($rg);
            }
            if ($tel !== '') {
                $repPart .= ', telefone: '.$e($tel);
            }
            if ($email !== '') {
                $repPart .= ', e-mail: '.$e($email);
            }
        }

        return '<p>Pelo presente instrumento de contrato de um lado, como <strong>CONTRATADA</strong>, '
            .$e($contratadaNome).', inscrita no CNPJ/MF sob o nº '.$e($contratadaCnpj)
            .', com sede na '.$e($enderecoContratada)
            .', neste ato representada por seu sócio proprietário, de outro lado, '
            .$e($contratanteRazao).', pessoa jurídica de direito privado, inscrita no CNPJ sob nº '
            .$e($contratanteCnpj).', com sede à '.$e($enderecoContratante)
            .$repPart
            .', doravante denominado(a) simplesmente <strong>CONTRATANTE</strong>, ajustam e celebram o presente Contrato, '
            .'que será regido pelas cláusulas e condições a seguir estipuladas:</p>';
    }

    private static function blocoAssinaturas(string $contratada, string $cnpjC, string $contratante, string $cnpjT): string
    {
        $e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return '<div class="assinaturas mt-4">'
            .'<p>E por estarem assim, justos e contratados, assinam este CONTRATO em 02 (duas) vias de igual teor e um só efeito.</p>'
            .'<div class="row mt-5"><div class="col-6 text-center"><p><strong>'.$e($contratada).'</strong><br>CNPJ n.º '.$e($cnpjC).'</p></div>'
            .'<div class="col-6 text-center"><p><strong>'.$e($contratante).'</strong><br>CNPJ nº '.$e($cnpjT).'</p></div></div></div>';
    }

    private static function blocoTestemunhas(): string
    {
        return '<div class="testemunhas mt-4"><p><strong>Testemunhas:</strong></p>'
            .'<p>Nome: _________________________ &nbsp; Documento: _________________________</p>'
            .'<p>Nome: _________________________ &nbsp; Documento: _________________________</p></div>';
    }

    private static function frequenciaPlano(Plano $plano): string
    {
        $periodo = $plano->coletas_periodo_meses;
        if ($periodo !== null && $periodo > 1) {
            return 'a cada '.$periodo.' meses ('.$plano->coletas_por_periodo.' coleta(s))';
        }
        $cm = (float)$plano->coletas_mensais;
        if ($cm <= 0) {
            return 'conforme acordado entre as partes';
        }
        if ($cm === 1.0) {
            return 'mensalmente';
        }

        return 'até '.number_format($cm, 0, ',', '.').' vezes por mês';
    }
}
