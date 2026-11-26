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
        $filtros = self::filtros($query);
        $clienteId = (int)($query['cliente_id'] ?? 0);
        $tipoId = (int)$filtros['tipo_residuo_id'];
        $modo = (string)$filtros['modo'];

        $aviso = '';
        $linhas = '';
        $totalLinha = '';
        $tituloResultado = '';
        $cliente = $clienteId > 0 ? EntityCliente::getById($clienteId) : null;
        $tipo = $tipoId > 0 ? EntityTipoResiduo::getById($tipoId) : null;
        if ($clienteId > 0 && !$cliente) {
            $aviso = 'Cliente não encontrado.';
        } elseif ($tipoId > 0 && !$tipo) {
            $aviso = 'Tipo de resíduo não encontrado.';
        } else {
            $result = RelatorioService::coletasPorCliente($clienteId, $filtros);
            if (!$result['ok']) {
                $aviso = $result['message'];
            } else {
                $nomeResiduo = $tipo ? $tipo->nome : 'Resíduos';
                $tituloResultado = mb_strtoupper($nomeResiduo, 'UTF-8')
                    .' GERADO POR EMPRESA — '.self::rotuloPeriodo($filtros, $result);
                foreach ($result['rows'] as $row) {
                    $linhas .= '<tr>
                        <td>'.CrudHelper::e((string)$row['empresa']).'</td>
                        <td class="text-end">'.CrudHelper::e(RelatorioService::kgExibicao((float)$row['kg'])).'</td>
                    </tr>';
                }
                if ($linhas === '') {
                    $linhas = '<tr><td colspan="2" class="text-center text-muted">Nenhuma coleta finalizada no período.</td></tr>';
                } else {
                    $totalLinha = '<tr>
                        <td class="fw-bold">TOTAL GERAL</td>
                        <td class="text-end fw-bold text-success fs-5">'.CrudHelper::e(RelatorioService::kgExibicao($result['total_kg'])).'</td>
                    </tr>';
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
            'titulo_resultado' => CrudHelper::e($tituloResultado),
            'linhas' => $linhas,
            'total_linha' => $totalLinha,
            'mostrar_tabela' => $linhas !== '' ? '' : 'd-none',
            'mostrar_csv' => $aviso === '' && $totalLinha !== '' ? '' : 'd-none',
        ]);

        return self::getPage('Resíduos gerados', $content, 'residuos_gerados');
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
        $filtros = self::filtros($post);
        $cliente = $clienteId > 0 ? EntityCliente::getById($clienteId) : null;
        $tipoId = (int)$filtros['tipo_residuo_id'];
        $tipo = $tipoId > 0 ? EntityTipoResiduo::getById($tipoId) : null;
        if ($clienteId > 0 && !$cliente) {
            http_response_code(400);
            echo 'Cliente não encontrado.';
            exit;
        }
        if ($tipoId > 0 && !$tipo) {
            http_response_code(400);
            echo 'Tipo de resíduo não encontrado.';
            exit;
        }
        $result = RelatorioService::coletasPorCliente($clienteId, $filtros);
        if (!$result['ok']) {
            http_response_code(400);
            echo $result['message'];
            exit;
        }
        $csv = RelatorioService::csvColetasPorCliente($clienteId, $filtros);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="residuos-gerados-'.date('Y-m-d_His').'.csv"');
        echo "\xEF\xBB\xBF".$csv;
        exit;
    }

    /** @param array<string, mixed> $filtros @param array{inicio:string,fim:string} $result */
    private static function rotuloPeriodo(array $filtros, array $result): string
    {
        $modo = (string)($filtros['modo'] ?? 'mes');
        if ($modo === 'ano') {
            return (string)(int)$filtros['ano'];
        }
        $inicio = RelatorioService::mesExibicao(substr((string)$result['inicio'], 0, 7));
        if ($modo === 'mes') {
            return $inicio;
        }

        return $inicio.' a '.RelatorioService::mesExibicao(substr((string)$result['fim'], 0, 7));
    }

    /** @param array<string, mixed> $input */
    private static function filtros(array $input): array
    {
        $modo = (string)($input['modo'] ?? 'mes');
        if (!in_array($modo, ['mes', 'intervalo', 'ano'], true)) {
            $modo = 'mes';
        }
        $mesAtual = date('Y-m');
        $competencia = trim((string)($input['competencia'] ?? ''));
        $inicio = trim((string)($input['competencia_ini'] ?? ''));
        $fim = trim((string)($input['competencia_fim'] ?? ''));
        $ano = trim((string)($input['ano'] ?? ''));

        return [
            'modo' => $modo,
            'competencia' => $competencia !== '' ? $competencia : $mesAtual,
            'competencia_ini' => $inicio !== '' ? $inicio : $mesAtual,
            'competencia_fim' => $fim !== '' ? $fim : $mesAtual,
            'ano' => $ano !== '' ? $ano : date('Y'),
            'tipo_residuo_id' => (int)($input['tipo_residuo_id'] ?? 0),
        ];
    }

    private static function opcoesClientes(int $selecionado): string
    {
        $html = '<option value="0"'.($selecionado === 0 ? ' selected' : '').'>Todos</option>';
        foreach (EntityCliente::list("c.status = 'ativo'", [], '500') as $cliente) {
            $sel = $cliente->id === $selecionado ? ' selected' : '';
            $html .= '<option value="'.$cliente->id.'"'.$sel.'>'.CrudHelper::e($cliente->nome_fantasia).'</option>';
        }

        return $html;
    }

    private static function opcoesTipos(int $selecionado): string
    {
        $html = '<option value="0"'.($selecionado === 0 ? ' selected' : '').'>Todos</option>';
        foreach (EntityTipoResiduo::list('t.ativo = 1', [], '500') as $tipo) {
            $sel = $tipo->id === $selecionado ? ' selected' : '';
            $html .= '<option value="'.$tipo->id.'"'.$sel.'>'.CrudHelper::e($tipo->nome).'</option>';
        }

        return $html;
    }
}
