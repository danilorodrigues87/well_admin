<?php

namespace App\Common\Contrato;

/**
 * Textos e defaults por modelo de contrato Well (8 variantes + genérico).
 */
final class ContratoModeloCatalog
{
    /** @return array<string,mixed> */
    public static function meta(string $contractType): array
    {
        $defaults = [
            'pricing_model' => 'FRANQUIA_EXCEDENTE_KG',
            'default_meses' => 36,
            'indice_reajuste' => 'IPCA/IBGE',
            'taxa_adesao' => 180.0,
            'foro_cidade' => 'Alta Floresta',
            'foro_uf' => 'MT',
            'flags' => [
                'exige_responsavel_tecnico' => false,
                'retencao_garantia_medicao' => false,
                'retencao_garantia_pct' => null,
                'exige_docs_trabalhistas_mensais' => false,
                'clausula_lgpd' => true,
                'clausula_anticorrupcao' => false,
                'primeiro_mes_antecipado' => true,
                'renovacao_automatica' => true,
                'taxa_adesao_aplicavel' => true,
            ],
        ];

        $specific = match (ContractType::normalize($contractType)) {
            ContractType::RSS_CLINICA => [
                'escopo_residuos' => 'Resíduos de Saúde (RSS) dos Grupos A (Biológicos), B (Químicos), D (Comuns) e E (Perfurocortantes).',
                'legislacao_especifica' => 'Resolução CONAMA nº 358/2005, RDC ANVISA nº 222/2018 e ABNT NBR 12.808, 12.809 e 12.810.',
            ],
            ContractType::RSS_HOSPITAL => [
                'escopo_residuos' => 'Resíduos de Saúde (RSS) dos Grupos A, B, C (Radionuclídeos/Radioativos), D e E.',
                'legislacao_especifica' => 'RDC ANVISA nº 222/2018 e Resolução CONAMA nº 358/2005.',
                'flags' => ['exige_responsavel_tecnico' => true],
            ],
            ContractType::CLASSE_I_II => [
                'escopo_residuos' => 'Resíduos Classe I (Perigosos): estopas, EPIs, panos contaminados com óleo, embalagens oleosas, terra/água contaminada; '
                    .'e Classe II-B (Recicláveis): plásticos, metais, papelão, vidros.',
                'legislacao_especifica' => 'Resoluções CONAMA nº 313/2002 e 352/2005, ABNT NBR 10.004, NBR 12.235 e normas ANTT para transporte de produtos perigosos.',
            ],
            ContractType::CLASSE_I => [
                'escopo_residuos' => 'Resíduos Classe I (Perigosos), inclusive óleos, graxas, solventes e materiais contaminados.',
                'legislacao_especifica' => 'Resoluções CONAMA nº 362/2005 e 452/2012, ABNT NBR 10.004, NBR 13.157 e NBR 13.221.',
            ],
            ContractType::RECICLAVEIS => [
                'escopo_residuos' => 'Resíduos Classe II-B recicláveis: papel, papelão, plástico, vidro, metais, sucatas e resíduos de pneus inservíveis quando aplicável.',
                'legislacao_especifica' => 'Resolução CONAMA nº 275/2001 e ABNT NBR 10.004.',
            ],
            ContractType::PNEUS => [
                'escopo_residuos' => 'Pneus inservíveis — Classe II-A.',
                'legislacao_especifica' => 'Resolução CONAMA nº 416/2009.',
            ],
            ContractType::RCC_PADRAO => [
                'pricing_model' => 'TABELA_TONELADA_CACAMBA',
                'default_meses' => 12,
                'indice_reajuste' => 'IGP-M',
                'taxa_adesao' => 0.0,
                'escopo_residuos' => 'Resíduos de Construção Civil (RCC) Classe II-A e II-B (concreto, argamassa, alvenaria, cerâmica, solo, madeira, plásticos, metais, gesso).',
                'legislacao_especifica' => 'Resolução CONAMA nº 307/2002.',
                'flags' => ['taxa_adesao_aplicavel' => false],
            ],
            ContractType::OBRA_GRANDE_PORTE => [
                'pricing_model' => 'VALOR_GLOBAL_OBRA',
                'default_meses' => 8,
                'foro_cidade' => 'Cuiabá',
                'foro_uf' => 'MT',
                'taxa_adesao' => 0.0,
                'escopo_residuos' => 'Coleta, tratamento e destinação final de resíduos de canteiro de obras.',
                'legislacao_especifica' => 'Resolução CONAMA nº 307/2002 e demais normas aplicáveis à obra.',
                'flags' => [
                    'retencao_garantia_medicao' => true,
                    'retencao_garantia_pct' => 5.0,
                    'exige_docs_trabalhistas_mensais' => true,
                    'clausula_lgpd' => true,
                    'clausula_anticorrupcao' => true,
                    'taxa_adesao_aplicavel' => false,
                ],
            ],
            default => [
                'escopo_residuos' => 'Resíduos conforme plano contratado e legislação ambiental vigente.',
                'legislacao_especifica' => 'demais normas técnicas e ambientais aplicáveis ao serviço.',
            ],
        };

        if (isset($specific['flags'])) {
            $defaults['flags'] = array_merge($defaults['flags'], $specific['flags']);
            unset($specific['flags']);
        }

        return array_merge($defaults, $specific);
    }
}
