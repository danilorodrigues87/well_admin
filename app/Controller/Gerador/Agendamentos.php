<?php

namespace App\Controller\Gerador;

use App\Common\GeradorScope;
use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Common\Helpers\FormatHelper;
use App\Model\Entity\ColetaSolicitacao;
use App\Service\ColetaSolicitacaoService;
use App\Session\Gerador\Login as GeradorSession;
use App\Utils\View;
use InvalidArgumentException;

class Agendamentos extends Page
{
    public static function index($request): string
    {
        $clienteId = GeradorScope::getClienteId();
        $cota = ColetaSolicitacaoService::resumoCota($clienteId);
        $lista = ColetaSolicitacao::listByCliente($clienteId, 30);

        $rows = '';
        foreach ($lista as $s) {
            $status = FormatHelper::statusSolicitacaoBadge($s->status);
            $tipo = $s->tipo === 'extra' ? '<span class="badge bg-warning text-dark">Extra</span>' : '<span class="badge bg-success">Inclusa</span>';
            $cancel = $s->status === 'pendente'
                ? '<button type="button" class="btn btn-sm btn-outline-danger btn-cancel-solic" data-id="'.(int)$s->id.'">Cancelar</button>'
                : '';
            $rows .= '<tr>
                <td>'.FormatHelper::dateBr($s->data_desejada).'</td>
                <td>'.$tipo.'</td>
                <td>'.$status.'</td>
                <td class="small">'.htmlspecialchars(mb_strimwidth((string)($s->motivo_gerador ?? ''), 0, 60, '…'), ENT_QUOTES, 'UTF-8').'</td>
                <td class="text-end">'.$cancel.'</td>
            </tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="text-muted text-center py-3">Nenhuma solicitação ainda.</td></tr>';
        }

        $content = View::render('gerador/agendamentos/index', [
            'csrf_field' => CsrfHelper::field(),
            'data_min' => date('Y-m-d', strtotime('+2 days')),
            'cota_limite' => (int)$cota['limite'],
            'cota_usadas' => (int)$cota['usadas'],
            'cota_pendentes' => (int)$cota['pendentes'],
            'cota_restantes' => (int)$cota['restantes'],
            'cota_periodo' => htmlspecialchars((string)$cota['periodo_label'], ENT_QUOTES, 'UTF-8'),
            'solicitacoes_rows' => $rows,
        ]);

        $scripts = '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>'
            .'<script src="'.URL.'/resources/js/gerador-agendamentos.js?v=20260921"></script>';

        return self::render('Agendamentos', $content, 'agendamentos', $scripts);
    }

    public static function post($request): string
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            return CrudHelper::jsonError($err);
        }
        $session = GeradorSession::getData() ?? [];
        $acao = trim((string)($post['acao'] ?? ''));

        try {
            if ($acao === 'criar') {
                $id = ColetaSolicitacaoService::criar(
                    GeradorScope::getClienteId(),
                    (int)($session['cliente_usuario_id'] ?? 0),
                    trim((string)($post['data_desejada'] ?? '')),
                    trim((string)($post['motivo'] ?? ''))
                );

                return CrudHelper::jsonOk(['message' => 'Solicitação enviada. Aguarde aprovação da equipe.', 'id' => $id]);
            }
            if ($acao === 'cancelar') {
                ColetaSolicitacaoService::cancelar((int)($post['id'] ?? 0), GeradorScope::getClienteId());

                return CrudHelper::jsonOk(['message' => 'Solicitação cancelada.']);
            }
        } catch (InvalidArgumentException $e) {
            return CrudHelper::jsonError($e->getMessage());
        }

        return CrudHelper::jsonError('Ação inválida.');
    }
}
