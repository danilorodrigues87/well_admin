<?php

namespace App\Controller\Gerador;

use App\Common\CompanyConfig;
use App\Common\GeradorScope;
use App\Common\Helpers\CsrfHelper;
use App\Service\TermosDeUsoService;
use App\Session\Gerador\Login as GeradorSession;
use App\Utils\View;

class TermosDeUso
{
    public static function index($request): string
    {
        $session = GeradorSession::getData() ?? [];
        $clienteUsuarioId = (int)($session['cliente_usuario_id'] ?? 0);
        $aceitou = TermosDeUsoService::clienteUsuarioAceitouVersaoAtual($clienteUsuarioId);
        $operadoraId = GeradorScope::getOperadoraId();

        $content = View::render('gerador/termos/index', [
            'versao' => TermosDeUsoService::VERSAO,
            'company_name' => htmlspecialchars(CompanyConfig::name($operadoraId), ENT_QUOTES, 'UTF-8'),
            'url_privacidade' => URL.'/privacidade',
            'aceitou' => $aceitou,
            'bloco_status' => self::blocoStatus($aceitou),
            'csrf_field' => CsrfHelper::field(),
        ]);

        return View::render('gerador/auth_page', [
            'title' => 'Termos de Uso — Portal Gerador',
            'content' => $content,
            'company_name' => CompanyConfig::name($operadoraId),
        ]);
    }

    public static function aceitaTermo($request): string
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            return '0';
        }
        $session = GeradorSession::getData() ?? [];
        $clienteUsuarioId = (int)($session['cliente_usuario_id'] ?? 0);
        if ($clienteUsuarioId <= 0) {
            return '0';
        }

        return TermosDeUsoService::registrarAceiteGerador($clienteUsuarioId) ? '1' : '0';
    }

    private static function blocoStatus(bool $aceitou): string
    {
        if ($aceitou) {
            return '<div class="alert alert-success small">Termos já aceitos. <a href="'.URL.'/gerador">Ir ao portal</a></div>';
        }

        return '<div class="form-check my-3">'
            .'<input class="form-check-input" type="checkbox" id="termo_uso" onchange="document.getElementById(\'btn-termo\').disabled=!this.checked">'
            .'<label class="form-check-label small" for="termo_uso">Li e concordo com os termos de uso do portal.</label>'
            .'</div>'
            .'<button disabled id="btn-termo" class="btn btn-primary w-100" onclick="aceitarTermosGerador()">Aceitar e continuar</button>';
    }
}
