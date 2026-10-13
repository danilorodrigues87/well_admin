<?php

namespace App\Controller\Gerador;

use App\Common\GeradorScope;
use App\Common\Helpers\CrudHelper;
use App\Common\Helpers\CsrfHelper;
use App\Model\Entity\Cliente as EntityCliente;
use App\Service\GeradorPerfilService;
use App\Session\Gerador\Login as GeradorSession;
use App\Utils\View;

class Perfil extends Page
{
    public static function index($request): string
    {
        $session = GeradorSession::getData() ?? [];
        $clienteId = GeradorScope::getClienteId();
        $cliente = $clienteId > 0 ? EntityCliente::getById($clienteId) : null;

        $msg = '';
        $msgType = 'success';
        if (isset($_GET['senha'])) {
            $msg = 'Senha alterada com sucesso.';
        } elseif (isset($_GET['sinir'])) {
            $msg = 'Dados SINIR salvos com sucesso.';
        } elseif (isset($_GET['erro'])) {
            $msgType = 'danger';
            $msg = htmlspecialchars((string)($_GET['erro'] ?? 'Erro'), ENT_QUOTES, 'UTF-8');
        }

        $msgAlert = $msg !== ''
            ? '<div class="alert alert-'.$msgType.' alert-dismissible fade show" role="alert">'
                .htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
                .'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>'
            : '';

        $temToken = $cliente && trim((string)($cliente->sinir_integration_token ?? '')) !== '';
        $unidade = $cliente && $cliente->sinir_cod_unidade ? (string)$cliente->sinir_cod_unidade : '';

        $content = View::render('gerador/perfil/index', [
            'csrf_field' => CsrfHelper::field(),
            'msg_alert' => $msgAlert,
            'nome' => CrudHelper::e((string)($session['nome'] ?? '')),
            'email' => CrudHelper::e((string)($session['email'] ?? '')),
            'cliente_nome' => CrudHelper::e((string)($session['cliente_nome'] ?? '')),
            'sinir_cod_unidade' => CrudHelper::e($unidade),
            'sinir_token_placeholder' => $temToken
                ? 'Token já cadastrado — deixe em branco para manter'
                : 'Cole o Token API WS gerado no portal MTR',
            'sinir_token_hint' => $temToken
                ? 'Há um token salvo. Cole um novo apenas para substituir.'
                : 'Ainda sem token. Sem ele a operadora não consegue emitir o MTR no SINIR.',
        ]);

        return self::render('Meu perfil', $content, 'perfil');
    }

    public static function saveSenha($request): never
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            self::redirectErro($request, $err);
        }

        $session = GeradorSession::getData() ?? [];
        $userId = (int)($session['cliente_usuario_id'] ?? $session['id'] ?? 0);
        if ($userId <= 0) {
            self::redirectErro($request, 'Sessão inválida.');
        }

        $result = GeradorPerfilService::trocarSenha(
            $userId,
            (string)($post['senha_atual'] ?? ''),
            (string)($post['nova_senha'] ?? ''),
            (string)($post['confirmar_senha'] ?? '')
        );

        if (!$result['success']) {
            self::redirectErro($request, $result['message'] ?? 'Erro ao alterar senha.');
        }

        $request->getRouter()->redirect('/gerador/perfil?senha=1');
    }

    public static function saveSinir($request): never
    {
        $post = $request->getPostVars();
        if ($err = CrudHelper::requireCsrf($post)) {
            self::redirectErro($request, $err);
        }

        $result = GeradorPerfilService::salvarSinir(
            GeradorScope::getClienteId(),
            $post
        );

        if (!$result['success']) {
            self::redirectErro($request, $result['message'] ?? 'Erro ao salvar SINIR.');
        }

        $request->getRouter()->redirect('/gerador/perfil?sinir=1');
    }

    private static function redirectErro($request, string $msg): never
    {
        $request->getRouter()->redirect('/gerador/perfil?erro='.urlencode($msg));
    }
}
