<?php

namespace App\Controller\Admin;

use App\Common\ColetaDefaults;
use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Veiculo as EntityVeiculo;
use App\Service\ColetaService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class ColetaNova extends Page
{
    private static function sessionUser(): array
    {
        return SessionUser::getUserLogedData()['usuario'] ?? [];
    }

    public static function selecionarCliente($request): string
    {
        $content = View::render('admin/modules/coleta_nova/selecionar', [
            'csrf_field' => CsrfHelper::field(),
        ]);
        $scripts = '<script>window.CRUD = { baseUrl: "/painel/coleta/nova", autoLoad: false };</script>'
            .'<script src="'.URL.'/resources/js/coleta-selecionar.js"></script>';

        return self::getPage('Lançar Coleta', $content, 'coleta_nova', $scripts);
    }

    public static function wizard($request, int $coletaId): string
    {
        $usuario = self::sessionUser();
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'rascunho') {
            return self::getPage('Lançar Coleta', '<div class="alert alert-warning">Coleta não encontrada ou já finalizada.</div>', 'coleta_nova');
        }
        if ((int)$coleta->coletor_id !== (int)($usuario['id'] ?? 0) && empty($usuario['is_admin'])) {
            return self::getPage('Lançar Coleta', '<div class="alert alert-danger">Sem permissão para esta coleta.</div>', 'coleta_nova');
        }

        $snapshot = EntityColetaSnapshot::getByColetaId($coletaId);
        $itens = EntityColetaItem::getByColetaId($coletaId);

        $veiculosOptions = '<option value="">— Selecione —</option>';
        foreach (EntityVeiculo::list('ativo = 1', [], '999') as $v) {
            $sel = $coleta->veiculo_id === $v->id ? ' selected' : '';
            $veiculosOptions .= '<option value="'.$v->id.'"'.$sel.'>'.CrudHelper::e($v->marca.' '.$v->modelo.' — '.$v->placa).'</option>';
        }

        $tiposOptions = '<option value="">— Selecione —</option>';
        foreach (EntityTipoResiduo::list('t.ativo = 1', [], '999') as $t) {
            $tiposOptions .= '<option value="'.$t->id.'">'.CrudHelper::e($t->nome).' ('.CrudHelper::e($t->classe_nome).' / '.$t->grupo_codigo.')</option>';
        }

        $tratamentosOptions = '';
        foreach (ColetaDefaults::tratamentos() as $tr) {
            $sel = $coleta->tratamento === $tr ? ' selected' : '';
            $tratamentosOptions .= '<option value="'.CrudHelper::e($tr).'"'.$sel.'>'.CrudHelper::e($tr).'</option>';
        }

        $itensHtml = self::renderItens($itens);

        $content = View::render('admin/modules/coleta_nova/wizard', [
            'csrf_field' => CsrfHelper::field(),
            'coleta_id' => $coletaId,
            'cliente_nome' => CrudHelper::e($snapshot->gerador_nome_fantasia ?? $coleta->cliente_nome),
            'gerador_endereco' => CrudHelper::e($snapshot->gerador_endereco ?? ''),
            'transportador_nome' => CrudHelper::e($snapshot->transportador_nome ?? ''),
            'transportador_cnpj' => CrudHelper::e($snapshot->transportador_cnpj ?? ''),
            'motorista_nome' => CrudHelper::e($snapshot->motorista_nome ?? ''),
            'destinador_nome' => CrudHelper::e($snapshot->destinador_nome ?? ''),
            'destinador_cnpj' => CrudHelper::e($snapshot->destinador_cnpj ?? ''),
            'destinador_endereco' => CrudHelper::e($snapshot->destinador_endereco ?? ''),
            'destinador_telefone' => CrudHelper::e($snapshot->destinador_telefone ?? ''),
            'destinador_responsavel' => CrudHelper::e($snapshot->destinador_responsavel ?? ''),
            'observacao_destinador' => CrudHelper::e($snapshot->observacao_destinador ?? ''),
            'relatorio' => CrudHelper::e($coleta->relatorio ?? ''),
            'veiculos_options' => $veiculosOptions,
            'tipos_options' => $tiposOptions,
            'tratamentos_options' => $tratamentosOptions,
            'itens_html' => $itensHtml,
            'situacao_recebido' => $coleta->situacao_recebimento === 'recebido' ? 'checked' : '',
            'data_recebimento' => $coleta->data_recebimento ?? '',
        ]);

        $scripts = self::crudScripts('/painel/coleta/nova/'.$coletaId, false)
            . '<script src="'.URL.'/resources/js/coleta-wizard.js"></script>';

        return self::getPage('Coleta #'.$coletaId, $content, 'coleta_nova', $scripts);
    }

    /** @param EntityColetaItem[] $itens */
    private static function renderItens(array $itens): string
    {
        if (empty($itens)) {
            return '<tr><td colspan="5" class="text-center text-muted">Nenhum resíduo adicionado.</td></tr>';
        }
        $html = '';
        foreach ($itens as $i) {
            $html .= '<tr>
                <td>'.CrudHelper::e($i->nome).'</td>
                <td>'.CrudHelper::e($i->classe_nome).'</td>
                <td>'.CrudHelper::e($i->grupo_codigo).'</td>
                <td>'.number_format($i->quantidade, 3, ',', '.').' '.$i->unidade.'</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removerItem('.$i->id.')"><i class="fas fa-trash"></i></button></td>
            </tr>';
        }
        return $html;
    }

    public static function post($request, ?int $coletaId = null): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $acao = (string)($post['acao'] ?? '');
        $usuario = self::sessionUser();

        try {
            return match ($acao) {
                'listar_clientes' => self::acaoListarClientes($post, $usuario),
                'iniciar' => self::acaoIniciar($post, $usuario),
                'salvar_transporte' => self::acaoSalvarTransporte($coletaId, $post),
                'adicionar_item' => self::acaoAdicionarItem($coletaId, $post),
                'remover_item' => self::acaoRemoverItem($coletaId, $post),
                'listar_itens' => self::acaoListarItens($coletaId),
                'finalizar' => self::acaoFinalizar($request, $coletaId),
                'cancelar' => self::acaoCancelar($coletaId),
                default => CrudHelper::jsonError('Ação inválida.'),
            };
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        } catch (\Throwable $e) {
            error_log('[ColetaNova] '.$e->getMessage());
            return CrudHelper::jsonError('Erro ao processar coleta.');
        }
    }

    private static function acaoListarClientes(array $post, array $usuario): string
    {
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $prioridade = trim((string)($post['prioridade'] ?? ''));
        $escopo = trim((string)($post['escopo'] ?? 'pendentes'));
        $somentePendentes = $escopo !== 'todos';

        $result = ColetaService::clientesParaColetaPaginado(
            (int)($usuario['id'] ?? 0),
            !empty($usuario['is_admin']),
            $page,
            15,
            $busca,
            $prioridade,
            $somentePendentes
        );

        $itens = '';
        foreach ($result['items'] as $c) {
            $urgente = $c->prioridade === 'urgente' ? ' <span class="badge bg-danger">Urgente</span>' : '';
            $data = $c->proxima_coleta ? date('d/m/Y', strtotime($c->proxima_coleta)) : '—';
            $itens .= '<tr>
                <td>'.CrudHelper::e($c->nome_fantasia).$urgente.'</td>
                <td>'.CrudHelper::e($c->cidade).'/'.$c->uf.'</td>
                <td>'.$data.'</td>
                <td>'.CrudHelper::e($c->plano_nome).'</td>
                <td><button class="btn btn-sm btn-primary" onclick="iniciarColeta('.$c->id.')"><i class="fas fa-truck"></i> Coletar</button></td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="5" class="text-center text-muted py-4">Nenhum cliente encontrado.</td></tr>';
        }

        return Page::jsonLista([
            'success' => true,
            'itens' => $itens,
            'pagination' => Pagination::renderNav($result['pagination']),
        ]);
    }

    private static function acaoIniciar(array $post, array $usuario): string
    {
        $clienteId = (int)($post['cliente_id'] ?? 0);
        $coletaId = ColetaService::iniciarRascunho($clienteId, (int)($usuario['id'] ?? 0));
        return CrudHelper::jsonOk(['coleta_id' => $coletaId, 'redirect' => URL.'/painel/coleta/nova/'.$coletaId]);
    }

    private static function acaoSalvarTransporte(?int $coletaId, array $post): string
    {
        if (!$coletaId) {
            return CrudHelper::jsonError('Coleta inválida.');
        }
        ColetaService::salvarTransporte($coletaId, $post);
        return CrudHelper::jsonOk(['message' => 'Dados salvos.']);
    }

    private static function acaoAdicionarItem(?int $coletaId, array $post): string
    {
        if (!$coletaId) {
            return CrudHelper::jsonError('Coleta inválida.');
        }
        ColetaService::adicionarItem(
            $coletaId,
            (int)($post['tipo_residuo_id'] ?? 0),
            (float)str_replace(',', '.', (string)($post['quantidade'] ?? 0)),
            (string)($post['unidade'] ?? 'kg')
        );
        return CrudHelper::jsonOk(['itens_html' => self::renderItens(EntityColetaItem::getByColetaId($coletaId))]);
    }

    private static function acaoRemoverItem(?int $coletaId, array $post): string
    {
        if (!$coletaId) {
            return CrudHelper::jsonError('Coleta inválida.');
        }
        ColetaService::removerItem($coletaId, (int)($post['item_id'] ?? 0));
        return CrudHelper::jsonOk(['itens_html' => self::renderItens(EntityColetaItem::getByColetaId($coletaId))]);
    }

    private static function acaoListarItens(?int $coletaId): string
    {
        if (!$coletaId) {
            return CrudHelper::jsonError('Coleta inválida.');
        }
        return CrudHelper::jsonOk(['itens_html' => self::renderItens(EntityColetaItem::getByColetaId($coletaId))]);
    }

    private static function acaoFinalizar($request, ?int $coletaId): string
    {
        if (!$coletaId) {
            return CrudHelper::jsonError('Coleta inválida.');
        }

        $files = [];
        foreach (['evidencia_1', 'evidencia_2', 'evidencia_3'] as $key) {
            if (!empty($_FILES[$key]['tmp_name'])) {
                $files[] = $_FILES[$key];
            }
        }

        $numeroMtr = ColetaService::finalizar($coletaId, $files);
        return CrudHelper::jsonOk([
            'message' => 'Coleta finalizada! MTR nº '.$numeroMtr,
            'numero_mtr' => $numeroMtr,
            'redirect' => URL.'/painel/coletas',
        ]);
    }

    private static function acaoCancelar(?int $coletaId): string
    {
        if (!$coletaId) {
            return CrudHelper::jsonError('Coleta inválida.');
        }
        ColetaService::cancelar($coletaId);
        return CrudHelper::jsonOk(['redirect' => URL.'/painel/coleta/nova']);
    }
}
