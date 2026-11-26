<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Service\RelatorioService;

class RelatorioCliente extends Page
{
    public static function index($request): string
    {
        $query = $request->getQueryParams();
        $clienteId = (int)($query['cliente_id'] ?? 0);
        $tipoId = (int)($query['tipo_residuo_id'] ?? 0);
        $modo = (string)($query['modo'] ?? 'mes');
        $filtros = self::filtros($query);
        $buscar = $clienteId > 0;

        $aviso = '';
        $linhas = '';
        $linhasResumo = '';
        $resumo = '';
        $tituloTon = ($filtros['modo'] ?? '') === 'ano'
            ? 'Quantidade '.(int)($filtros['ano'] ?? 0).' (t)'
            : 'Toneladas';
        if ($buscar) {
            $cliente = EntityCliente::getById($clienteId);
            if (!$cliente) {
                $aviso = 'Cliente não encontrado.';
            } else {
                $result = RelatorioService::coletasPorCliente($clienteId, $filtros);
                if (!$result['ok']) {
                    $aviso = $result['message'];
                } else {
                    $resumo = $cliente->nome_fantasia
                        .' · '.date('d/m/Y', strtotime($result['inicio']))
                        .' a '.date('d/m/Y', strtotime($result['fim']))
                        .' · '.number_format($result['total_kg'], 3, ',', '.')
                        .' kg · '.number_format($result['total_kg'] / 1000, 3, ',', '.')
                        .' t · '.$result['total_coletas'].' coleta(s)';
                    $agrupado = [];
                    foreach ($result['rows'] as $row) {
                        $chave = (string)$row['tipo_nome'].'|'.(string)($row['classe_nome'] ?? '').'|'.(string)($row['grupo_codigo'] ?? '');
                        if (!isset($agrupado[$chave])) {
                            $agrupado[$chave] = $row;
                            $agrupado[$chave]['kg'] = 0.0;
                            $agrupado[$chave]['coletas'] = 0;
                        }
                        $agrupado[$chave]['kg'] += (float)$row['kg'];
                        $agrupado[$chave]['coletas'] += (int)$row['coletas'];
                    }
                    foreach ($agrupado as $row) {
                        $linhasResumo .= self::linhaResiduo($row, false);
                    }
                    if ($linhasResumo !== '') {
                        $linhasResumo .= '<tr class="fw-bold">
                            <td colspan="2">Total</td>
                            <td class="text-end">'.(int)$result['total_coletas'].'</td>
                            <td class="text-end">'.number_format($result['total_kg'], 3, ',', '.').'</td>
                            <td class="text-end">'.number_format($result['total_kg'] / 1000, 3, ',', '.').'</td>
                        </tr>';
                    }
                    foreach ($result['rows'] as $row) {
                        $linhas .= self::linhaResiduo($row, true);
                    }
                    if ($linhas === '') {
                        $mensagem = 'Nenhuma coleta finalizada no período.';
                        $linhasResumo = '<tr><td colspan="5" class="text-center text-muted">'.CrudHelper::e($mensagem).'</td></tr>';
                        $linhas = '<tr><td colspan="6" class="text-center text-muted">'.CrudHelper::e($mensagem).'</td></tr>';
                    }
                }
            }
        }

        $content = \App\Utils\View::render('admin/modules/relatorio_cliente/index', [
            'csrf_field' => CsrfHelper::field(),
            'clientes_options' => self::opcoesClientes($clienteId),
            'tipos_options' => self::opcoesTipos($tipoId),
            'modo' => in_array($modo, ['mes', 'intervalo', 'ano'], true) ? $modo : 'mes',
            'competencia' => CrudHelper::e((string)($query['competencia'] ?? date('Y-m'))),
            'competencia_ini' => CrudHelper::e((string)($query['competencia_ini'] ?? date('Y-m'))),
            'competencia_fim' => CrudHelper::e((string)($query['competencia_fim'] ?? date('Y-m'))),
            'ano' => CrudHelper::e((string)($query['ano'] ?? date('Y'))),
            'aviso' => $aviso !== '' ? '<div class="alert alert-warning">'.CrudHelper::e($aviso).'</div>' : '',
            'resumo' => $resumo !== '' ? '<p class="mb-2"><strong>'.CrudHelper::e($resumo).'</strong></p>' : '',
            'titulo_ton' => CrudHelper::e($tituloTon),
            'linhas_resumo' => $linhasResumo,
            'linhas' => $linhas,
            'mostrar_tabela' => $linhas !== '' ? '' : 'd-none',
            'mostrar_csv' => $buscar && $aviso === '' && $linhas !== '' ? '' : 'd-none',
        ]);

        return self::getPage('Coletas por cliente', $content, 'relatorio_cliente');
    }

    public static function exportCsv($request): never
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            http_response_code(400);
            echo $err;
            exit;
        }

        $clienteId = (int)($post['cliente_id'] ?? 0);
        $cliente = $clienteId > 0 ? EntityCliente::getById($clienteId) : null;
        $filtros = self::filtros($post);
        $result = RelatorioService::coletasPorCliente($clienteId, $filtros);
        if (!$cliente || !$result['ok']) {
            http_response_code(400);
            echo !$cliente ? 'Cliente não encontrado.' : $result['message'];
            exit;
        }
        $csv = RelatorioService::csvColetasPorCliente($clienteId, $filtros, $cliente->nome_fantasia);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="coletas-cliente-'.date('Y-m-d_His').'.csv"');
        echo "\xEF\xBB\xBF".$csv;
        exit;
    }

    /** @param array<string, mixed> $row */
    private static function linhaResiduo(array $row, bool $comMes): string
    {
        $kg = (float)$row['kg'];
        $classe = RelatorioService::classificacaoExibicao(
            (string)($row['classe_nome'] ?? ''),
            (string)($row['grupo_codigo'] ?? '')
        );
        $mes = $comMes
            ? '<td>'.RelatorioService::mesExibicao((string)$row['competencia']).'</td>'
            : '';

        return '<tr>'
            .$mes
            .'<td>'.CrudHelper::e((string)$row['tipo_nome']).'</td>'
            .'<td>'.CrudHelper::e($classe).'</td>'
            .'<td class="text-end">'.(int)$row['coletas'].'</td>'
            .'<td class="text-end">'.number_format($kg, 3, ',', '.').'</td>'
            .'<td class="text-end">'.number_format($kg / 1000, 3, ',', '.').'</td>'
            .'</tr>';
    }

    /** @param array<string, mixed> $input */
    private static function filtros(array $input): array
    {
        return [
            'modo' => (string)($input['modo'] ?? 'mes'),
            'competencia' => (string)($input['competencia'] ?? ''),
            'competencia_ini' => (string)($input['competencia_ini'] ?? ''),
            'competencia_fim' => (string)($input['competencia_fim'] ?? ''),
            'ano' => (string)($input['ano'] ?? ''),
            'tipo_residuo_id' => (int)($input['tipo_residuo_id'] ?? 0),
        ];
    }

    private static function opcoesClientes(int $selecionado): string
    {
        $html = '<option value="">Selecione</option>';
        foreach (EntityCliente::list("c.status = 'ativo'", [], '500') as $cliente) {
            $sel = $cliente->id === $selecionado ? ' selected' : '';
            $html .= '<option value="'.$cliente->id.'"'.$sel.'>'.CrudHelper::e($cliente->nome_fantasia).'</option>';
        }

        return $html;
    }

    private static function opcoesTipos(int $selecionado): string
    {
        $html = '<option value="0">Todos</option>';
        foreach (EntityTipoResiduo::list('t.ativo = 1', [], '500') as $tipo) {
            $sel = $tipo->id === $selecionado ? ' selected' : '';
            $html .= '<option value="'.$tipo->id.'"'.$sel.'>'.CrudHelper::e($tipo->nome).'</option>';
        }

        return $html;
    }
}
