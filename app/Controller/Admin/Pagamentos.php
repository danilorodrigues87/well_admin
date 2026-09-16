<?php

namespace App\Controller\Admin;

use App\Common\CobrancaConfig;
use App\Common\Helpers\CrudHelper;
use App\Common\InterConfig;
use App\Model\Db\Pagination;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\InterCobranca as EntityInterCobranca;
use App\Model\Entity\Plano as EntityPlano;
use App\Service\FaturamentoService;
use App\Service\MailService;
use App\Service\PlanoCobrancaService;
use App\Utils\View;

class Pagamentos extends Page
{
    private static function planosOptions(): string
    {
        $html = '<option value="">Todos os planos</option>';
        foreach (EntityPlano::getAllActive() as $p) {
            $html .= '<option value="'.$p->id.'">'.CrudHelper::e($p->nome).'</option>';
        }

        return $html;
    }

    private static function configFields(): array
    {
        $multa = CobrancaConfig::multa();
        $mora = CobrancaConfig::mora();

        return [
            'multa_tipo' => $multa['tipo'],
            'multa_taxa' => number_format($multa['taxa'], 2, '.', ''),
            'multa_valor' => number_format($multa['valor'], 2, '.', ''),
            'mora_tipo' => $mora['tipo'],
            'mora_taxa' => number_format($mora['taxa'], 2, '.', ''),
            'mora_valor' => number_format($mora['valor'], 2, '.', ''),
        ];
    }

    public static function index($request): string
    {
        $cfg = self::configFields();
        $competenciaDefault = date('Y-m', strtotime('first day of last month'));
        $vencimentoDefault = date('Y-m-d', strtotime('+10 days'));
        $interOk = InterConfig::isConfigured();

        $content = View::render('admin/modules/pagamentos/index', array_merge([
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'planos_options' => self::planosOptions(),
            'competencia_default' => $competenciaDefault,
            'vencimento_default' => $vencimentoDefault,
            'inter_configured' => $interOk ? '1' : '0',
            'inter_alert' => $interOk
                ? ''
                : '<div class="alert alert-warning">Integração Inter incompleta. Configure .env e certificados antes de emitir boletos.</div>',
            'resumo_multa_mora' => CobrancaConfig::resumoMultaMora(),
        ], $cfg));

        $scripts = self::crudScripts('/painel/pagamentos', false)
            .'<script src="'.URL.'/resources/js/pagamentos.js?v=20260916f"></script>';

        return self::getPage('Pagamentos', $content, 'pagamentos', $scripts);
    }

    public static function relatorio($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $competencia = trim((string)($post['competencia'] ?? date('Y-m')));
        $filtros = [
            'plano_id' => (int)($post['plano_id'] ?? 0),
            'situacao' => trim((string)($post['situacao'] ?? '')),
            'busca' => trim((string)($post['busca'] ?? '')),
        ];

        $page = max(1, (int)($post['page'] ?? 1));
        $result = FaturamentoService::relatorioCompetencia($competencia, $filtros, $page, 25);
        $pagination = new Pagination($result['total'], $result['page'], 25);
        $itens = '';

        foreach ($result['rows'] as $row) {
            $disabled = $row['situacao'] === 'emitida' ? 'disabled' : '';
            $checked = $row['situacao'] === 'pendente' && empty($row['validacao']) ? 'checked' : '';
            $valorTotal = number_format((float)$row['valor_total'], 2, '.', '');
            $badge = $row['situacao'] === 'emitida' ? 'success' : 'secondary';
            $aviso = $row['validacao']
                ? '<span class="badge bg-danger ms-1" title="'.CrudHelper::e((string)$row['validacao']).'">!</span>'
                : '';

            $itens .= '<tr data-cliente-id="'.(int)$row['cliente_id'].'">
                <td><input type="checkbox" class="form-check-input chk-cliente" value="'.(int)$row['cliente_id'].'" '.$checked.' '.$disabled.'/></td>
                <td>'.CrudHelper::e((string)$row['cliente_nome']).$aviso.'</td>
                <td>'.CrudHelper::e((string)$row['plano_nome']).'</td>
                <td class="text-end">R$ '.number_format((float)$row['valor_fixo'], 2, ',', '.').'</td>
                <td class="text-end">R$ '.number_format((float)$row['valor_residuos'], 2, ',', '.').'</td>
                <td class="text-end">R$ '.number_format((float)$row['valor_total'], 2, ',', '.').'</td>
                <td class="text-end" style="min-width:120px">
                    <input type="number" step="0.01" min="2.50" class="form-control form-control-sm input-valor-final text-end"
                        value="'.$valorTotal.'" data-calculado="'.$valorTotal.'" '.$disabled.'/>
                </td>
                <td><span class="badge bg-'.$badge.'">'.CrudHelper::e($row['situacao']).'</span></td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-detalhe" data-cliente-id="'.(int)$row['cliente_id'].'">
                        <i class="fas fa-list"></i>
                    </button>
                </td>
            </tr>';

        }

        if ($itens === '') {
            $itens = '<tr><td colspan="9" class="text-center text-muted">Nenhum cliente encontrado para a competência.</td></tr>';
        }

        return self::jsonLista([
            'success' => true,
            'itens' => $itens,
            'pagination' => Pagination::renderNav($pagination, 'loadPageRelatorio'),
            'total' => $result['total'],
            'page' => $result['page'],
            'competencia' => $result['competencia'],
        ]);
    }

    public static function historico($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $where = '1=1';
        $params = [];
        $competencia = trim((string)($post['competencia'] ?? ''));
        if ($competencia !== '') {
            $where .= ' AND ic.competencia = ?';
            $params[] = $competencia;
        }
        $status = trim((string)($post['status'] ?? ''));
        if ($status !== '') {
            $where .= ' AND ic.status = ?';
            $params[] = $status;
        }

        $page = max(1, (int)($post['page'] ?? 1));
        $perPage = 20;
        $total = EntityInterCobranca::countHistorico($where, $params);
        $pagination = new Pagination($total, $page, $perPage);
        $rows = EntityInterCobranca::listHistorico($where, $params, $pagination->getLimit());
        $itens = '';

        foreach ($rows as $c) {
            $comp = $c->competencia ? date('m/Y', strtotime($c->competencia.'-01')) : '—';
            $venc = date('d/m/Y', strtotime($c->data_vencimento));
            $valor = number_format($c->valor_nominal, 2, ',', '.');
            if ($c->email_enviado_em) {
                $emailBadge = '<span class="badge bg-success">Enviado</span>';
            } elseif ($c->email_erro) {
                $emailBadge = '<span class="badge bg-danger" title="'.CrudHelper::e($c->email_erro).'">Falhou</span>';
            } else {
                $emailBadge = '<span class="badge bg-secondary">Não enviado</span>';
            }

            $itens .= '<tr>
                <td>'.CrudHelper::e((string)($c->cliente_nome ?? '')).'</td>
                <td>'.$comp.'</td>
                <td class="text-end">R$ '.$valor.'</td>
                <td>'.$venc.'</td>
                <td>'.self::statusBadge($c->status).'</td>
                <td>'.$emailBadge.'</td>
                <td class="text-nowrap">'.self::historicoAcoesHtml($c).'</td>
            </tr>';
        }

        if ($itens === '') {
            $itens = '<tr><td colspan="7" class="text-center text-muted">Nenhuma cobrança emitida.</td></tr>';
        }

        return self::jsonLista([
            'success' => true,
            'itens' => $itens,
            'pagination' => Pagination::renderNav($pagination, 'loadPageHistorico'),
            'total' => $total,
            'page' => $pagination->getCurrentPage(),
        ]);
    }

    public static function emitirLote($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $competencia = trim((string)($post['competencia'] ?? ''));
        $dataVencimento = trim((string)($post['data_vencimento'] ?? ''));
        $enviarEmail = !empty($post['enviar_email']);
        $itensRaw = $post['itens'] ?? [];
        if (!is_array($itensRaw)) {
            return CrudHelper::jsonError('Nenhum cliente selecionado');
        }

        $itens = [];
        foreach ($itensRaw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itens[] = [
                'cliente_id' => (int)($item['cliente_id'] ?? 0),
                'valor_final' => (float)str_replace(',', '.', (string)($item['valor_final'] ?? '0')),
                'observacao' => trim((string)($item['observacao'] ?? '')),
            ];
        }

        if ($itens === []) {
            return CrudHelper::jsonError('Selecione ao menos um cliente');
        }

        $override = CobrancaConfig::parseOverrideFromPost($post);
        $result = FaturamentoService::emitirLote(
            $competencia,
            $dataVencimento,
            $itens,
            $override,
            $enviarEmail
        );

        return json_encode([
            'success' => $result['emitidos'] > 0,
            'emitidos' => $result['emitidos'],
            'erros' => $result['erros'],
            'detalhes' => $result['detalhes'],
            'message' => $result['emitidos'] > 0
                ? $result['emitidos'].' boleto(s) emitido(s)'
                : 'Nenhum boleto emitido',
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function saveConfig($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        CobrancaConfig::saveFromPost($post);

        return CrudHelper::jsonOk([
            'message' => 'Configurações salvas',
            'resumo' => CobrancaConfig::resumoMultaMora(),
        ]);
    }

    public static function detalhe($request, int $clienteId): string
    {
        $competencia = trim((string)($request->getQueryParams()['competencia'] ?? date('Y-m')));
        $calculo = PlanoCobrancaService::calcularMes($clienteId, $competencia);

        return json_encode([
            'success' => true,
            'cliente_id' => $clienteId,
            'competencia' => $competencia,
            'calculo' => $calculo,
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function sincronizarStatus($request, int $id): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $result = FaturamentoService::sincronizarCobranca($id);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['error'] ?? 'Falha ao sincronizar');
        }

        $msg = $result['alterou']
            ? 'Status atualizado: '.$result['status_anterior'].' → '.$result['status']
            : 'Status já estava atualizado ('.$result['status'].')';

        return CrudHelper::jsonOk([
            'message' => $msg,
            'status' => $result['status'],
            'alterou' => $result['alterou'],
        ]);
    }

    public static function baixaManual($request, int $id): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $result = FaturamentoService::baixaManual($id);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['error'] ?? 'Falha na baixa manual');
        }

        return CrudHelper::jsonOk([
            'message' => 'Baixa manual registrada — status PAGO',
            'status' => $result['status'],
        ]);
    }

    public static function enviarEmail($request, int $id): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $cobranca = EntityInterCobranca::getById($id);
        if (!$cobranca) {
            return CrudHelper::jsonError('Cobrança não encontrada');
        }

        if (!$cobranca->linha_digitavel && !$cobranca->pdf_path) {
            FaturamentoService::enriquecerCobranca($id);
            $cobranca = EntityInterCobranca::getById($id);
        }

        if (!$cobranca) {
            return CrudHelper::jsonError('Cobrança não encontrada');
        }

        $cliente = $cobranca->cliente_id ? EntityCliente::getById((int)$cobranca->cliente_id) : null;
        $result = MailService::enviarBoleto($cobranca, $cliente);

        return $result['ok']
            ? CrudHelper::jsonOk(['message' => 'E-mail enviado'])
            : CrudHelper::jsonError($result['error'] ?? 'Falha no envio');
    }

    public static function downloadPdf($request, int $id): void
    {
        $cobranca = EntityInterCobranca::getById($id);
        if (!$cobranca) {
            http_response_code(404);
            echo 'Cobrança não encontrada';
            exit;
        }

        if (!$cobranca->pdf_path) {
            FaturamentoService::enriquecerCobranca($id);
            $cobranca = EntityInterCobranca::getById($id);
        }

        $path = $cobranca?->pdfAbsolutePath();
        if (!$path || !is_file($path)) {
            http_response_code(404);
            echo 'PDF não disponível';
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="boleto-'.$id.'.pdf"');
        header('Content-Length: '.filesize($path));
        readfile($path);
        exit;
    }

    private static function statusBadge(string $status): string
    {
        $s = mb_strtoupper(trim($status));
        $cls = match ($s) {
            'PAGO' => 'success',
            'EMITIDA' => 'primary',
            'VENCIDO' => 'warning',
            'CANCELADO' => 'secondary',
            default => 'secondary',
        };

        return '<span class="badge bg-'.$cls.'">'.CrudHelper::e($status).'</span>';
    }

    private static function historicoAcoesHtml(EntityInterCobranca $c): string
    {
        $status = mb_strtoupper(trim($c->status));
        $podeBaixa = !in_array($status, ['PAGO', 'CANCELADO'], true);
        $baixaBtn = $podeBaixa
            ? '<button type="button" class="btn btn-sm btn-outline-warning btn-baixa" data-id="'.$c->id.'" title="Baixa manual"><i class="fas fa-check"></i></button>'
            : '';

        return '<a class="btn btn-sm btn-outline-primary" href="'.URL.'/painel/pagamentos/'.$c->id.'/pdf" target="_blank" title="PDF"><i class="fas fa-file-pdf"></i></a>'
            .'<button type="button" class="btn btn-sm btn-outline-secondary btn-copy" data-copy="'.CrudHelper::e((string)$c->linha_digitavel).'" title="Copiar linha" '.($c->linha_digitavel ? '' : 'disabled').'><i class="fas fa-barcode"></i></button>'
            .'<button type="button" class="btn btn-sm btn-outline-info btn-copy" data-copy="'.CrudHelper::e((string)$c->pix_copia_cola).'" title="Copiar PIX" '.($c->pix_copia_cola ? '' : 'disabled').'><i class="fas fa-qrcode"></i></button>'
            .'<button type="button" class="btn btn-sm btn-outline-success btn-email" data-id="'.$c->id.'" title="Enviar e-mail"><i class="fas fa-envelope"></i></button>'
            .'<button type="button" class="btn btn-sm btn-outline-dark btn-sync" data-id="'.$c->id.'" title="Sincronizar com Inter"><i class="fas fa-sync"></i></button>'
            .$baixaBtn;
    }

    /** @param list<array<string,mixed>> $itens */
    private static function detalheRowHtml(int $clienteId, array $itens, int $coletasNoMes = 0): string
    {
        $linhas = '';
        foreach ($itens as $item) {
            $saldoTxt = number_format((float)$item['saldo'], 3, ',', '.');
            if (!empty($item['saldo_info'])) {
                $saldoTxt .= ' <small class="text-muted">('.CrudHelper::e((string)$item['saldo_info']).')</small>';
            }
            $linhas .= '<tr>
                <td>'.CrudHelper::e((string)$item['nome']).'</td>
                <td class="text-end">'.number_format((float)$item['coletado'], 3, ',', '.').' '.CrudHelper::e((string)$item['unidade']).'</td>
                <td class="text-end">'.$saldoTxt.'</td>
                <td class="text-end">'.number_format((float)$item['excedente'], 3, ',', '.').'</td>
                <td class="text-end">R$ '.number_format((float)$item['valor'], 2, ',', '.').'</td>
            </tr>';

            foreach ($item['origens'] ?? [] as $origem) {
                $dataLabel = !empty($origem['data']) ? date('d/m', strtotime((string)$origem['data'])) : '—';
                $linhas .= '<tr class="table-light">
                    <td colspan="2" class="small text-muted ps-4">↳ MTR '.(int)$origem['mtr'].' ('.$dataLabel.')</td>
                    <td class="text-end small text-muted">'.number_format((float)$origem['quantidade'], 3, ',', '.').' '.CrudHelper::e((string)$item['unidade']).'</td>
                    <td colspan="2"></td>
                </tr>';
            }
        }
        if ($linhas === '') {
            $linhas = '<tr><td colspan="5" class="text-muted">Sem itens de plano/resíduo.</td></tr>';
        }

        $avisoColetas = $coletasNoMes > 1
            ? '<p class="small text-muted mb-2"><i class="fas fa-info-circle"></i> '
                .$coletasNoMes.' coleta(s) finalizada(s) nesta competência — o total <strong>soma todas</strong>.</p>'
            : '';

        return '<tr class="detalhe-row d-none" data-detalhe-cliente="'.$clienteId.'">
            <td colspan="9" class="bg-light">
                '.$avisoColetas.'
                <table class="table table-sm mb-0">
                    <thead><tr>
                        <th>Resíduo</th><th class="text-end">Coletado</th><th class="text-end">Saldo incl.</th>
                        <th class="text-end">Excedente</th><th class="text-end">Valor R$</th>
                    </tr></thead>
                    <tbody>'.$linhas.'</tbody>
                </table>
            </td>
        </tr>';
    }
}
