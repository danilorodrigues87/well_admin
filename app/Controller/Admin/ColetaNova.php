<?php

namespace App\Controller\Admin;

use App\Common\ColetaDefaults;
use App\Common\Helpers\ColetaMtrHelper;
use App\Common\Helpers\ColetorSelectHelper;
use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Transportadora as EntityTransportadora;
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

        $tiposOptions = self::tiposResiduoOptionsHtml();
        $motoristaColetorId = ColetorSelectHelper::resolveSelectedId(
            $snapshot->motorista_nome ?? '',
            (int)$coleta->coletor_id
        );
        $motoristaLocked = ColetorSelectHelper::isColetorSession($usuario);
        if ($motoristaLocked) {
            $motoristaColetorId = (int)($usuario['id'] ?? 0);
        }
        $transportadoraId = (int)($coleta->transportadora_id ?? 0);
        if ($transportadoraId <= 0) {
            $padrao = EntityTransportadora::getPadrao();
            $transportadoraId = $padrao ? $padrao->id : 0;
        }
        $motoristaOptions = ColetorSelectHelper::optionsHtml($motoristaColetorId, !$motoristaLocked, $transportadoraId > 0 ? $transportadoraId : null);

        $tratamentosOptions = '';
        foreach (ColetaDefaults::tratamentos() as $tr) {
            $sel = $coleta->tratamento === $tr ? ' selected' : '';
            $tratamentosOptions .= '<option value="'.CrudHelper::e($tr).'"'.$sel.'>'.CrudHelper::e($tr).'</option>';
        }

        $itensHtml = self::renderItens($itens);
        $evidencias = EntityColetaEvidencia::getByColetaId($coletaId);
        $rascunhoConferido = ColetaService::rascunhoFinalConferido($coletaId);

        $btnImprimir = ColetaMtrHelper::podeImprimirRelatorio($coleta)
            ? '<a class="btn btn-outline-secondary" href="'.URL.'/painel/coletas/mtr/'.$coletaId.'" target="_blank" rel="noopener">'
                .'<i class="fas fa-print me-1"></i> Imprimir relatório</a>'
            : '';

        $content = View::render('admin/modules/coleta_nova/wizard', [
            'csrf_field' => CsrfHelper::field(),
            'btn_imprimir_relatorio' => $btnImprimir,
            'coleta_id' => $coletaId,
            'cliente_nome' => CrudHelper::e($snapshot->gerador_nome_fantasia ?? $coleta->cliente_nome),
            'gerador_endereco' => CrudHelper::e($snapshot->gerador_endereco ?? ''),
            'transportadoras_options' => self::transportadorasOptionsHtml((int)($coleta->transportadora_id ?? 0)),
            'destinadores_options' => self::destinadoresOptionsHtml((int)($coleta->destinador_id ?? 0)),
            'motorista_coletor_id' => $motoristaColetorId,
            'motorista_options' => $motoristaOptions,
            'motorista_locked' => $motoristaLocked ? 'disabled' : '',
            'motorista_hidden' => $motoristaLocked
                ? '<input type="hidden" name="motorista_coletor_id" value="'.$motoristaColetorId.'"/>'
                : '',
            'observacao_destinador' => CrudHelper::e($snapshot->observacao_destinador ?? ''),
            'assinatura_preview' => self::renderAssinaturaPreview($coleta),
            'relatorio' => CrudHelper::e($coleta->relatorio ?? ''),
            'veiculos_options' => $veiculosOptions,
            'tipos_options' => $tiposOptions,
            'tratamentos_options' => $tratamentosOptions,
            'itens_html' => $itensHtml,
            'itens_count' => count($itens),
            'motorista_nome' => CrudHelper::e($snapshot->motorista_nome ?? ''),
            'evidencias_html' => self::renderEvidenciasHtml($evidencias),
            'rascunho_conferido' => $rascunhoConferido ? '1' : '0',
            'rascunho_resumo_class' => $rascunhoConferido ? '' : 'd-none',
            'btn_gerar_disabled' => $rascunhoConferido ? '' : 'disabled',
            'resumo_rascunho_html' => $rascunhoConferido
                ? self::renderResumoRascunho($itens, $evidencias, $snapshot->motorista_nome ?? '')
                : '',
            'situacao_recebido' => $coleta->situacao_recebimento === 'recebido' ? 'checked' : '',
            'data_recebimento' => $coleta->data_recebimento ?? '',
        ]);

        $scripts = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/css/tom-select.bootstrap5.min.css">'
            . '<link rel="stylesheet" href="'.URL.'/resources/css/tom-select-well.css?v=20260916">'
            . self::crudScripts('/painel/coleta/nova/'.$coletaId, false)
            . '<script>window.COLETA_WIZARD = { rascunhoConferido: '.($rascunhoConferido ? 'true' : 'false').' };</script>'
            . '<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.1/dist/js/tom-select.complete.min.js"></script>'
            . '<script src="'.URL.'/resources/js/coleta-wizard.js?v=20260922"></script>';

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
                'salvar_transporte', 'salvar_etapa_transporte' => self::acaoSalvarTransporte($coletaId, $post, $usuario),
                'salvar_destinador', 'salvar_etapa_destinador' => self::acaoSalvarDestinador($coletaId, $post, $usuario),
                'salvar_assinatura' => self::acaoSalvarAssinatura($coletaId, $post, $usuario),
                'coletores_transportadora' => self::acaoColetoresTransportadora($post),
                'adicionar_item' => self::acaoAdicionarItem($coletaId, $post, $usuario),
                'remover_item' => self::acaoRemoverItem($coletaId, $post, $usuario),
                'listar_itens' => self::acaoListarItens($coletaId, $usuario),
                'salvar_rascunho_final' => self::acaoSalvarRascunhoFinal($request, $coletaId, $usuario),
                'finalizar' => self::acaoFinalizar($request, $coletaId, $usuario),
                'cancelar' => self::acaoCancelar($coletaId, $usuario),
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
        $coletaId = ColetaService::iniciarRascunho(
            $clienteId,
            (int)($usuario['id'] ?? 0),
            !empty($usuario['is_admin'])
        );
        return CrudHelper::jsonOk(['coleta_id' => $coletaId, 'redirect' => URL.'/painel/coleta/nova/'.$coletaId]);
    }

    private static function tiposResiduoOptionsHtml(): string
    {
        $html = '<option value="">— Selecione —</option>';
        foreach (EntityTipoResiduo::list('t.ativo = 1', [], '999') as $t) {
            $label = $t->nome.' ('.$t->classe_nome.' / '.$t->grupo_codigo.')';
            $html .= '<option value="'.$t->id.'">'.CrudHelper::e($label).'</option>';
        }

        return $html;
    }

    private static function assertColetaAccess(?int $coletaId, array $usuario): void
    {
        if (!$coletaId) {
            throw new \InvalidArgumentException('Coleta inválida.');
        }
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'rascunho') {
            throw new \InvalidArgumentException('Coleta não encontrada ou já finalizada.');
        }
        if ((int)$coleta->coletor_id !== (int)($usuario['id'] ?? 0) && empty($usuario['is_admin'])) {
            throw new \InvalidArgumentException('Sem permissão para esta coleta.');
        }
    }

    private static function resolveMotoristaPost(array $post, array $usuario): string
    {
        $motoristaId = (int)($post['motorista_coletor_id'] ?? 0);
        if (ColetorSelectHelper::isColetorSession($usuario)) {
            $motoristaId = (int)($usuario['id'] ?? 0);
        }
        if (!ColetorSelectHelper::isColetorAtivo($motoristaId)) {
            throw new \InvalidArgumentException('Selecione um coletor válido como motorista.');
        }

        return ColetorSelectHelper::nomeById($motoristaId);
    }

    private static function acaoSalvarTransporte(?int $coletaId, array $post, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);
        $motoristaId = (int)($post['motorista_coletor_id'] ?? 0);
        if (ColetorSelectHelper::isColetorSession($usuario)) {
            $motoristaId = (int)($usuario['id'] ?? 0);
        }
        $post['motorista_nome'] = self::resolveMotoristaPost($post, $usuario);
        $post['coletor_id'] = $motoristaId;
        ColetaService::salvarEtapaTransporte($coletaId, $post);

        return CrudHelper::jsonOk(['message' => 'Transportadora e coletor salvos.']);
    }

    private static function acaoSalvarDestinador(?int $coletaId, array $post, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);
        ColetaService::salvarEtapaDestinador($coletaId, $post);

        return CrudHelper::jsonOk(['message' => 'Destinador salvo.']);
    }

    private static function acaoSalvarAssinatura(?int $coletaId, array $post, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);
        $dataUrl = (string)($post['assinatura_data_url'] ?? '');
        ColetaService::salvarAssinaturaCliente($coletaId, $dataUrl);
        $coleta = EntityColeta::getById($coletaId);

        return CrudHelper::jsonOk([
            'message' => 'Assinatura salva.',
            'preview_html' => $coleta ? self::renderAssinaturaPreview($coleta) : '',
        ]);
    }

    private static function acaoColetoresTransportadora(array $post): string
    {
        $transportadoraId = (int)($post['transportadora_id'] ?? 0);
        $selected = (int)($post['selected_id'] ?? 0);
        $html = ColetorSelectHelper::optionsHtml($selected, true, $transportadoraId > 0 ? $transportadoraId : null);

        return CrudHelper::jsonOk(['options_html' => $html]);
    }

    private static function transportadorasOptionsHtml(int $selectedId): string
    {
        $html = '';
        foreach (EntityTransportadora::listAtivas() as $t) {
            $sel = $t->id === $selectedId ? ' selected' : '';
            $html .= '<option value="'.$t->id.'"'.$sel.'>'.CrudHelper::e($t->nome).'</option>';
        }

        return $html !== '' ? $html : '<option value="">— Cadastre transportadoras —</option>';
    }

    private static function destinadoresOptionsHtml(int $selectedId): string
    {
        $html = '';
        foreach (EntityDestinador::listAtivos() as $d) {
            $sel = $d->id === $selectedId ? ' selected' : '';
            $html .= '<option value="'.$d->id.'"'.$sel.'>'.CrudHelper::e($d->nome).'</option>';
        }

        return $html !== '' ? $html : '<option value="">— Cadastre destinadores —</option>';
    }

    private static function renderAssinaturaPreview(EntityColeta $coleta): string
    {
        if (!$coleta->assinatura_cliente_path) {
            return '';
        }
        $parts = explode('/', ltrim($coleta->assinatura_cliente_path, '/'));
        $arquivo = end($parts);
        $url = URL.'/storage/coletas/'.$coleta->id.'/'.rawurlencode($arquivo);

        return '<p class="small text-success mt-2 mb-0">Assinatura registrada:</p>'
            .'<img src="'.CrudHelper::e($url).'" alt="Assinatura" class="img-fluid border mt-1" style="max-width:320px"/>';
    }

    private static function acaoAdicionarItem(?int $coletaId, array $post, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);
        ColetaService::adicionarItem(
            $coletaId,
            (int)($post['tipo_residuo_id'] ?? 0),
            (float)str_replace(',', '.', (string)($post['quantidade'] ?? 0)),
            (string)($post['unidade'] ?? 'kg')
        );
        return CrudHelper::jsonOk(['itens_html' => self::renderItens(EntityColetaItem::getByColetaId($coletaId))]);
    }

    private static function acaoRemoverItem(?int $coletaId, array $post, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);
        ColetaService::removerItem($coletaId, (int)($post['item_id'] ?? 0));

        return CrudHelper::jsonOk(['itens_html' => self::renderItens(EntityColetaItem::getByColetaId($coletaId))]);
    }

    private static function acaoListarItens(?int $coletaId, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);

        return CrudHelper::jsonOk(['itens_html' => self::renderItens(EntityColetaItem::getByColetaId($coletaId))]);
    }

    private static function acaoSalvarRascunhoFinal($request, ?int $coletaId, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);

        $post = $request->getPostVars();
        $relatorio = trim((string)($post['relatorio'] ?? ''));
        $filesByKey = self::collectEvidenciaFiles();

        $resumo = ColetaService::salvarRascunhoFinal($coletaId, $relatorio, $filesByKey);
        $itens = EntityColetaItem::getByColetaId($coletaId);
        $evidencias = EntityColetaEvidencia::getByColetaId($coletaId);

        return CrudHelper::jsonOk([
            'message' => 'Rascunho salvo. Confira o resumo e finalize a coleta.',
            'evidencias_html' => self::renderEvidenciasHtml($evidencias),
            'resumo_html' => self::renderResumoRascunho($itens, $evidencias, $resumo['motorista']),
            'rascunho_conferido' => true,
        ]);
    }

    private static function acaoFinalizar($request, ?int $coletaId, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);

        $files = self::collectEvidenciaFilesList();

        $result = ColetaService::finalizar($coletaId, $files);
        $numeroRel = $result['numero_relatorio'];
        $msg = 'Relatório nº '.$numeroRel.' concluído. '
            .(\App\Common\SinirConfig::isEnabled()
                ? 'Use "Gerar MTR" em Coletas quando for registrar no SINIR.'
                : 'Impressão disponível em Coletas.');

        return CrudHelper::jsonOk([
            'message' => $msg,
            'numero_relatorio' => $numeroRel,
            'redirect' => URL.'/painel/coletas',
        ]);
    }

    private static function acaoCancelar(?int $coletaId, array $usuario): string
    {
        self::assertColetaAccess($coletaId, $usuario);
        ColetaService::cancelar($coletaId);
        return CrudHelper::jsonOk(['redirect' => URL.'/painel/coleta/nova']);
    }

    /** @return array<string, array<string, mixed>> */
    private static function collectEvidenciaFiles(): array
    {
        $files = [];
        foreach (['evidencia_1', 'evidencia_2', 'evidencia_3'] as $key) {
            if (!empty($_FILES[$key]['tmp_name'])) {
                $files[$key] = $_FILES[$key];
            }
        }

        return $files;
    }

    /** @return list<array<string, mixed>> */
    private static function collectEvidenciaFilesList(): array
    {
        return array_values(self::collectEvidenciaFiles());
    }

    /** @param EntityColetaEvidencia[] $evidencias */
    private static function renderEvidenciasHtml(array $evidencias): string
    {
        if ($evidencias === []) {
            return '<p class="text-muted small mb-0">Nenhuma foto salva ainda.</p>';
        }

        $html = '<div class="row g-2">';
        foreach ($evidencias as $e) {
            $url = URL.'/storage/coletas/'.$e->coleta_id.'/'.basename($e->arquivo);
            $html .= '<div class="col-md-4"><a href="'.$url.'" target="_blank">'
                .'<img src="'.$url.'" class="img-fluid rounded border" alt="Evidência '.$e->ordem.'"/></a></div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param EntityColetaItem[] $itens
     * @param EntityColetaEvidencia[] $evidencias
     */
    private static function renderResumoRascunho(array $itens, array $evidencias, string $motorista): string
    {
        $html = '<ul class="mb-0 small">';
        $html .= '<li><strong>Resíduos:</strong> '.count($itens).' item(ns)</li>';
        $html .= '<li><strong>Motorista:</strong> '.CrudHelper::e($motorista).'</li>';
        $html .= '<li><strong>Fotos salvas:</strong> '.count($evidencias).'</li>';
        $html .= '</ul>';

        return $html;
    }
}
