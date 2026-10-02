<?php

namespace App\Controller\Admin;

use App\Common\Helpers\ColetaMtrHelper;
use App\Common\Helpers\CrudHelper;
use App\Model\Db\Pagination;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaItem as EntityColetaItem;
use App\Model\Entity\ColetaSnapshot as EntityColetaSnapshot;
use App\Model\Entity\ColetaEvidencia as EntityColetaEvidencia;
use App\Service\ColetaCdfService;
use App\Service\ColetaService;
use App\Service\RotaScopeService;
use App\Service\Sinir\ColetaSinirPrecheckService;
use App\Service\Sinir\SinirService;
use App\Session\User\Login as SessionUser;
use App\Model\Entity\SinirEnvio as EntitySinirEnvio;
use App\Common\SinirConfig;
use App\Http\Response;
use App\Utils\View;

class Coletas extends Page
{
    public static function index($request): string
    {
        $content = View::render('admin/modules/coletas/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'data_inicio' => date('Y-m-01'),
            'data_fim' => date('Y-m-t'),
        ]);
        return self::getPage(
            'Coletas',
            $content,
            'coletas',
            self::crudScripts('/painel/coletas').'<script src="'.URL.'/resources/js/coletas-sinir.js?v=20261002a"></script>'
        );
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        $page = (int)($post['page'] ?? 1);
        $busca = trim((string)($post['busca'] ?? ''));
        $status = trim((string)($post['status'] ?? ''));
        $recebimento = trim((string)($post['situacao_recebimento'] ?? ''));
        $dataInicio = trim((string)($post['data_inicio'] ?? ''));
        $dataFim = trim((string)($post['data_fim'] ?? ''));

        $where = '1=1';
        $params = [];
        if ($dataInicio !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicio)) {
            $where .= ' AND c.data_coleta >= ?';
            $params[] = $dataInicio;
        }
        if ($dataFim !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFim)) {
            $where .= ' AND c.data_coleta <= ?';
            $params[] = $dataFim;
        }
        if ($busca !== '') {
            if (ctype_digit($busca)) {
                $n = (int)$busca;
                $where .= ' AND (c.numero_relatorio = ? OR c.numero_mtr = ?)';
                $params[] = $n;
                $params[] = $n;
            } else {
                $where .= ' AND c.cliente_id IN (SELECT id FROM clientes WHERE nome_fantasia LIKE ? OR cnpj LIKE ?)';
                $params[] = '%'.$busca.'%';
                $params[] = '%'.$busca.'%';
            }
        }
        if (in_array($status, ['rascunho', 'finalizada', 'cancelada'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        } else {
            $where .= " AND c.status != 'cancelada'";
        }
        if (in_array($recebimento, ['pendente', 'recebido'], true)) {
            $where .= ' AND c.situacao_recebimento = ?';
            $params[] = $recebimento;
        }

        $filtroMtr = trim((string)($post['filtro_mtr'] ?? ''));
        if ($filtroMtr === 'com_mtr') {
            $where .= " AND (c.sinir_status = 'enviado' OR (c.legacy_manifesto IS NOT NULL AND c.numero_mtr IS NOT NULL))";
        } elseif ($filtroMtr === 'sem_mtr') {
            $where .= " AND c.status = 'finalizada' AND (c.sinir_status IS NULL OR c.sinir_status NOT IN ('enviado'))";
        } elseif ($filtroMtr === 'mtr_pendente') {
            $where .= " AND c.status = 'finalizada' AND cl.exige_mtr = 1 AND (c.sinir_status IS NULL OR c.sinir_status IN ('pendente','erro'))";
        }

        $usuario = SessionUser::getUserLogedData()['usuario'] ?? [];
        if (empty($usuario['is_admin'])) {
            $userId = (int)($usuario['id'] ?? 0);
            [$extraWhere, $extraParams] = RotaScopeService::coletasWhereForColetor($userId);
            $where .= $extraWhere;
            $params = array_merge($params, $extraParams);
        }

        $pagination = new Pagination(EntityColeta::count($where, $params), $page, 15);
        $rows = EntityColeta::list($where, $params, $pagination->getLimit());

        $itens = '';
        foreach ($rows as $c) {
            $numRel = ColetaMtrHelper::numeroRelatorioExibicao($c);
            $relCol = $numRel !== null
                ? '#'.CrudHelper::e($numRel)
                : '<span class="text-muted">—</span>';
            $numExib = ColetaMtrHelper::numeroExibicao($c);
            $mtr = $numExib !== null
                ? '#'.CrudHelper::e($numExib)
                : '<span class="text-muted">'.CrudHelper::e(ColetaMtrHelper::rotuloSemMtr($c)).'</span>';
            $badge = match ($c->status) {
                'finalizada' => 'success',
                'rascunho' => 'warning',
                'cancelada' => 'danger',
                default => 'secondary',
            };
            $data = $c->data_coleta ? date('d/m/Y', strtotime($c->data_coleta)) : '—';
            $sinirBadge = SinirService::renderStatusBadge($c->sinir_status, $c->status);
            $podeCancelar = ColetaMtrHelper::podeCancelar($c)
                && (!empty($usuario['is_admin']) || (int)$c->coletor_id === (int)($usuario['id'] ?? 0));
            $itens .= '<tr>
                <td>'.$relCol.'</td>
                <td>'.$mtr.'</td>
                <td>'.CrudHelper::e($c->cliente_nome)
                .($c->cliente_exige_mtr && $c->status === 'finalizada' && !ColetaMtrHelper::temMtr($c)
                    ? ' <span class="badge bg-warning text-dark">MTR pendente</span>' : '').'</td>
                <td>'.$data.'</td>
                <td>'.CrudHelper::e($c->coletor_nome).'</td>
                <td><span class="badge bg-'.$badge.'">'.CrudHelper::e($c->status).'</span></td>
                <td>'.$sinirBadge.'</td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" onclick="detalhar('.$c->id.')" title="Detalhe"><i class="fas fa-eye"></i></button>
                    '.(ColetaMtrHelper::podeImprimirRelatorio($c)
                        ? '<a class="btn btn-sm btn-outline-secondary" href="'.URL.'/painel/coletas/mtr/'.$c->id.'" target="_blank" title="Imprimir relatório"><i class="fas fa-print"></i></a>'
                          .'<a class="btn btn-sm btn-outline-danger" href="'.URL.'/painel/coletas/mtr/'.$c->id.'/pdf" title="Baixar PDF"><i class="fas fa-file-pdf"></i></a>'
                        : '').'
                    '.($podeCancelar
                        ? '<button class="btn btn-sm btn-outline-danger" onclick="cancelarColeta('.$c->id.', '.($c->status === 'finalizada' && ColetaMtrHelper::precisaCancelarMtrSinir($c) ? 'true' : 'false').')" title="Cancelar coleta"><i class="fas fa-ban"></i></button>'
                        : '').'
                    '.(ColetaMtrHelper::podeGerarMtr($c)
                        ? '<button class="btn btn-sm btn-outline-warning" onclick="sinirReenviar('.$c->id.')" title="Gerar MTR no SINIR"><i class="fas fa-file-contract"></i></button>'
                        : '').'
                    '.($c->status === 'finalizada' && SinirConfig::isEnabled() && ($c->sinir_status ?? '') === 'enviado'
                        ? '<button class="btn btn-sm btn-outline-secondary" onclick="sinirConsultar('.$c->id.')" title="Consultar SINIR"><i class="fas fa-search"></i></button>'
                        : '').'
                    '.($c->status === 'rascunho' ? '<a class="btn btn-sm btn-outline-warning" href="'.URL.'/painel/coleta/nova/'.$c->id.'" title="Continuar"><i class="fas fa-edit"></i></a>' : '').'
                </td>
            </tr>';
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="8" class="text-center text-muted">Nenhuma coleta encontrada.</td></tr>';
        }

        return self::jsonLista(['success' => true, 'itens' => $itens, 'pagination' => Pagination::renderNav($pagination)]);
    }

    public static function excluir($request): string
    {
        return self::cancelar($request);
    }

    public static function cancelar($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        $justificativa = trim((string)($post['justificativa'] ?? ''));
        $usuario = SessionUser::getUserLogedData()['usuario'] ?? [];
        $coleta = EntityColeta::getById($id);
        if (!$coleta || !ColetaMtrHelper::podeCancelar($coleta)) {
            return CrudHelper::jsonError('Somente rascunhos ou coletas finalizadas podem ser cancelados.');
        }
        if (empty($usuario['is_admin']) && (int)$coleta->coletor_id !== (int)($usuario['id'] ?? 0)) {
            return CrudHelper::jsonError('Sem permissão para cancelar esta coleta.');
        }
        try {
            $result = ColetaService::cancelar($id, $justificativa);
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        }

        return CrudHelper::jsonOk(['message' => $result['message']]);
    }

    public static function get($request): string
    {
        $id = (int)($request->getPostVars()['id'] ?? 0);
        try {
            $det = ColetaService::detalhar($id);
        } catch (\InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        }

        $c = $det['coleta'];
        $s = $det['snapshot'];
        $itensHtml = '';
        foreach ($det['itens'] as $i) {
            $itensHtml .= '<tr><td>'.CrudHelper::e($i->nome).'</td><td>'.CrudHelper::e($i->classe_nome).'</td><td>'.number_format($i->quantidade, 3, ',', '.').' '.$i->unidade.'</td></tr>';
        }
        if ($itensHtml === '') {
            $itensHtml = '<tr><td colspan="3" class="text-muted">Sem itens.</td></tr>';
        }

        $evidHtml = '';
        foreach ($det['evidencias'] as $e) {
            $parts = explode('/', $e->arquivo);
            $url = URL.'/storage/coletas/'.(int)($parts[1] ?? 0).'/'.basename($e->arquivo);
            $evidHtml .= '<div class="col-md-4"><a href="'.$url.'" target="_blank"><img src="'.$url.'" class="img-fluid rounded border" alt="Evidência '.$e->ordem.'"/></a></div>';
        }

        $mtrPrintBtn = ColetaMtrHelper::podeImprimirRelatorio($c)
            ? '<a href="'.URL.'/painel/coletas/mtr/'.$c->id.'" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i> Imprimir</a>'
              .' <a href="'.URL.'/painel/coletas/mtr/'.$c->id.'/pdf" class="btn btn-sm btn-outline-danger"><i class="fas fa-file-pdf me-1"></i> Baixar PDF</a>'
            : '';

        $usuario = SessionUser::getUserLogedData()['usuario'] ?? [];
        $podeCancelar = ColetaMtrHelper::podeCancelar($c)
            && (!empty($usuario['is_admin']) || (int)$c->coletor_id === (int)($usuario['id'] ?? 0));
        $cancelarBtn = $podeCancelar
            ? '<button type="button" class="btn btn-sm btn-outline-danger" onclick="cancelarColeta('.$c->id.', '.(ColetaMtrHelper::precisaCancelarMtrSinir($c) ? 'true' : 'false').')"><i class="fas fa-ban me-1"></i> Cancelar coleta</button>'
            : '';

        $sinirHtml = self::renderSinirDetalhe($c);
        $sinirReenviarBtn = '';
        $sinirJaEnviado = ($c->sinir_status ?? '') === 'enviado';
        $stSinir = $c->sinir_status ?? '';
        if (ColetaMtrHelper::podeGerarMtr($c)) {
            $label = match ($c->sinir_status ?? '') {
                'erro' => 'Tentar novamente — Gerar MTR',
                'cancelado' => 'Emitir novo MTR no SINIR',
                'pendente' => 'Continuar envio — Gerar MTR',
                default => 'Gerar MTR no SINIR',
            };
            $sinirReenviarBtn = '<button type="button" class="btn btn-sm btn-outline-warning" onclick="sinirReenviar('.$c->id.')"><i class="fas fa-sync me-1"></i> '
                .CrudHelper::e($label).'</button>';
        }
        if ($c->status === 'finalizada' && SinirConfig::isEnabled() && $sinirJaEnviado) {
            $sinirReenviarBtn .= ' <button type="button" class="btn btn-sm btn-outline-secondary" onclick="sinirConsultar('.$c->id.')"><i class="fas fa-search me-1"></i> Consultar SINIR</button>'
                .' <button type="button" class="btn btn-sm btn-outline-danger" onclick="sinirCancelar('.$c->id.')"><i class="fas fa-ban me-1"></i> Cancelar no SINIR</button>';
        }
        if (ColetaMtrHelper::podeRegistrarRecebimentoSinir($c)) {
            $sinirReenviarBtn .= ' <button type="button" class="btn btn-sm btn-outline-success" onclick="sinirReceber('.$c->id.')"><i class="fas fa-warehouse me-1"></i> Receber no SINIR</button>';
        }
        if ($sinirJaEnviado && SinirConfig::isEnabled() && !ColetaCdfService::temCdf($c)) {
            $sinirReenviarBtn .= ' <button type="button" class="btn btn-sm btn-outline-info" onclick="sinirBaixarPdf('.$c->id.')"><i class="fas fa-file-pdf me-1"></i> Baixar PDF MTR</button>';
        }
        if (ColetaMtrHelper::podeEmitirCdfSinir($c)) {
            $sinirReenviarBtn .= ' <button type="button" class="btn btn-sm btn-outline-primary" onclick="sinirEmitirCdf('.$c->id.')"><i class="fas fa-certificate me-1"></i> Emitir CDF SINIR</button>';
        }
        if (!empty($c->sinir_cdf_codigo) && SinirConfig::isEnabled()) {
            $sinirReenviarBtn .= ' <button type="button" class="btn btn-sm btn-outline-info" onclick="sinirBaixarCdf('.$c->id.')"><i class="fas fa-download me-1"></i> Baixar PDF CDF</button>';
        }
        $cdfAdminHtml = '';
        if (ColetaCdfService::temCdf($c)) {
            $rotulo = CrudHelper::e(ColetaCdfService::rotuloTipo($c->cdf_tipo, $c->sinir_cdf_codigo));
            $cdfAdminHtml = '<p class="small mb-1"><strong>Tipo:</strong> '.$rotulo.'</p>'
                .'<a class="btn btn-sm btn-outline-primary" href="'.URL.'/painel/coletas/cdf/'.$c->id.'" target="_blank"><i class="fas fa-file-pdf me-1"></i> Abrir PDF</a>';
        } else {
            $cdfAdminHtml = '<span class="text-muted small">Sem PDF — receba no SINIR, emita CDF ou envie manualmente.</span>';
        }

        $html = View::render('admin/modules/coletas/detalhe', [
            'mtr_print_btn' => $mtrPrintBtn,
            'cancelar_btn' => $cancelarBtn,
            'sinir_reenviar_btn' => $sinirReenviarBtn,
            'cdf_admin_html' => $cdfAdminHtml,
            'cdf_upload_form' => SinirConfig::isEnabled() && $c->status === 'finalizada'
                ? '<form id="form-cdf-upload-'.$c->id.'" class="d-flex flex-wrap gap-2 align-items-center mt-2" onsubmit="return sinirCdfUpload(event, '.$c->id.')">'
                    .'<input type="file" name="cdf_file" accept="application/pdf" class="form-control form-control-sm" style="max-width:220px" required/>'
                    .'<button type="submit" class="btn btn-sm btn-secondary">Enviar PDF manual</button></form>'
                : '',
            'sinir_html' => $sinirHtml,
            'numero_relatorio' => ColetaMtrHelper::numeroRelatorioExibicao($c)
                ? '#'.ColetaMtrHelper::numeroRelatorioExibicao($c)
                : '—',
            'numero_mtr' => ColetaMtrHelper::numeroExibicao($c)
                ? '#'.ColetaMtrHelper::numeroExibicao($c)
                : ColetaMtrHelper::rotuloSemMtr($c),
            'cliente' => CrudHelper::e($c->cliente_nome),
            'coletor' => CrudHelper::e($c->coletor_nome),
            'data_coleta' => $c->data_coleta ? date('d/m/Y H:i', strtotime($c->data_coleta.' '.($c->hora ?? '00:00:00'))) : '—',
            'status' => CrudHelper::e($c->status),
            'gerador' => CrudHelper::e($s->gerador_nome_fantasia ?? ''),
            'endereco' => CrudHelper::e($s->gerador_endereco ?? ''),
            'transportador' => CrudHelper::e($s->transportador_nome ?? '').' — '.CrudHelper::e($s->transportador_cnpj ?? ''),
            'motorista' => CrudHelper::e($s->motorista_nome ?? ''),
            'veiculo' => CrudHelper::e(($s->veiculo_descricao ?? '').' '.($s->veiculo_placa ?? '')),
            'destinador' => CrudHelper::e($s->destinador_nome ?? ''),
            'tratamento' => CrudHelper::e($c->tratamento ?? ''),
            'relatorio' => nl2br(CrudHelper::e($c->relatorio ?? '')),
            'itens_html' => $itensHtml,
            'evidencias_html' => $evidHtml ?: '<p class="text-muted">Sem evidências.</p>',
        ]);

        return CrudHelper::jsonOk(['html' => $html]);
    }

    public static function sinirPrecheck($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }

        $precheck = (new ColetaSinirPrecheckService())->forColeta($id);

        return CrudHelper::jsonOk([
            'ok' => $precheck['ok'],
            'checks' => $precheck['checks'],
            'errors' => $precheck['errors'],
        ]);
    }

    public static function sinirReenviar($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }

        if (!SinirConfig::isEnabled()) {
            return CrudHelper::jsonError('Integração SINIR desabilitada no .env.');
        }

        $precheck = (new ColetaSinirPrecheckService())->forColeta($id);
        if (!$precheck['ok']) {
            return CrudHelper::jsonError(implode(' ', $precheck['errors']));
        }

        $result = ColetaService::gerarMtrSinir($id, true);
        if (!empty($result['skipped'])) {
            return CrudHelper::jsonOk(['message' => $result['message']]);
        }
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk([
            'message' => $result['message'],
            'details' => $result['details'] ?? [],
        ]);
    }

    public static function sinirCancelar($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        $justificativa = trim((string)($post['justificativa'] ?? ''));
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }

        $result = SinirService::cancelarColeta($id, $justificativa);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message']]);
    }

    public static function sinirReceber($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }
        $responsavel = trim((string)($post['responsavel'] ?? ''));
        $cargo = trim((string)($post['cargo'] ?? ''));

        $result = SinirService::receberColeta(
            $id,
            true,
            $responsavel !== '' ? $responsavel : null,
            $cargo !== '' ? $cargo : null
        );
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message'], 'details' => $result['details'] ?? []]);
    }

    public static function sinirEmitirCdf($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }
        $responsavel = trim((string)($post['responsavel'] ?? ''));

        $result = SinirService::emitirCdfColeta($id, $responsavel !== '' ? $responsavel : null);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message'], 'details' => $result['details'] ?? []]);
    }

    public static function sinirBaixarCdf($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }

        $result = SinirService::baixarPdfCdf($id);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message']]);
    }

    public static function sinirBaixarPdf($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }

        $result = SinirService::baixarPdfManifesto($id);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message']]);
    }

    public static function sinirCdfUpload($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }

        $file = $_FILES['cdf_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return CrudHelper::jsonError('Selecione um arquivo PDF.');
        }

        $result = ColetaCdfService::gravarUpload($id, (string)$file['tmp_name'], (string)($file['type'] ?? ''));
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message']]);
    }

    public static function cdfDownload($request, int $id): string|Response
    {
        try {
            ColetaService::detalhar($id);
        } catch (\InvalidArgumentException) {
            return View::render('erros/404', ['URL' => URL]);
        }

        $c = EntityColeta::getById($id);
        if (!$c) {
            return View::render('erros/404', ['URL' => URL]);
        }
        $path = ColetaCdfService::absolutePath($c);
        if ($path === null) {
            return View::render('erros/404', ['URL' => URL]);
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return View::render('erros/404', ['URL' => URL]);
        }

        return new Response(200, $bytes, 'application/pdf');
    }

    public static function sinirConsultar($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $id = (int)($post['id'] ?? 0);
        if ($id <= 0) {
            return CrudHelper::jsonError('Coleta inválida.');
        }

        $result = SinirService::consultarColeta($id);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk([
            'message' => $result['message'],
            'details' => $result['details'] ?? [],
        ]);
    }

    private static function renderSinirDetalhe(EntityColeta $c): string
    {
        if ($c->status !== 'finalizada') {
            return '<p class="text-muted mb-0">Disponível após finalizar a coleta.</p>';
        }

        if (!SinirConfig::isEnabled()) {
            return '<p class="text-muted mb-0">Integração SINIR desabilitada (<code>SINIR_ENABLED=false</code>).</p>';
        }

        $badge = SinirService::renderStatusBadge($c->sinir_status, $c->status);
        $man = $c->sinir_man_numero ? CrudHelper::e($c->sinir_man_numero) : '—';
        $bar = $c->sinir_codigo_barras ? CrudHelper::e($c->sinir_codigo_barras) : '—';
        $enviado = $c->sinir_enviado_em
            ? date('d/m/Y H:i', strtotime($c->sinir_enviado_em))
            : '—';
        $recebidoSinir = $c->sinir_recebido_em
            ? date('d/m/Y H:i', strtotime($c->sinir_recebido_em))
            : '—';
        $cdfInfo = ColetaCdfService::temCdf($c)
            ? ColetaCdfService::rotuloTipo($c->cdf_tipo, $c->sinir_cdf_codigo)
                .($c->cdf_obtido_em ? ' · '.date('d/m/Y H:i', strtotime($c->cdf_obtido_em)) : '')
            : 'Não';
        $cdfCod = $c->sinir_cdf_codigo ? CrudHelper::e($c->sinir_cdf_codigo) : '—';

        $historico = '';
        foreach (EntitySinirEnvio::listByColeta($c->id, 3) as $envio) {
            $histBadge = match ($envio->status) {
                'enviado' => 'success',
                'erro' => 'danger',
                'cancelado' => 'secondary',
                'consulta' => 'info',
                'recebido' => 'success',
                default => 'secondary',
            };
            $historico .= '<li><span class="badge bg-'.$histBadge.'">T'.$envio->tentativa.' · '.$envio->status.'</span>';
            if ($envio->mensagem_erro) {
                $historico .= ' — '.CrudHelper::e($envio->mensagem_erro);
            }
            $historico .= '</li>';
        }

        $histHtml = $historico !== ''
            ? '<ul class="small mb-0 ps-3">'.$historico.'</ul>'
            : '<p class="text-muted small mb-0">Nenhuma tentativa registrada.</p>';

        return '
            <p class="mb-1"><strong>Status:</strong> '.$badge.'</p>
            <p class="mb-1"><strong>MTR SINIR:</strong> '.$man.'</p>
            <p class="mb-1"><strong>Cód. barras:</strong> '.$bar.'</p>
            <p class="mb-1"><strong>Enviado em:</strong> '.$enviado.'</p>
            <p class="mb-1"><strong>Recebido SINIR:</strong> '.$recebidoSinir.'</p>
            <p class="mb-1"><strong>CDF SINIR nº:</strong> '.$cdfCod.'</p>
            <p class="mb-2"><strong>PDF portal:</strong> '.CrudHelper::e($cdfInfo).'</p>
            <p class="mb-1"><strong>Últimas tentativas:</strong></p>
            '.$histHtml;
    }

    public static function mtrPrint($request, int $id): string
    {
        try {
            $det = ColetaService::detalhar($id);
        } catch (\InvalidArgumentException $e) {
            return View::render('erros/404', ['URL' => URL]);
        }

        if (!ColetaMtrHelper::podeImprimirRelatorio($det['coleta'])) {
            return View::render('erros/405', ['URL' => URL]);
        }

        return \App\Service\ColetaRelatorioPdfService::renderHtml($det, [
            'auto_print' => ($request->getQueryParams()['print'] ?? '') === '1',
        ]);
    }

    public static function mtrPdf($request, int $id): Response
    {
        try {
            $det = ColetaService::detalhar($id);
        } catch (\InvalidArgumentException $e) {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        if (!ColetaMtrHelper::podeImprimirRelatorio($det['coleta'])) {
            return new Response(405, View::render('erros/405', ['URL' => URL]));
        }

        return \App\Service\ColetaRelatorioPdfService::pdfResponse($det);
    }
}
