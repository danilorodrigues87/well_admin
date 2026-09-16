<?php

namespace App\Controller\Admin;

use App\Common\CompanyConfig;
use App\Common\Helpers\CsrfHelper;
use App\Common\OperadoraScope;
use App\Service\TermosDeUsoService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class TermosDeUso extends Page
{
    public static function index($request): string
    {
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        $aceitou = TermosDeUsoService::usuarioAceitouVersaoAtual($usuarioId);
        $operadoraId = OperadoraScope::getOperadoraId();

        $content = View::render('admin/modules/termos_uso/index', [
            'versao' => TermosDeUsoService::VERSAO,
            'data_versao' => TermosDeUsoService::DATA_VERSAO,
            'company_name' => htmlspecialchars(CompanyConfig::name($operadoraId), ENT_QUOTES, 'UTF-8'),
            'url_privacidade' => URL.'/privacidade',
            'aceitou' => $aceitou ? '1' : '0',
            'bloco_status' => self::blocoStatus($aceitou),
            'csrf_field' => CsrfHelper::field(),
        ]);

        return self::getPage('Termos de Uso', $content, 'termos_de_uso', View::render('admin/modules/termos_uso/scripts'));
    }

    public static function aceitaTermo($request): string
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            return '0';
        }
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        if ($usuarioId <= 0) {
            return '0';
        }

        return TermosDeUsoService::registrarAceiteAdmin($usuarioId) ? '1' : '0';
    }

    private static function blocoStatus(bool $aceitou): string
    {
        if ($aceitou) {
            return '<div class="alert alert-success">Termos versão <strong>'
                .htmlspecialchars(TermosDeUsoService::VERSAO, ENT_QUOTES, 'UTF-8')
                .'</strong> já aceitos.</div>';
        }

        return '<div class="form-check my-4">'
            .'<input onchange="ativaBtnTermos()" class="form-check-input" type="checkbox" id="termo_uso">'
            .'<label class="form-check-label" for="termo_uso">'
            .'Declaro estar ciente e concordar com este Termo de Uso (versão '
            .htmlspecialchars(TermosDeUsoService::VERSAO, ENT_QUOTES, 'UTF-8')
            .'), assumindo responsabilidade pelo uso adequado do painel e dos dados tratados.'
            .'</label></div>'
            .'<button disabled onclick="aceitarTermos()" id="btn-termo" class="btn btn-primary mb-3">Aceitar e continuar</button>';
    }
}
