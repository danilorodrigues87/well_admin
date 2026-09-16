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
        foreach (Cliente::list('c.ativo = 1', [], '500') as $cl) {
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
            'qtd_meses' => (int)($post['qtd_meses'] ?? 12),
            'data_inicio' => (string)($post['data_inicio'] ?? date('Y-m-d')),
            'dia_vencimento' => (int)($post['dia_vencimento'] ?? 10),
            'primeira_competencia' => (string)($post['primeira_competencia'] ?? date('Y-m')),
            'status' => 'rascunho',
        ]);

        if ($id <= 0) {
            header('Location: '.URL.'/painel/clientes/'.$clienteId.'/contrato/novo?erro=1');
            exit;
        }

        if (!empty($post['enviar_assinatura'])) {
            ContratoClienteService::enviarParaAssinatura($id);
        }

        header('Location: '.URL.'/painel/contratos/'.$id);
        exit;
    }

    public static function show($request, int $id): string
    {
        $contrato = ClienteContrato::getById($id);
        if (!$contrato) {
            $request->getRouter()->redirect('/painel/contratos');
        }

        $acoes = '';
        if ($contrato->status === 'rascunho') {
            $acoes = '<form method="post" action="'.URL.'/painel/contratos/'.$id.'/enviar" class="d-inline">'
                .CsrfHelper::field()
                .'<button type="submit" class="btn btn-primary">Enviar para assinatura</button></form>';
        }

        $content = View::render('admin/modules/contratos/show', [
            'numero' => htmlspecialchars($contrato->numero, ENT_QUOTES, 'UTF-8'),
            'status_label' => htmlspecialchars(self::statusLabel($contrato->status), ENT_QUOTES, 'UTF-8'),
            'cliente_nome' => htmlspecialchars($contrato->cliente_nome, ENT_QUOTES, 'UTF-8'),
            'cliente_id' => $contrato->cliente_id,
            'acoes' => $acoes,
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
            default => $status,
        };
    }

    private static function statusBadge(string $status): string
    {
        $class = match ($status) {
            'ativo' => 'bg-success',
            'aguardando_assinatura' => 'bg-warning text-dark',
            'cancelado' => 'bg-secondary',
            default => 'bg-light text-dark border',
        };

        return '<span class="badge '.$class.'">'.htmlspecialchars(self::statusLabel($status), ENT_QUOTES, 'UTF-8').'</span>';
    }
}
