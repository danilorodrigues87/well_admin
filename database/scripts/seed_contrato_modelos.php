<?php

declare(strict_types=1);

/**
 * Seed idempotente dos modelos jurídicos (operadora 1).
 * Uso: php database/scripts/seed_contrato_modelos.php
 */

if (!defined('URL')) {
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    require dirname(__DIR__, 2).'/includes/app.php';
}

use App\Model\Db\Database;

$operadoraId = 1;
$seedDir = dirname(__DIR__).'/seeds/contrato_modelos';

/** @var list<array{slug:string,nome:string,titulo:string,pricing_variant:string,file?:string,flags?:array}> */
$modelos = [
    [
        'slug' => 'CLASSE_I',
        'nome' => 'Classe I (perigosos)',
        'titulo' => 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE COLETA, TRANSPORTE E DESTINAÇÃO FINAL ADEQUADA DE RESÍDUOS CLASSE I (CONTAMINADOS COM ÓLEO), QUE ENTRE SI FAZEM WELL SOLUÇÕES AMBIENTAIS E A {{contratante_razao}}.',
        'pricing_variant' => 'FRANQUIA_KG',
        'file' => 'CLASSE_I.html',
    ],
    [
        'slug' => 'RECICLAVEIS',
        'nome' => 'Classe II-B recicláveis',
        'titulo' => 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE COLETA, TRANSPORTE E DESTINAÇÃO FINAL ADEQUADA DE RESÍDUOS RECICLÁVEIS – CLASSE II B, QUE ENTRE SI FAZEM WELL SOLUÇÕES AMBIENTAIS E A {{contratante_razao}}.',
        'pricing_variant' => 'FRANQUIA_KG',
        'derive' => 'RECICLAVEIS',
    ],
    [
        'slug' => 'PNEUS',
        'nome' => 'Classe II-A pneus',
        'titulo' => 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE COLETA, TRANSPORTE E DESTINAÇÃO FINAL ADEQUADA DE PNEUS INSERVÍVEIS, QUE ENTRE SI FAZEM WELL SOLUÇÕES AMBIENTAIS E A {{contratante_razao}}.',
        'pricing_variant' => 'FRANQUIA_KG',
        'derive' => 'PNEUS',
    ],
    [
        'slug' => 'RSS_CLINICA',
        'nome' => 'RSS clínica',
        'titulo' => 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE COLETA, TRANSPORTE E DESTINAÇÃO FINAL ADEQUADA DE RESÍDUOS SÓLIDOS DOS SERVIÇOS DE SAÚDE, QUE ENTRE SI FAZEM WELL SOLUÇÕES AMBIENTAIS E A {{contratante_razao}}.',
        'pricing_variant' => 'FRANQUIA_KG',
        'derive' => 'RSS_CLINICA',
    ],
    [
        'slug' => 'RSS_HOSPITAL',
        'nome' => 'RSS hospital',
        'titulo' => 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE COLETA, TRANSPORTE E DESTINAÇÃO FINAL ADEQUADA DE RESÍDUOS SÓLIDOS DOS SERVIÇOS DE SAÚDE, QUE ENTRE SI FAZEM WELL SOLUÇÕES AMBIENTAIS E A {{contratante_razao}}.',
        'pricing_variant' => 'FRANQUIA_KG',
        'derive' => 'RSS_HOSPITAL',
    ],
    [
        'slug' => 'CLASSE_I_II',
        'nome' => 'Classe I e II',
        'titulo' => 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE COLETA, TRANSPORTE E DESTINAÇÃO FINAL DE RESÍDUOS CLASSE I E II, QUE ENTRE SI FAZEM WELL SOLUÇÕES AMBIENTAIS E A {{contratante_razao}}.',
        'pricing_variant' => 'FRANQUIA_KG',
        'file' => 'CLASSE_I.html',
    ],
    [
        'slug' => 'RCC_PADRAO',
        'nome' => 'RCC Classe II-A e B',
        'titulo' => 'CONTRATO PARA A PRESTAÇÃO DOS SERVIÇOS DE COLETA, TRIAGEM E DESTINAÇÃO FINAL ADEQUADA DE RESÍDUOS SÓLIDOS CLASSE II – A e B, QUE ENTRE SI FAZEM WELL SOLUÇÕES AMBIENTAIS E A {{contratante_razao}}.',
        'pricing_variant' => 'TABELA_TONELADA_CACAMBA',
        'file' => 'RCC_PADRAO.html',
        'flags' => ['taxa_adesao_aplicavel' => false],
    ],
    [
        'slug' => 'OBRA_GRANDE_PORTE',
        'nome' => 'Obra grande porte',
        'titulo' => 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE COLETA E DESTINAÇÃO EM OBRA DE GRANDE PORTE, QUE ENTRE SI FAZEM WELL SOLUÇÕES AMBIENTAIS E A {{contratante_razao}}.',
        'pricing_variant' => 'VALOR_GLOBAL_OBRA',
        'file' => 'CLASSE_I.html',
        'flags' => ['taxa_adesao_aplicavel' => false],
    ],
];

$db = new Database();
$fallback = @file_get_contents($seedDir.'/CLASSE_I.html');
if ($fallback === false) {
    fwrite(STDERR, "Arquivo base CLASSE_I.html não encontrado.\n");
    exit(1);
}

/**
 * Deriva corpo completo a partir de CLASSE_I com ajustes por slug.
 */
function seed_derive_body(string $base, string $kind): string
{
    $objetoReciclaveis = '<p>O presente contrato tem por objeto a prestação dos serviços de coleta, transporte e destinação final '
        .'de resíduos sólidos recicláveis – Classe II-B (papel, papelão, plástico, vidro, metais e sucatas limpas), '
        .'conforme Lei 12.305/2010, CONAMA 275/2001 e ABNT NBR 10.004.</p>'
        .'<p>A destinação compreenderá encaminhamento a recicladoras ou cooperativas licenciadas, com emissão de CDF quando aplicável.</p>'
        .'<p>A CONTRATADA declara possuir licenças ambientais válidas para as atividades descritas.</p>';

    $objetoPneus = '<p>O presente contrato tem por objeto a prestação dos serviços de coleta, transporte e destinação final '
        .'de pneus inservíveis – Classe II-A, conforme Lei 12.305/2010 e Resolução CONAMA nº 416/2009.</p>'
        .'<p>A CONTRATADA declara possuir licenças ambientais válidas para as atividades descritas.</p>';

    $objetoRss = '<p>O presente contrato tem por objeto a prestação dos serviços de coleta, transporte e destinação final '
        .'de resíduos de serviços de saúde (RSS), conforme Lei 12.305/2010, CONAMA 358/2005 e RDC ANVISA 222/2018 '
        .'(Grupos A, B, D e E; hospital pode incluir Grupo C conforme {{grupo_c_html}}).</p>'
        .'<p>A destinação será em unidade licenciada, com licença e validade indicadas no CDF.</p>'
        .'<p>A CONTRATADA declara possuir licenças ambientais e sanitárias válidas.</p>';

    $body = $base;
    if (preg_match('/<div class="contrato-clausula">\s*<h2>CLÁUSULA PRIMEIRA.*?<\/div>\s*/s', $body, $m, PREG_OFFSET_CAPTURE)) {
        $replacement = match ($kind) {
            'RECICLAVEIS' => '<div class="contrato-clausula"><h2>CLÁUSULA PRIMEIRA - DO OBJETO</h2>'.$objetoReciclaveis.'</div>'."\n\n",
            'PNEUS' => '<div class="contrato-clausula"><h2>CLÁUSULA PRIMEIRA - DO OBJETO</h2>'.$objetoPneus.'</div>'."\n\n",
            'RSS_CLINICA', 'RSS_HOSPITAL' => '<div class="contrato-clausula"><h2>CLÁUSULA PRIMEIRA - DO OBJETO</h2>'.$objetoRss.'</div>'."\n\n",
            default => null,
        };
        if ($replacement !== null) {
            $body = substr($body, 0, $m[0][1]).$replacement.substr($body, $m[0][1] + strlen($m[0][0]));
        }
    }

    $map = [
        'RECICLAVEIS' => [
            'resíduos perigosos – Classe I' => 'resíduos recicláveis – Classe II-B',
            'Classe I, operados' => 'Classe II-B, operados',
            'Resíduos Classe I – Perigosos' => 'Resíduos Classe II-B – Recicláveis',
            'resíduos Classe I' => 'resíduos Classe II-B (recicláveis)',
            'resíduos Classe I (contaminados)' => 'resíduos Classe II-B (recicláveis)',
            'armazenamento adequado' => 'separar e acondicionar corretamente os recicláveis, limpos e secos',
        ],
        'PNEUS' => [
            'resíduos perigosos – Classe I' => 'pneus inservíveis – Classe II-A',
            'Classe I, operados' => 'Classe II-A, operados',
            'Resíduos Classe I – Perigosos' => 'Pneus inservíveis – Classe II-A',
            'resíduos Classe I' => 'pneus inservíveis Classe II-A',
            'resíduos Classe I (contaminados)' => 'pneus inservíveis Classe II-A',
            'armazenamento adequado' => 'acondicionar e armazenar adequadamente os pneus até a coleta',
        ],
        'RSS_CLINICA' => [
            'resíduos perigosos – Classe I' => 'resíduos de serviços de saúde (RSS)',
            'Classe I, operados' => 'RSS, operados',
            'Resíduos Classe I – Perigosos' => 'Resíduos de Serviços de Saúde',
            'resíduos Classe I' => 'resíduos de saúde (RSS)',
            'resíduos Classe I (contaminados)' => 'resíduos de saúde (RSS)',
        ],
        'RSS_HOSPITAL' => [
            'resíduos perigosos – Classe I' => 'resíduos de serviços de saúde (RSS) incl. Grupo C quando aplicável',
            'Classe I, operados' => 'RSS, operados',
            'Resíduos Classe I – Perigosos' => 'Resíduos de Serviços de Saúde',
            'resíduos Classe I' => 'resíduos de saúde (RSS)',
            'resíduos Classe I (contaminados)' => 'resíduos de saúde (RSS)',
        ],
    ];
    foreach ($map[$kind] ?? [] as $from => $to) {
        $body = str_replace($from, $to, $body);
    }

    if ($kind === 'RSS_HOSPITAL') {
        $body = str_replace(
            'classificação NBR 10.004.',
            'classificação NBR 10.004; designar responsável técnico pelo gerenciamento interno dos resíduos.',
            $body
        );
    }

    return $body;
}

foreach ($modelos as $m) {
    $file = $m['file'] ?? 'CLASSE_I.html';
    $path = $seedDir.'/'.$file;
    if (!empty($m['derive'])) {
        $body = seed_derive_body($fallback, (string)$m['derive']);
    } elseif (is_file($path)) {
        $body = file_get_contents($path);
    } else {
        echo "Aviso: {$file} ausente, usando CLASSE_I.html para {$m['slug']}\n";
        $body = $fallback;
    }
    if ($body === false || $body === '') {
        $body = $fallback;
    }
    $flagsJson = isset($m['flags']) ? json_encode($m['flags'], JSON_UNESCAPED_UNICODE) : null;

    $db->execute(
        'INSERT INTO contrato_modelos (operadora_id, slug, nome, versao, titulo, body_html, pricing_variant, flags_json, ativo)
         VALUES (?,?,?,?,?,?,?,?,1)
         ON DUPLICATE KEY UPDATE
           nome = VALUES(nome),
           titulo = VALUES(titulo),
           body_html = VALUES(body_html),
           pricing_variant = VALUES(pricing_variant),
           flags_json = VALUES(flags_json),
           ativo = 1,
           updated_at = CURRENT_TIMESTAMP',
        [
            $operadoraId,
            $m['slug'],
            $m['nome'],
            1,
            $m['titulo'],
            $body,
            $m['pricing_variant'],
            $flagsJson,
        ]
    );
    echo "OK: {$m['slug']}\n";
}

echo "Seed contrato_modelos concluído.\n";
