<?php

namespace App\Controller\Admin;

use App\Common\Helpers\ContratoTemplateHelper;
use App\Common\Helpers\CsrfHelper;
use App\Model\Entity\OperadoraConfig;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class ConfigContrato extends Page
{
    public static function index($request): string
    {
        $userData = SessionUser::getUserLogedData();
        if (empty($userData['usuario']['is_admin'])) {
            $request->getRouter()->redirect('/painel');
        }

        $modelo = OperadoraConfig::get('modelo_contrato_html') ?? '';
        if (trim($modelo) === '') {
            $modelo = ContratoTemplateHelper::modeloPadrao();
        }

        $content = View::render('admin/modules/config/contrato', [
            'modelo_html' => htmlspecialchars($modelo, ENT_QUOTES, 'UTF-8'),
            'csrf_field' => CsrfHelper::field(),
        ]);

        return self::getPage('Modelo de Contrato', $content, 'operadora');
    }

    public static function save($request): void
    {
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/config/contrato?erro=csrf');
            exit;
        }
        $userData = SessionUser::getUserLogedData();
        if (empty($userData['usuario']['is_admin'])) {
            header('Location: '.URL.'/painel');
            exit;
        }

        $acao = (string)($post['acao'] ?? '');
        if ($acao === 'restaurar') {
            OperadoraConfig::set('modelo_contrato_html', '');
        } else {
            $html = (string)($post['modelo_html'] ?? '');
            OperadoraConfig::set('modelo_contrato_html', $html);
        }

        header('Location: '.URL.'/painel/config/contrato?ok=1');
        exit;
    }
}
