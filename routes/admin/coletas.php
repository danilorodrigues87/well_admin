<?php

use App\Controller\Admin\Agendamentos;
use App\Controller\Admin\ColetaNova;
use App\Controller\Admin\Coletas;
use App\Controller\Admin\FrotaMapa;
use App\Controller\Admin\RotaDoDia;
use App\Http\Response;

// ── Rota do dia (mapa + otimização) ──
$obRouter->get('/painel/rota-do-dia', [
    'middlewares' => ['required-admin-login', 'required-module:rota_dia'],
    function ($request) {
        return new Response(200, RotaDoDia::index($request));
    },
]);

$obRouter->post('/painel/rota-do-dia', [
    'middlewares' => ['required-admin-login', 'required-module:rota_dia'],
    function ($request) {
        $acao = $request->getPostVars()['acao'] ?? '';
        $content = match ($acao) {
            'paradas' => RotaDoDia::paradas($request),
            'otimizar' => RotaDoDia::otimizar($request),
            'salvar_ordem' => RotaDoDia::salvarOrdem($request),
            'geocode_paradas' => RotaDoDia::geocodeParadas($request),
            'registrar_posicao' => RotaDoDia::registrarPosicao($request),
            'parada_status' => RotaDoDia::paradaStatus($request),
            default => json_encode(['success' => false, 'message' => 'Ação inválida']),
        };
        return new Response(200, $content, 'application/json');
    },
]);

$obRouter->get('/painel/frota/mapa', [
    'middlewares' => ['required-admin-login', 'required-module:frota_mapa'],
    function ($request) {
        return new Response(200, FrotaMapa::index($request));
    },
]);

$obRouter->post('/painel/frota/mapa', [
    'middlewares' => ['required-admin-login', 'required-module:frota_mapa'],
    function ($request) {
        return new Response(200, FrotaMapa::post($request), 'application/json');
    },
]);

// ── Lançar coleta (wizard) ──
$obRouter->get('/painel/coleta/nova', [
    'middlewares' => ['required-admin-login', 'required-module:coleta_nova'],
    function ($request) {
        return new Response(200, ColetaNova::selecionarCliente($request));
    },
]);

$obRouter->post('/painel/coleta/nova', [
    'middlewares' => ['required-admin-login', 'required-module:coleta_nova'],
    function ($request) {
        return new Response(200, ColetaNova::post($request), 'application/json');
    },
]);

$obRouter->get('/painel/coleta/nova/{id}', [
    'middlewares' => ['required-admin-login', 'required-module:coleta_nova'],
    function ($request, int $id) {
        return new Response(200, ColetaNova::wizard($request, $id));
    },
]);

$obRouter->post('/painel/coleta/nova/{id}', [
    'middlewares' => ['required-admin-login', 'required-module:coleta_nova'],
    function ($request, int $id) {
        return new Response(200, ColetaNova::post($request, $id), 'application/json');
    },
]);

// ── Impressão MTR ──
$obRouter->get('/painel/coletas/mtr/{id}', [
    'middlewares' => ['required-admin-login', 'required-module:coletas'],
    function ($request, int $id) {
        return new Response(200, Coletas::mtrPrint($request, $id));
    },
]);

$obRouter->get('/painel/coletas/cdf/{id}', [
    'middlewares' => ['required-admin-login', 'required-module:coletas'],
    function ($request, int $id) {
        $out = Coletas::cdfDownload($request, $id);
        return $out instanceof Response ? $out : new Response(200, $out);
    },
]);

// ── Listagem coletas ──
$coletaCrud = [
    ['path' => '/painel/coletas', 'ctrl' => Coletas::class, 'module' => 'coletas'],
    ['path' => '/painel/agendamentos', 'ctrl' => Agendamentos::class, 'module' => 'agendamentos'],
];

foreach ($coletaCrud as $route) {
    $ctrl = $route['ctrl'];
    $module = $route['module'];
    $path = $route['path'];

    $obRouter->get($path, [
        'middlewares' => ['required-admin-login', 'required-module:'.$module],
        function ($request) use ($ctrl) {
            return new Response(200, $ctrl::index($request));
        },
    ]);

    $obRouter->post($path, [
        'middlewares' => ['required-admin-login', 'required-module:'.$module],
        function ($request) use ($ctrl) {
            $acao = $request->getPostVars()['acao'] ?? 'listar';
            $content = match ($acao) {
                'listar' => $ctrl::list($request),
                'get' => $ctrl::get($request),
                'sinir_precheck' => $ctrl === Coletas::class ? Coletas::sinirPrecheck($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'sinir_reenviar' => $ctrl === Coletas::class ? Coletas::sinirReenviar($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'sinir_cancelar' => $ctrl === Coletas::class ? Coletas::sinirCancelar($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'sinir_consultar' => $ctrl === Coletas::class ? Coletas::sinirConsultar($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'sinir_receber' => $ctrl === Coletas::class ? Coletas::sinirReceber($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'sinir_baixar_pdf' => $ctrl === Coletas::class ? Coletas::sinirBaixarPdf($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'sinir_cdf_upload' => $ctrl === Coletas::class ? Coletas::sinirCdfUpload($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'sinir_emitir_cdf' => $ctrl === Coletas::class ? Coletas::sinirEmitirCdf($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'sinir_baixar_cdf' => $ctrl === Coletas::class ? Coletas::sinirBaixarCdf($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'salvar' => method_exists($ctrl, 'save') ? $ctrl::save($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'agendar_rota' => $ctrl === Agendamentos::class ? Agendamentos::agendarRota($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'list_solicitacoes' => $ctrl === Agendamentos::class ? Agendamentos::listSolicitacoes($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'get_solicitacao' => $ctrl === Agendamentos::class ? Agendamentos::getSolicitacao($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'aprovar_solicitacao' => $ctrl === Agendamentos::class ? Agendamentos::aprovarSolicitacao($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                'recusar_solicitacao' => $ctrl === Agendamentos::class ? Agendamentos::recusarSolicitacao($request) : json_encode(['success' => false, 'message' => 'Ação inválida']),
                default => json_encode(['success' => false, 'message' => 'Ação inválida']),
            };
            return new Response(200, $content, 'application/json');
        },
    ]);
}

// ── Servir evidências (storage) ──
$obRouter->get('/storage/coletas/{coletaId}/{arquivo}', [
    'middlewares' => ['required-admin-login'],
    function ($request, int $coletaId, string $arquivo) {
        $arquivo = basename($arquivo);
        $path = dirname(__DIR__, 2).'/storage/coletas/'.$coletaId.'/'.$arquivo;
        if (!is_file($path)) {
            return new Response(404, 'Arquivo não encontrado');
        }
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        return new Response(200, file_get_contents($path), $mime);
    },
]);
