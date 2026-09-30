<?php

namespace App\Controller\Admin;

use App\Common\Contrato\ContractType;
use App\Common\Helpers\CsrfHelper;
use App\Common\Helpers\FormatHelper;
use App\Model\Entity\Cliente;
use App\Model\Entity\ClienteContrato;
use App\Model\Entity\ContratoModelo;
use App\Model\Entity\Plano;
use App\Service\Contrato\ContratoComercialSnapshot;
use App\Service\Contrato\ContratoPlanoItensPresenter;
use App\Service\Contrato\ContratoPreRequisitosService;
use App\Service\ContratoClienteService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class ContratosClientes extends Page
{
    public static function index($request): string
    {
        $query = $request->getQueryParams();
        $clienteId = (int)($query['cliente_id'] ?? 0);
        $statusFiltro = trim((string)($query['status'] ?? ''));

        $clientesOpts = '';
        $clientesOptsNovo = '<option value="">Selecione o cliente…</option>';
        foreach (Cliente::list("c.status = 'ativo'", [], '500') as $cl) {
            $nome = htmlspecialchars($cl->nome_fantasia, ENT_QUOTES, 'UTF-8');
            $sel = $clienteId === $cl->id ? ' selected' : '';
            $clientesOpts .= '<option value="'.$cl->id.'"'.$sel.'>'.$nome.'</option>';
            $clientesOptsNovo .= '<option value="'.$cl->id.'">'.$nome.'</option>';
        }

        $statusTabs = self::statusTabsHtml($statusFiltro, $clienteId);

        $rows = '';
        foreach (ClienteContrato::listAll(
            $clienteId > 0 ? $clienteId : null,
            $statusFiltro !== '' ? $statusFiltro : null
        ) as $c) {
            $plano = Plano::getById($c->plano_id);
            $modeloLabel = $plano
                ? htmlspecialchars(ContractType::label($plano->contrato_modelo_tipo), ENT_QUOTES, 'UTF-8')
                : '—';
            $aguardando = $c->status === 'aguardando_assinatura'
                ? '<span class="badge bg-warning text-dark">Sim</span>'
                : '<span class="text-muted">—</span>';
            $rows .= '<tr>'
                .'<td>'.htmlspecialchars($c->numero, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.htmlspecialchars($c->cliente_nome, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.htmlspecialchars($c->plano_nome, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td class="small">'.$modeloLabel.'</td>'
                .'<td>'.FormatHelper::money($c->valor_mensal).'</td>'
                .'<td>'.FormatHelper::dateBr($c->data_inicio).' — '.FormatHelper::dateBr($c->data_fim).'</td>'
                .'<td>'.self::statusBadge($c->status).'</td>'
                .'<td>'.$aguardando.'</td>'
                .'<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="'
                .htmlspecialchars(self::urlContrato($c->id, self::contratosListPath($clienteId, $statusFiltro)), ENT_QUOTES, 'UTF-8')
                .'">Abrir</a></td>'
                .'</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="9" class="text-center text-muted py-4">'
                .'Nenhum contrato encontrado. Selecione um cliente acima e clique em <strong>Criar</strong>, '
                .'ou use o ícone de contrato na listagem de Clientes.'
                .'</td></tr>';
        }

        $content = View::render('admin/modules/contratos/index', [
            'clientes_options' => $clientesOpts,
            'clientes_options_novo' => $clientesOptsNovo,
            'rows' => $rows,
            'status_tabs' => $statusTabs,
            'status_hidden' => htmlspecialchars($statusFiltro, ENT_QUOTES, 'UTF-8'),
            'return_query' => 'return='.rawurlencode(self::contratosListPath($clienteId, $statusFiltro)),
        ]);

        return self::getPage('Contratos', $content, 'contratos');
    }

    public static function planoItensJson($request): string
    {
        $planoId = (int)($request->getQueryParams()['plano_id'] ?? 0);
        $payload = ContratoPlanoItensPresenter::payloadForPlano($planoId);

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    public static function listByCliente($request, int $clienteId): string
    {
        $cliente = Cliente::getById($clienteId);
        if (!$cliente) {
            $request->getRouter()->redirect('/painel/contratos');
        }

        $rows = '';
        foreach (ClienteContrato::listByCliente($clienteId) as $c) {
            $rows .= '<tr>'
                .'<td>'.htmlspecialchars($c->numero, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.htmlspecialchars($c->plano_nome, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.self::statusBadge($c->status).'</td>'
                .'<td><a class="btn btn-sm btn-outline-primary" href="'
                .htmlspecialchars(self::urlContrato($c->id, self::contratosListPath($clienteId)), ENT_QUOTES, 'UTF-8')
                .'">Ver</a></td>'
                .'</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="text-muted text-center">Nenhum contrato.</td></tr>';
        }

        $voltarLista = URL.self::contratosListPath($clienteId);

        $content = View::render('admin/modules/contratos/lista_cliente', [
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'cliente_id' => $clienteId,
            'rows' => $rows,
            'voltar_lista_url' => htmlspecialchars($voltarLista, ENT_QUOTES, 'UTF-8'),
            'return_path_encoded' => rawurlencode(self::contratosListPath($clienteId)),
        ]);

        return self::getPage('Contratos — '.$cliente->nome_fantasia, $content, 'contratos');
    }

    public static function novo($request, int $clienteId): string
    {
        $cliente = Cliente::getById($clienteId);
        if (!$cliente) {
            $request->getRouter()->redirect('/painel/contratos');
        }

        $planos = Plano::getAllActive();
        $planosOpts = '';
        foreach ($planos as $p) {
            $sel = (int)$cliente->plano_id === $p->id ? ' selected' : '';
            $meta = \App\Common\Contrato\ContratoModeloCatalog::meta($p->contrato_modelo_tipo ?? ContractType::GENERICO);
            $planosOpts .= '<option value="'.$p->id.'"'
                .' data-valor="'.htmlspecialchars((string)$p->valor_mensal, ENT_QUOTES, 'UTF-8').'"'
                .' data-modelo="'.htmlspecialchars(ContractType::label($p->contrato_modelo_tipo), ENT_QUOTES, 'UTF-8').'"'
                .' data-meses="'.(int)($meta['default_meses'] ?? 36).'"'
                .$sel.'>'
                .htmlspecialchars($p->nome, ENT_QUOTES, 'UTF-8').'</option>';
        }
        if ($planosOpts === '') {
            $planosOpts = '<option value="">Cadastre um plano ativo em Cadastros → Planos</option>';
        }

        $erro = (string)($request->getQueryParams()['erro'] ?? '');
        $alerta = '';
        if ($erro === 'csrf') {
            $alerta = '<div class="alert alert-danger">Sessão expirada. Tente novamente.</div>';
        } elseif ($erro === '1') {
            $alerta = '<div class="alert alert-danger">Não foi possível criar o contrato. Verifique se há plano ativo selecionado.</div>';
        } elseif ($erro === 'itens') {
            $alerta = '<div class="alert alert-danger">Selecione ao menos um tipo de resíduo para este gerador.</div>';
        } elseif ($erro === 'pre') {
            $msg = trim((string)($request->getQueryParams()['msg'] ?? ''));
            $alerta = '<div class="alert alert-danger">'.($msg !== ''
                ? htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
                : 'Complete os pré-requisitos antes de gerar o contrato.').'</div>';
        } elseif ($planos === []) {
            $alerta = '<div class="alert alert-warning">'
                .'Não há planos ativos. Cadastre ao menos um em <a href="'.URL.'/painel/planos">Planos</a> antes de gerar contratos.'
                .'</div>';
        }

        $returnPath = self::returnPathFromRequest($request, $clienteId);
        $voltarUrl = URL.$returnPath;

        $content = View::render('admin/modules/contratos/form', [
            'cliente_id' => $clienteId,
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'voltar_url' => htmlspecialchars($voltarUrl, ENT_QUOTES, 'UTF-8'),
            'return_path' => htmlspecialchars($returnPath, ENT_QUOTES, 'UTF-8'),
            'planos_options' => $planosOpts,
            'data_inicio' => date('Y-m-d'),
            'primeira_competencia' => date('Y-m'),
            'csrf_field' => CsrfHelper::field(),
            'alerta' => $alerta,
            'sem_planos' => $planos === [] ? '1' : '0',
            'checklist_cliente' => self::checklistClienteHtml($cliente),
            'prefill_tipo_ids' => json_encode(
                ClienteContrato::ultimoSnapshotTipoResiduoIds($clienteId),
                JSON_UNESCAPED_UNICODE
            ),
        ]);

        return self::getPage('Novo contrato', $content, 'contratos');
    }

    public static function salvar($request, int $clienteId): void
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/clientes/'.$clienteId.'/contrato/novo?erro=csrf&return='.rawurlencode(self::returnPathFromPost($post, $clienteId)));
            exit;
        }
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);

        $valorPost = trim((string)($post['valor_mensal'] ?? ''));

        $cliente = Cliente::getById($clienteId);
        $planoIdPost = (int)($post['plano_id'] ?? 0);
        if ($cliente && $planoIdPost > 0) {
            $preErros = ContratoPreRequisitosService::errosCompletos($cliente, $planoIdPost);
            if ($preErros !== []) {
                $msg = urlencode('Faltam: '.implode(', ', $preErros));
                header('Location: '.URL.'/painel/clientes/'.$clienteId.'/contrato/novo?erro=pre&msg='.$msg.'&return='.rawurlencode(self::returnPathFromPost($post, $clienteId)));
                exit;
            }
        }

        $itensTipo = ContratoClienteService::parseTipoResiduoIds($post['itens_tipo_residuo_id'] ?? null);
        if ($itensTipo === []) {
            header('Location: '.URL.'/painel/clientes/'.$clienteId.'/contrato/novo?erro=itens&return='.rawurlencode(self::returnPathFromPost($post, $clienteId)));
            exit;
        }

        $id = ContratoClienteService::criar($clienteId, $usuarioId, [
            'plano_id' => (int)($post['plano_id'] ?? 0),
            'valor_mensal' => $valorPost !== '' ? str_replace(',', '.', $valorPost) : null,
            'qtd_meses' => (int)($post['qtd_meses'] ?? 36),
            'data_inicio' => (string)($post['data_inicio'] ?? date('Y-m-d')),
            'dia_vencimento' => (int)($post['dia_vencimento'] ?? 10),
            'primeira_competencia' => (string)($post['primeira_competencia'] ?? date('Y-m')),
            'taxa_adesao' => trim((string)($post['taxa_adesao'] ?? '')),
            'indice_reajuste' => trim((string)($post['indice_reajuste'] ?? '')),
            'foro_cidade' => trim((string)($post['foro_cidade'] ?? '')),
            'foro_uf' => trim((string)($post['foro_uf'] ?? '')),
            'itens_tipo_residuo_id' => $itensTipo,
            'exigir_itens_selecionados' => true,
            'status' => 'rascunho',
        ]);

        if ($id <= 0) {
            header('Location: '.URL.'/painel/clientes/'.$clienteId.'/contrato/novo?erro=1&return='.rawurlencode(self::returnPathFromPost($post, $clienteId)));
            exit;
        }

        ContratoClienteService::atualizarPersonalizacao($id, [
            'frequencia_coleta' => trim((string)($post['frequencia_coleta'] ?? '')),
            'promocao_html' => (string)($post['promocao_html'] ?? ''),
            'clausulas_extra_html' => (string)($post['clausulas_extra_html'] ?? ''),
        ]);

        if (!empty($post['enviar_assinatura'])) {
            ContratoClienteService::enviarParaAssinatura($id);
        }

        header('Location: '.self::urlContrato($id, self::returnPathFromPost($post, $clienteId)));
        exit;
    }

    public static function editar($request, int $id): string
    {
        $contrato = ClienteContrato::getById($id);
        if (!$contrato || !in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            $request->getRouter()->redirect('/painel/contratos/'.$id);
        }

        $erro = (string)($request->getQueryParams()['erro'] ?? '');
        $alertaEdit = $erro === 'itens'
            ? '<div class="alert alert-danger">Selecione ao menos um tipo de resíduo.</div>'
            : '';

        $vars = [];
        if ($contrato->variables_json) {
            $decoded = json_decode($contrato->variables_json, true);
            if (is_array($decoded)) {
                $vars = $decoded;
            }
        }

        $snapshotResumo = '';
        $snap = json_decode($contrato->comercial_snapshot_json ?? '', true);
        if (is_array($snap) && !empty($snap['itens'])) {
            $snapshotResumo = '<ul class="small mb-0">';
            foreach ($snap['itens'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $snapshotResumo .= '<li>'.htmlspecialchars((string)($item['tipo_nome'] ?? ''), ENT_QUOTES, 'UTF-8')
                    .' — franquia '.htmlspecialchars((string)($item['saldo_incluso'] ?? ''), ENT_QUOTES, 'UTF-8')
                    .' '.htmlspecialchars((string)($item['unidade'] ?? 'kg'), ENT_QUOTES, 'UTF-8')
                    .', excedente R$ '.htmlspecialchars((string)($item['valor_excedente'] ?? ''), ENT_QUOTES, 'UTF-8')
                    .'</li>';
            }
            $snapshotResumo .= '</ul>';
        } else {
            $snapshotResumo = '<p class="text-muted small mb-0">Nenhum item no snapshot.</p>';
        }

        $snapDecoded = ContratoComercialSnapshot::decode($contrato->comercial_snapshot_json);
        $returnPath = self::returnPathFromRequest($request, $contrato->cliente_id);
        $showUrl = self::urlContrato($id, $returnPath);

        $content = View::render('admin/modules/contratos/editar', [
            'alerta' => $alertaEdit,
            'contrato_id' => $id,
            'voltar_url' => htmlspecialchars($showUrl, ENT_QUOTES, 'UTF-8'),
            'return_path' => htmlspecialchars($returnPath, ENT_QUOTES, 'UTF-8'),
            'plano_id' => $contrato->plano_id,
            'numero' => htmlspecialchars($contrato->numero, ENT_QUOTES, 'UTF-8'),
            'valor_mensal' => number_format($contrato->valor_mensal, 2, ',', '.'),
            'taxa_adesao' => $contrato->taxa_adesao !== null ? number_format($contrato->taxa_adesao, 2, ',', '.') : '',
            'indice_reajuste' => htmlspecialchars($contrato->indice_reajuste, ENT_QUOTES, 'UTF-8'),
            'foro_cidade' => htmlspecialchars($contrato->foro_cidade, ENT_QUOTES, 'UTF-8'),
            'foro_uf' => htmlspecialchars($contrato->foro_uf, ENT_QUOTES, 'UTF-8'),
            'qtd_meses' => (string)$contrato->qtd_meses,
            'data_inicio' => htmlspecialchars($contrato->data_inicio, ENT_QUOTES, 'UTF-8'),
            'dia_vencimento' => (string)$contrato->dia_vencimento,
            'frequencia_coleta' => htmlspecialchars((string)($vars['frequencia_coleta'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'promocao_html' => htmlspecialchars((string)($vars['promocao_html'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'clausulas_extra_html' => htmlspecialchars((string)($vars['clausulas_extra_html'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'snapshot_itens' => $snapshotResumo,
            'prefill_tipo_ids' => json_encode(
                ContratoComercialSnapshot::tipoResiduoIdsFromSnapshot($snapDecoded),
                JSON_UNESCAPED_UNICODE
            ),
            'csrf_field' => CsrfHelper::field(),
        ]);

        return self::getPage('Editar contrato', $content, 'contratos');
    }

    public static function salvarEdicao($request, int $id): void
    {
        $post = $request->getPostVars();
        $contratoRef = ClienteContrato::getById($id);
        $clienteRefId = $contratoRef ? $contratoRef->cliente_id : 0;
        $returnPath = self::returnPathFromPost($post, $clienteRefId);
        $returnQ = 'return='.rawurlencode($returnPath);
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/contratos/'.$id.'/editar?erro=csrf&'.$returnQ);
            exit;
        }
        if (!empty($post['recarregar_snapshot'])) {
            ContratoClienteService::recarregarSnapshotPlano($id);
            header('Location: '.URL.'/painel/contratos/'.$id.'/editar?ok=snapshot&'.$returnQ);
            exit;
        }

        $itensTipo = ContratoClienteService::parseTipoResiduoIds($post['itens_tipo_residuo_id'] ?? null);
        if ($itensTipo === [] && empty($post['recarregar_snapshot'])) {
            header('Location: '.URL.'/painel/contratos/'.$id.'/editar?erro=itens&'.$returnQ);
            exit;
        }

        $ok = ContratoClienteService::atualizarPersonalizacao($id, [
            'valor_mensal' => trim((string)($post['valor_mensal'] ?? '')),
            'taxa_adesao' => trim((string)($post['taxa_adesao'] ?? '')),
            'indice_reajuste' => trim((string)($post['indice_reajuste'] ?? '')),
            'foro_cidade' => trim((string)($post['foro_cidade'] ?? '')),
            'foro_uf' => trim((string)($post['foro_uf'] ?? '')),
            'qtd_meses' => (int)($post['qtd_meses'] ?? 36),
            'data_inicio' => trim((string)($post['data_inicio'] ?? '')),
            'dia_vencimento' => (int)($post['dia_vencimento'] ?? 10),
            'frequencia_coleta' => trim((string)($post['frequencia_coleta'] ?? '')),
            'promocao_html' => (string)($post['promocao_html'] ?? ''),
            'clausulas_extra_html' => (string)($post['clausulas_extra_html'] ?? ''),
            'itens_tipo_residuo_id' => $itensTipo,
            'exigir_itens_selecionados' => empty($post['recarregar_snapshot']),
            'atualizar_snapshot' => !empty($post['atualizar_snapshot']),
        ]);

        header('Location: '.($ok ? self::urlContrato($id, $returnPath) : URL.'/painel/contratos/'.$id.'/editar?erro=1&'.$returnQ));
        exit;
    }

    public static function show($request, int $id): string
    {
        $contrato = ClienteContrato::getById($id);
        if (!$contrato) {
            $request->getRouter()->redirect('/painel/contratos');
        }

        $returnPath = self::returnPathFromRequest($request, $contrato->cliente_id);
        $returnField = '<input type="hidden" name="return" value="'
            .htmlspecialchars($returnPath, ENT_QUOTES, 'UTF-8').'">';

        $acoes = '';
        if (in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            $acoes .= '<a href="'.htmlspecialchars(
                URL.'/painel/contratos/'.$id.'/editar?return='.rawurlencode($returnPath),
                ENT_QUOTES,
                'UTF-8'
            ).'" class="btn btn-outline-secondary me-2">Personalizar</a>';
        }
        if ($contrato->status === 'rascunho') {
            $acoes .= '<form method="post" action="'.URL.'/painel/contratos/'.$id.'/enviar" class="d-inline">'
                .CsrfHelper::field()
                .$returnField
                .'<button type="submit" class="btn btn-primary">Enviar para assinatura</button></form>';
        }
        if (in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            $acoes .= ' <button type="button" class="btn btn-outline-danger ms-2" data-bs-toggle="modal" data-bs-target="#modalCancelarContrato">Cancelar contrato</button>';
        }
        if ($contrato->status === 'ativo') {
            $acoes .= ' <button type="button" class="btn btn-outline-warning ms-2" data-bs-toggle="modal" data-bs-target="#modalRescindirContrato">Rescindir</button>';
        }
        $modais = '';
        if (in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            $modais .= View::render('admin/modules/contratos/modal_encerrar', [
                'modal_id' => 'modalCancelarContrato',
                'titulo' => 'Cancelar contrato',
                'action' => URL.'/painel/contratos/'.$id.'/cancelar',
                'csrf_field' => CsrfHelper::field(),
                'return_field' => $returnField,
                'botao' => 'Confirmar cancelamento',
                'botao_class' => 'btn-danger',
            ]);
        }
        if ($contrato->status === 'ativo') {
            $modais .= View::render('admin/modules/contratos/modal_encerrar', [
                'modal_id' => 'modalRescindirContrato',
                'titulo' => 'Rescindir contrato ativo',
                'action' => URL.'/painel/contratos/'.$id.'/rescindir',
                'csrf_field' => CsrfHelper::field(),
                'return_field' => $returnField,
                'botao' => 'Confirmar rescisão',
                'botao_class' => 'btn-warning',
            ]);
        }
        if ($contrato->encerrado_motivo) {
            $modais .= '<div class="alert alert-secondary mt-3"><strong>Motivo encerramento:</strong> '
                .htmlspecialchars($contrato->encerrado_motivo, ENT_QUOTES, 'UTF-8')
                .($contrato->encerrado_em ? ' · '.FormatHelper::dateTimeBr($contrato->encerrado_em) : '')
                .'</div>';
        }

        $snap = ContratoComercialSnapshot::decode($contrato->comercial_snapshot_json);
        $itensCount = is_array($snap['itens'] ?? null) ? count($snap['itens']) : 0;

        $erroShow = trim((string)($request->getQueryParams()['erro'] ?? ''));
        $alertaShow = '';
        if ($erroShow === 'pre') {
            $msg = trim((string)($request->getQueryParams()['msg'] ?? ''));
            $alertaShow = '<div class="alert alert-danger">'.($msg !== ''
                ? htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
                : 'Complete os pré-requisitos antes de enviar para assinatura.').'</div>';
        }

        $content = View::render('admin/modules/contratos/show', [
            'alerta' => $alertaShow,
            'numero' => htmlspecialchars($contrato->numero, ENT_QUOTES, 'UTF-8'),
            'status_label' => htmlspecialchars(self::statusLabel($contrato->status), ENT_QUOTES, 'UTF-8'),
            'status' => $contrato->status,
            'cliente_nome' => htmlspecialchars($contrato->cliente_nome, ENT_QUOTES, 'UTF-8'),
            'cliente_id' => $contrato->cliente_id,
            'acoes' => $acoes,
            'modais' => $modais,
            'preview_url' => URL.'/painel/contratos/'.$id.'/imprimir',
            'painel_lateral' => self::painelLateralShow($contrato, $itensCount),
            'voltar_url' => htmlspecialchars(URL.$returnPath, ENT_QUOTES, 'UTF-8'),
        ]);

        return self::getPage('Contrato '.$contrato->numero, $content, 'contratos');
    }

    public static function enviar($request, int $id): void
    {
        $post = $request->getPostVars();
        $contratoRef = ClienteContrato::getById($id);
        $clienteRefId = $contratoRef ? $contratoRef->cliente_id : 0;
        $returnPath = self::returnPathFromPost($post, $clienteRefId);
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.self::urlContrato($id, $returnPath));
            exit;
        }
        $contrato = ClienteContrato::getById($id);
        if ($contrato) {
            $cliente = Cliente::getById($contrato->cliente_id);
            if ($cliente) {
                $preErros = ContratoPreRequisitosService::errosCompletos($cliente, $contrato->plano_id);
                if ($preErros !== []) {
                    $back = self::urlContrato($id, $returnPath);
                    $sep = str_contains($back, '?') ? '&' : '?';
                    header('Location: '.$back.$sep.'erro=pre&msg='.urlencode(implode(', ', $preErros)));
                    exit;
                }
            }
        }
        ContratoClienteService::enviarParaAssinatura($id);
        header('Location: '.self::urlContrato($id, $returnPath));
        exit;
    }

    public static function cancelar($request, int $id): void
    {
        $post = $request->getPostVars();
        $contratoRef = ClienteContrato::getById($id);
        $clienteRefId = $contratoRef ? $contratoRef->cliente_id : 0;
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.self::urlContrato($id, self::returnPathFromPost($post, $clienteRefId)));
            exit;
        }
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        ContratoClienteService::cancelar($id, $usuarioId, (string)($post['motivo'] ?? ''));
        self::redirectReturn($post, $id);
    }

    public static function rescindir($request, int $id): void
    {
        $post = $request->getPostVars();
        $contratoRef = ClienteContrato::getById($id);
        $clienteRefId = $contratoRef ? $contratoRef->cliente_id : 0;
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.self::urlContrato($id, self::returnPathFromPost($post, $clienteRefId)));
            exit;
        }
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        ContratoClienteService::rescindir($id, $usuarioId, (string)($post['motivo'] ?? ''));
        self::redirectReturn($post, $id);
    }

    public static function imprimir($request, int $id): string
    {
        $contrato = ClienteContrato::getById($id);
        if (!$contrato) {
            $request->getRouter()->redirect('/painel/contratos');
        }

        return ContratoClienteService::renderHtml($id);
    }

    private static function contratosListPath(int $clienteId = 0, string $status = ''): string
    {
        $params = [];
        if ($clienteId > 0) {
            $params['cliente_id'] = $clienteId;
        }
        if ($status !== '') {
            $params['status'] = $status;
        }

        return '/painel/contratos'.($params === [] ? '' : '?'.http_build_query($params));
    }

    private static function isSafeReturnPath(string $path): bool
    {
        return str_starts_with($path, '/painel/contratos')
            && !str_contains($path, '..')
            && !str_contains($path, '//');
    }

    private static function returnPathFromRequest($request, int $defaultClienteId = 0): string
    {
        $return = trim((string)($request->getQueryParams()['return'] ?? ''));
        if ($return !== '' && self::isSafeReturnPath($return)) {
            return $return;
        }

        return self::contratosListPath($defaultClienteId);
    }

    /** @param array<string,mixed> $post */
    private static function returnPathFromPost(array $post, int $defaultClienteId = 0): string
    {
        $return = trim((string)($post['return'] ?? ''));
        if ($return !== '' && self::isSafeReturnPath($return)) {
            return $return;
        }

        return self::contratosListPath($defaultClienteId);
    }

    /** @param array<string,mixed> $post */
    private static function returnQueryFromPost(array $post, int $defaultClienteId): string
    {
        return 'return='.rawurlencode(self::returnPathFromPost($post, $defaultClienteId));
    }

    private static function urlContrato(int $contratoId, string $returnPath): string
    {
        $url = URL.'/painel/contratos/'.$contratoId;
        if ($returnPath !== '' && self::isSafeReturnPath($returnPath)) {
            $url .= '?return='.rawurlencode($returnPath);
        }

        return $url;
    }

    /** @param array<string,mixed> $post */
    private static function redirectReturn(array $post, int $contratoId): void
    {
        $contrato = ClienteContrato::getById($contratoId);
        $clienteId = $contrato ? $contrato->cliente_id : 0;
        $path = self::returnPathFromPost($post, $clienteId);
        header('Location: '.URL.$path);
        exit;
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'rascunho' => 'Rascunho',
            'aguardando_assinatura' => 'Aguardando assinatura',
            'ativo' => 'Ativo',
            'cancelado' => 'Cancelado',
            'encerrado' => 'Encerrado',
            default => $status,
        };
    }

    private static function statusBadge(string $status): string
    {
        $class = match ($status) {
            'ativo' => 'bg-success',
            'aguardando_assinatura' => 'bg-warning text-dark',
            'cancelado' => 'bg-secondary',
            'encerrado' => 'bg-dark',
            default => 'bg-light text-dark border',
        };

        return '<span class="badge '.$class.'">'.htmlspecialchars(self::statusLabel($status), ENT_QUOTES, 'UTF-8').'</span>';
    }

    private static function statusTabsHtml(string $active, int $clienteId): string
    {
        $base = URL.'/painel/contratos?';
        if ($clienteId > 0) {
            $base .= 'cliente_id='.$clienteId.'&';
        }
        $tabs = [
            '' => 'Todos',
            'rascunho' => 'Rascunhos',
            'aguardando_assinatura' => 'Aguardando assinatura',
            'ativo' => 'Ativos',
            'encerrado' => 'Encerrados',
            'cancelado' => 'Cancelados',
        ];
        $html = '<ul class="nav nav-pills flex-wrap gap-1 mb-3">';
        foreach ($tabs as $key => $label) {
            $activeClass = $active === $key ? ' active' : '';
            $html .= '<li class="nav-item"><a class="nav-link'.$activeClass.'" href="'
                .htmlspecialchars($base.'status='.rawurlencode($key), ENT_QUOTES, 'UTF-8').'">'
                .htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</a></li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private static function checklistClienteHtml(Cliente $cliente): string
    {
        $checks = [
            'Responsável' => trim($cliente->responsavel) !== '',
            'CPF do responsável' => trim($cliente->responsavel_cpf) !== '',
            'RG do responsável' => trim($cliente->responsavel_rg) !== '',
            'Cargo' => trim($cliente->responsavel_cargo) !== '',
        ];
        $html = '<ul class="list-unstyled small mb-0">';
        foreach ($checks as $label => $ok) {
            $icon = $ok ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-warning';
            $html .= '<li><i class="fas '.$icon.' me-1"></i>'
                .htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</li>';
        }
        $html .= '</ul>';
        $editUrl = URL.'/painel/clientes?editar='.$cliente->id;
        $html .= '<p class="small mt-2 mb-0"><a href="'.htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8').'">Editar cadastro do cliente</a></p>';

        return $html;
    }

    private static function painelLateralShow(ClienteContrato $contrato, int $itensCount): string
    {
        $passos = [
            'rascunho' => 'Revise valores e resíduos, depois envie para assinatura.',
            'aguardando_assinatura' => 'Aguardando o gerador assinar no portal.',
            'ativo' => 'Contrato vigente — valores congelados na assinatura.',
            'cancelado' => 'Contrato cancelado antes da assinatura.',
            'encerrado' => 'Contrato rescindido.',
        ];
        $proximo = $passos[$contrato->status] ?? '';

        $plano = Plano::getById($contrato->plano_id);
        $modeloOk = true;
        if ($plano) {
            $slug = ContractType::normalize($plano->contrato_modelo_tipo ?? ContractType::GENERICO);
            if ($slug !== ContractType::GENERICO) {
                $modeloOk = ContratoModelo::tabelaExiste()
                    && ContratoModelo::resolveAtivo($contrato->operadora_id, $slug) !== null;
            }
        }

        $competencia = date('Y-m');
        $simUrl = URL.'/painel/pagamentos/'.$contrato->cliente_id.'/detalhe?competencia='.$competencia;

        return '<div class="col-lg-4">'
            .'<div class="card shadow-sm mb-3"><div class="card-body">'
            .'<h2 class="h6">Próximo passo</h2>'
            .'<p class="small text-muted mb-0">'.htmlspecialchars($proximo, ENT_QUOTES, 'UTF-8').'</p>'
            .'</div></div>'
            .'<div class="card shadow-sm mb-3"><div class="card-body">'
            .'<h2 class="h6">Cobrança</h2>'
            .'<p class="mb-1"><strong>Mensalidade:</strong> '.FormatHelper::money($contrato->valor_mensal).'</p>'
            .'<p class="mb-1 small text-muted">Negociada neste contrato (não vem do plano após assinatura).</p>'
            .'<p class="mb-2"><strong>Tipos no acordo:</strong> '.$itensCount.'</p>'
            .($contrato->status === 'ativo'
                ? '<button type="button" class="btn btn-sm btn-outline-primary w-100" id="btn_simular_fatura" data-url="'
                .htmlspecialchars($simUrl, ENT_QUOTES, 'UTF-8').'">Simular fatura do mês</button>'
                .'<pre class="small bg-light p-2 mt-2 d-none" id="simular_fatura_out"></pre>'
                : '<p class="small text-muted mb-0">Simulação disponível após assinatura.</p>')
            .'</div></div>'
            .(!$modeloOk
                ? '<div class="alert alert-warning small">Modelo jurídico não encontrado no banco. Execute o seed de modelos ou use plano genérico.</div>'
                : '')
            .'</div>';
    }
}
