<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Service\OperadoraConfigService;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class Operadora extends Page
{
    public static function index($request): string
    {
        $session = SessionUser::getUserLogedData()['usuario'] ?? [];
        if (empty($session['is_admin'])) {
            return self::getPage('Operadora', '<div class="alert alert-danger">Acesso restrito a administradores.</div>', 'operadora');
        }

        $op = OperadoraConfigService::getOperadoraAtual();
        if (!$op) {
            return self::getPage('Operadora', '<div class="alert alert-danger">Operadora não encontrada.</div>', 'operadora');
        }

        $msg = '';
        $msgType = 'success';
        if (isset($_GET['saved'])) {
            $msg = 'Configurações salvas com sucesso.';
        } elseif (isset($_GET['erro'])) {
            $msgType = 'danger';
            $msg = htmlspecialchars((string)($_GET['erro'] ?? 'Erro'), ENT_QUOTES, 'UTF-8');
        }

        $msgAlert = $msg !== ''
            ? '<div class="alert alert-'.$msgType.' alert-dismissible fade show" role="alert">'
                .htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
                .'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
            : '';

        $content = View::render('admin/modules/operadora/index', [
            'csrf_field' => CsrfHelper::field(),
            'msg_alert' => $msgAlert,
            'nome_fantasia' => CrudHelper::e((string)$op['nome_fantasia']),
            'nome_curto' => CrudHelper::e((string)$op['nome_curto']),
            'razao_social' => CrudHelper::e((string)$op['razao_social']),
            'cnpj' => CrudHelper::e((string)$op['cnpj']),
            'transportador_nome' => CrudHelper::e((string)$op['transportador_nome']),
            'transportador_cnpj' => CrudHelper::e((string)$op['transportador_cnpj']),
            'destinador_nome' => CrudHelper::e((string)$op['destinador_nome']),
            'destinador_cnpj' => CrudHelper::e((string)$op['destinador_cnpj']),
            'destinador_endereco' => CrudHelper::e((string)$op['destinador_endereco']),
            'destinador_telefone' => CrudHelper::e((string)$op['destinador_telefone']),
            'destinador_responsavel' => CrudHelper::e((string)$op['destinador_responsavel']),
        ]);

        return self::getPage('Configurações da Operadora', $content, 'operadora');
    }

    public static function save($request): never
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            self::redirectErro($request, $err);
        }

        $session = SessionUser::getUserLogedData()['usuario'] ?? [];
        if (empty($session['is_admin'])) {
            self::redirectErro($request, 'Acesso negado.');
        }

        OperadoraConfigService::saveOperadora($post);
        $request->getRouter()->redirect('/painel/operadora?saved=1');
    }

    private static function redirectErro($request, string $msg): never
    {
        $request->getRouter()->redirect('/painel/operadora?erro='.urlencode($msg));
    }
}
