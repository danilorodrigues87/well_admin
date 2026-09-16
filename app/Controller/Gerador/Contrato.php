<?php

namespace App\Controller\Gerador;

use App\Common\CompanyConfig;
use App\Common\GeradorScope;
use App\Common\Helpers\CsrfHelper;
use App\Model\Entity\ClienteContrato;
use App\Service\ContratoClienteService;
use App\Session\Gerador\Login as GeradorSession;
use App\Utils\View;

class Contrato
{
    public static function index($request): string
    {
        $session = GeradorSession::getData() ?? [];
        $clienteId = (int)($session['cliente_id'] ?? 0);
        $contrato = ClienteContrato::getPendenteAssinatura($clienteId);

        if (!$contrato) {
            $request->getRouter()->redirect('/gerador');
        }

        $html = ContratoClienteService::renderHtml($contrato->id);
        $content = View::render('gerador/contrato/index', [
            'contrato_html' => $html,
            'numero' => htmlspecialchars($contrato->numero, ENT_QUOTES, 'UTF-8'),
            'csrf_field' => CsrfHelper::field(),
            'contrato_id' => $contrato->id,
        ]);

        return View::render('gerador/auth_page', [
            'title' => 'Contrato comercial — Portal Gerador',
            'content' => $content,
            'company_name' => CompanyConfig::name(GeradorScope::getOperadoraId()),
        ]);
    }

    public static function aceita($request): string
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            return '0';
        }
        $session = GeradorSession::getData() ?? [];
        $clienteId = (int)($session['cliente_id'] ?? 0);
        $clienteUsuarioId = (int)($session['cliente_usuario_id'] ?? 0);
        $contrato = ClienteContrato::getPendenteAssinatura($clienteId);
        if (!$contrato) {
            return '0';
        }

        return ContratoClienteService::registrarAssinaturaGerador($contrato->id, $clienteUsuarioId) ? '1' : '0';
    }
}
