<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Common\SinirConfig;
use App\Http\Response;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Service\CdfLoteService;
use App\Service\ColetaCdfService;
use App\Service\Sinir\SinirCdfService;
use App\Service\Sinir\SinirRecebimentoService;
use App\Utils\View;

class Cdf extends Page
{
    private static function clientesOptions(int $selecionado): string
    {
        $html = '<option value="">Selecione o gerador…</option>';
        foreach (EntityCliente::list("c.status = 'ativo'", [], '500') as $c) {
            $sel = $c->id === $selecionado ? ' selected' : '';
            $html .= '<option value="'.$c->id.'"'.$sel.'>'.CrudHelper::e($c->nome_fantasia).'</option>';
        }

        return $html;
    }

    public static function index($request): string
    {
        $query = $request->getQueryParams();
        $clienteId = (int)($query['cliente_id'] ?? 0);
        $inicio = self::dataQuery($query['data_inicio'] ?? null, date('Y-m-01'));
        $fim = self::dataQuery($query['data_fim'] ?? null, date('Y-m-t'));

        $content = View::render('admin/modules/cdf/index', [
            'csrf_field' => CsrfHelper::field(),
            'clientes_options' => self::clientesOptions($clienteId),
            'data_inicio' => CrudHelper::e($inicio),
            'data_fim' => CrudHelper::e($fim),
        ]);

        return self::getPage('Certificados', $content, 'coletas', self::crudScripts('/painel/cdf'));
    }

    public static function list($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $clienteId = (int)($post['cliente_id'] ?? 0);
        $inicio = self::dataQuery($post['data_inicio'] ?? null, '');
        $fim = self::dataQuery($post['data_fim'] ?? null, '');
        if ($clienteId <= 0 || $inicio === '' || $fim === '') {
            return CrudHelper::jsonOk([
                'itens' => '<tr><td colspan="8" class="text-center text-muted">Selecione o gerador e o período.</td></tr>',
                'pagination' => '',
                'aviso' => '',
            ]);
        }
        if ($inicio > $fim) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $coletas = CdfLoteService::listar($clienteId, $inicio, $fim);
        $itens = '';
        foreach ($coletas as $c) {
            $itens .= self::linha($c);
        }
        if ($itens === '') {
            $itens = '<tr><td colspan="8" class="text-center text-muted">Nenhuma coleta finalizada neste período.</td></tr>';
        }
        if (count($coletas) >= 300) {
            $itens = '<tr><td colspan="8" class="text-center text-warning">Mostrando as 300 mais recentes. Encurte o período se faltar alguma.</td></tr>'.$itens;
        }

        return CrudHelper::jsonOk([
            'itens' => $itens,
            'pagination' => '',
            'aviso' => '<div class="alert alert-light border small mb-3">'.CrudHelper::e(CdfLoteService::avisoMensalidade($clienteId, $inicio)).'</div>',
        ]);
    }

    public static function gerar($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $result = (new SinirCdfService())->emitirCdfLote(self::ids($post));
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message']]);
    }

    public static function liberar($request, bool $liberar): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }

        $documento = (string)($post['documento'] ?? '');
        $result = CdfLoteService::definirLiberacao(self::ids($post), $documento, $liberar);
        if (!$result['ok']) {
            return CrudHelper::jsonError($result['message']);
        }

        return CrudHelper::jsonOk(['message' => $result['message']]);
    }

    public static function baixarMtr($request, int $id): Response
    {
        $c = EntityColeta::getById($id);
        $manNumero = $c ? trim((string)($c->sinir_man_numero ?? '')) : '';
        if (!$c || $c->status !== 'finalizada' || $manNumero === '') {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        $path = ColetaCdfService::absoluteMtrOficial($id);
        if ($path === null && SinirConfig::isEnabled()) {
            $baixado = (new SinirRecebimentoService())->baixarPdfManifesto($id);
            if (!$baixado['ok']) {
                $msg = htmlspecialchars((string)$baixado['message'], ENT_QUOTES, 'UTF-8');

                return new Response(200, '<p>Não foi possível obter o MTR oficial no SINIR: '.$msg.'</p>');
            }
            $path = ColetaCdfService::absoluteMtrOficial($id);
        }
        if ($path === null) {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        $bytes = file_get_contents($path);
        if ($bytes === false) {
            return new Response(404, View::render('erros/404', ['URL' => URL]));
        }

        $nome = 'mtr_'.preg_replace('/\D/', '', $manNumero).'.pdf';
        $resp = new Response(200, $bytes, 'application/pdf');
        $resp->addHeader('Content-Disposition', 'attachment; filename="'.$nome.'"');

        return $resp;
    }

    private static function linha(EntityColeta $c): string
    {
        $man = trim((string)($c->sinir_man_numero ?? ''));
        $manHtml = $man !== '' ? CrudHelper::e($man) : '—';
        $recebido = !empty($c->sinir_recebido_em)
            ? '<span class="badge bg-success">Recebido</span>'
            : '<span class="badge bg-secondary">Aguardando</span>';
        $cdf = trim((string)($c->sinir_cdf_codigo ?? ''));
        $cdfHtml = $cdf !== '' ? '#'.CrudHelper::e($cdf) : '—';
        $mtrLib = self::badgeLib(!empty($c->mtr_oficial_liberado_em), $man !== '');
        $cdfLib = self::badgeLib(!empty($c->cdf_liberado_em), $cdf !== '' || ColetaCdfService::temCdf($c));
        $baixarMtr = $man !== ''
            ? '<a class="btn btn-sm btn-outline-secondary" href="'.URL.'/painel/cdf/mtr/'.$c->id.'">MTR</a>'
            : '';
        $baixarCdf = ($cdf !== '' || ColetaCdfService::temCdf($c))
            ? '<a class="btn btn-sm btn-outline-primary" href="'.URL.'/painel/coletas/cdf/'.$c->id.'">CDF</a>'
            : '';
        $data = $c->data_coleta ? date('d/m/Y', strtotime((string)$c->data_coleta)) : '—';

        return '<tr>
            <td><input class="form-check-input cdf-check" type="checkbox" value="'.$c->id.'"></td>
            <td>#'.$c->id.'<div class="small text-muted">'.$data.'</div></td>
            <td>'.$manHtml.'</td>
            <td>'.$recebido.'</td>
            <td>'.$cdfHtml.'</td>
            <td>'.$mtrLib.'</td>
            <td>'.$cdfLib.'</td>
            <td class="text-end text-nowrap">'.$baixarMtr.' '.$baixarCdf.'</td>
        </tr>';
    }

    private static function badgeLib(bool $liberado, bool $existe): string
    {
        if (!$existe) {
            return '<span class="text-muted">—</span>';
        }

        return $liberado
            ? '<span class="badge bg-success">Liberado</span>'
            : '<span class="badge bg-warning text-dark">Retido</span>';
    }

    /** @param array<string,mixed> $post @return list<int> */
    private static function ids(array $post): array
    {
        $raw = $post['ids'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
    }

    private static function dataQuery(mixed $valor, string $padrao): string
    {
        $data = trim((string)$valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return $data;
        }

        return $padrao;
    }
}
