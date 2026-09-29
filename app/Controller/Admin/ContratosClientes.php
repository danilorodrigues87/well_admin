<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CsrfHelper;
use App\Common\Helpers\FormatHelper;
use App\Model\Entity\Cliente;
use App\Model\Entity\ClienteContrato;
use App\Model\Entity\Plano;
use App\Service\ContratoClienteService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class ContratosClientes extends Page
{
    public static function index($request): string
    {
        $clienteId = (int)($request->getQueryParams()['cliente_id'] ?? 0);

        $clientesOpts = '';
        $clientesOptsNovo = '<option value="">Selecione o cliente…</option>';
        foreach (Cliente::list("c.status = 'ativo'", [], '500') as $cl) {
            $nome = htmlspecialchars($cl->nome_fantasia, ENT_QUOTES, 'UTF-8');
            $sel = $clienteId === $cl->id ? ' selected' : '';
            $clientesOpts .= '<option value="'.$cl->id.'"'.$sel.'>'.$nome.'</option>';
            $clientesOptsNovo .= '<option value="'.$cl->id.'">'.$nome.'</option>';
        }

        $rows = '';
        foreach (ClienteContrato::listAll($clienteId > 0 ? $clienteId : null) as $c) {
            $rows .= '<tr>'
                .'<td>'.htmlspecialchars($c->numero, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.htmlspecialchars($c->cliente_nome, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.htmlspecialchars($c->plano_nome, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.FormatHelper::money($c->valor_mensal).'</td>'
                .'<td>'.FormatHelper::dateBr($c->data_inicio).' — '.FormatHelper::dateBr($c->data_fim).'</td>'
                .'<td>'.self::statusBadge($c->status).'</td>'
                .'<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="'.URL.'/painel/contratos/'.$c->id.'">Abrir</a></td>'
                .'</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="text-center text-muted py-4">'
                .'Nenhum contrato encontrado. Selecione um cliente acima e clique em <strong>Criar</strong>, '
                .'ou use o ícone de contrato na listagem de Clientes.'
                .'</td></tr>';
        }

        $content = View::render('admin/modules/contratos/index', [
            'clientes_options' => $clientesOpts,
            'clientes_options_novo' => $clientesOptsNovo,
            'rows' => $rows,
        ]);

        return self::getPage('Contratos', $content, 'contratos');
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
                .'<td><a class="btn btn-sm btn-outline-primary" href="'.URL.'/painel/contratos/'.$c->id.'">Ver</a></td>'
                .'</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="4" class="text-muted text-center">Nenhum contrato.</td></tr>';
        }

        $content = View::render('admin/modules/contratos/lista_cliente', [
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'cliente_id' => $clienteId,
            'rows' => $rows,
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
            $planosOpts .= '<option value="'.$p->id.'" data-valor="'.$p->valor_mensal.'"'.$sel.'>'
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
        } elseif ($planos === []) {
            $alerta = '<div class="alert alert-warning">'
                .'Não há planos ativos. Cadastre ao menos um em <a href="'.URL.'/painel/planos">Planos</a> antes de gerar contratos.'
                .'</div>';
        }

        $content = View::render('admin/modules/contratos/form', [
            'cliente_id' => $clienteId,
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'planos_options' => $planosOpts,
            'data_inicio' => date('Y-m-d'),
            'primeira_competencia' => date('Y-m'),
            'csrf_field' => CsrfHelper::field(),
            'alerta' => $alerta,
            'sem_planos' => $planos === [] ? '1' : '0',
        ]);

        return self::getPage('Novo contrato', $content, 'contratos');
    }

    public static function salvar($request, int $clienteId): void
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/clientes/'.$clienteId.'/contrato/novo?erro=csrf');
            exit;
        }
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);

        $valorPost = trim((string)($post['valor_mensal'] ?? ''));

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
            'status' => 'rascunho',
        ]);

        if ($id <= 0) {
            header('Location: '.URL.'/painel/clientes/'.$clienteId.'/contrato/novo?erro=1');
            exit;
        }

        ContratoClienteService::atualizarPersonalizacao($id, [
            'frequencia_coleta' => trim((string)($post['frequencia_coleta'] ?? '')),
            'promocao_html' => (string)($post['promocao_html'] ?? ''),
            'clausulas_extra_html' => (string)($post['clausulas_extra_html'] ?? ''),
            'atualizar_snapshot' => true,
        ]);

        if (!empty($post['enviar_assinatura'])) {
            ContratoClienteService::enviarParaAssinatura($id);
        }

        header('Location: '.URL.'/painel/contratos/'.$id);
        exit;
    }

    public static function editar($request, int $id): string
    {
        $contrato = ClienteContrato::getById($id);
        if (!$contrato || !in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            $request->getRouter()->redirect('/painel/contratos/'.$id);
        }

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

        $content = View::render('admin/modules/contratos/editar', [
            'contrato_id' => $id,
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
            'csrf_field' => CsrfHelper::field(),
        ]);

        return self::getPage('Editar contrato', $content, 'contratos');
    }

    public static function salvarEdicao($request, int $id): void
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/contratos/'.$id.'/editar?erro=csrf');
            exit;
        }
        if (!empty($post['recarregar_snapshot'])) {
            ContratoClienteService::recarregarSnapshotPlano($id);
            header('Location: '.URL.'/painel/contratos/'.$id.'/editar?ok=snapshot');
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
            'atualizar_snapshot' => !empty($post['atualizar_snapshot']),
        ]);

        header('Location: '.URL.'/painel/contratos/'.($ok ? $id : $id.'/editar?erro=1'));
        exit;
    }

    public static function show($request, int $id): string
    {
        $contrato = ClienteContrato::getById($id);
        if (!$contrato) {
            $request->getRouter()->redirect('/painel/contratos');
        }

        $acoes = '';
        if (in_array($contrato->status, ['rascunho', 'aguardando_assinatura'], true)) {
            $acoes .= '<a href="'.URL.'/painel/contratos/'.$id.'/editar" class="btn btn-outline-secondary me-2">Personalizar</a>';
        }
        if ($contrato->status === 'rascunho') {
            $acoes .= '<form method="post" action="'.URL.'/painel/contratos/'.$id.'/enviar" class="d-inline">'
                .CsrfHelper::field()
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

        $content = View::render('admin/modules/contratos/show', [
            'numero' => htmlspecialchars($contrato->numero, ENT_QUOTES, 'UTF-8'),
            'status_label' => htmlspecialchars(self::statusLabel($contrato->status), ENT_QUOTES, 'UTF-8'),
            'cliente_nome' => htmlspecialchars($contrato->cliente_nome, ENT_QUOTES, 'UTF-8'),
            'cliente_id' => $contrato->cliente_id,
            'acoes' => $acoes,
            'modais' => $modais,
            'preview_url' => URL.'/painel/contratos/'.$id.'/imprimir',
        ]);

        return self::getPage('Contrato '.$contrato->numero, $content, 'contratos');
    }

    public static function enviar($request, int $id): void
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/contratos/'.$id);
            exit;
        }
        ContratoClienteService::enviarParaAssinatura($id);
        header('Location: '.URL.'/painel/contratos/'.$id);
        exit;
    }

    public static function cancelar($request, int $id): void
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/contratos/'.$id);
            exit;
        }
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        ContratoClienteService::cancelar($id, $usuarioId, (string)($post['motivo'] ?? ''));
        header('Location: '.URL.'/painel/contratos/'.$id);
        exit;
    }

    public static function rescindir($request, int $id): void
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/contratos/'.$id);
            exit;
        }
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        ContratoClienteService::rescindir($id, $usuarioId, (string)($post['motivo'] ?? ''));
        header('Location: '.URL.'/painel/contratos/'.$id);
        exit;
    }

    public static function imprimir($request, int $id): string
    {
        $contrato = ClienteContrato::getById($id);
        if (!$contrato) {
            $request->getRouter()->redirect('/painel/contratos');
        }

        return ContratoClienteService::renderHtml($id);
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
}
