<?php

namespace App\Controller\Admin;

use App\Common\Helpers\ChamadoAnexoHelper;
use App\Common\Helpers\ChamadoHelper;
use App\Common\Helpers\FormatHelper;
use App\Common\OperadoraScope;
use App\Http\Response;
use App\Model\Entity\Chamado;
use App\Model\Entity\ChamadoMensagem;
use App\Model\Entity\Usuario;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class Suporte extends Page
{
    public static function index($request): string
    {
        if (!Chamado::tabelaExiste()) {
            $content = '<div class="alert alert-warning">Módulo de suporte não configurado. Execute a migration 027.</div>';

            return self::getPage('Suporte', $content, 'suporte');
        }

        $content = View::render('admin/modules/suporte/index', [
            'categorias_json' => json_encode(ChamadoHelper::categoriasLista(), JSON_UNESCAPED_UNICODE),
            'status_json' => json_encode(ChamadoHelper::statusLista(), JSON_UNESCAPED_UNICODE),
        ]);

        return self::getPage('Suporte', $content, 'suporte', '<script src="'.URL.'/resources/js/suporte.js"></script>');
    }

    public static function getInfo($request): string
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Chamado::tabelaExiste()) {
            return json_encode(['success' => false, 'message' => 'Módulo indisponível.'], JSON_UNESCAPED_UNICODE);
        }

        $post = $request->getPostVars();
        $acao = (string)($post['acao'] ?? '');

        return match ($acao) {
            'listar' => self::listar($post),
            'abrir' => self::abrir($post),
            'detalhe' => self::detalhe($post),
            'responder' => self::responder($post),
            'fechar' => self::fechar($post),
            default => json_encode(['success' => false, 'message' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE),
        };
    }

    public static function downloadAnexo($request, int $idMensagem)
    {
        $msg = ChamadoMensagem::getById($idMensagem);
        if (!$msg || empty($msg->anexo_path)) {
            return new Response(404, 'Anexo não encontrado.');
        }
        $chamado = Chamado::getById((int)$msg->chamado_id);
        if (!$chamado) {
            return new Response(403, 'Acesso negado.');
        }
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        $isAdmin = !empty($userData['usuario']['is_admin']);
        if (!$isAdmin && (int)$chamado->usuario_id !== $usuarioId) {
            return new Response(403, 'Acesso negado.');
        }

        $abs = ChamadoAnexoHelper::caminhoAbsoluto((string)$msg->anexo_path);
        if ($abs === null) {
            return new Response(404, 'Arquivo não encontrado.');
        }
        $bin = file_get_contents($abs);
        if ($bin === false) {
            return new Response(500, 'Falha ao ler anexo.');
        }
        $mime = ChamadoAnexoHelper::mimePorArquivo($abs);
        $nome = basename((string)($msg->anexo_nome ?: $msg->anexo_path));
        $resp = new Response(200, $bin, $mime);
        $resp->addHeader('Content-Disposition', 'inline; filename="'.str_replace('"', '', $nome).'"');

        return $resp;
    }

    private static function listar(array $post): string
    {
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        $isAdmin = !empty($userData['usuario']['is_admin']);
        $operadoraId = OperadoraScope::getOperadoraId();

        $itens = [];
        foreach (Chamado::listForUser(
            $operadoraId,
            $usuarioId,
            $isAdmin,
            trim((string)($post['status'] ?? '')),
            trim((string)($post['busca'] ?? ''))
        ) as $ob) {
            $itens[] = self::resumoChamado($ob);
        }

        return json_encode(['success' => true, 'itens' => $itens], JSON_UNESCAPED_UNICODE);
    }

    private static function abrir(array $post): string
    {
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        if ($usuarioId <= 0) {
            return json_encode(['success' => false, 'message' => 'Sessão inválida.'], JSON_UNESCAPED_UNICODE);
        }

        $categoria = trim((string)($post['categoria'] ?? ''));
        $assunto = trim((string)($post['assunto'] ?? ''));
        $mensagem = trim((string)($post['mensagem'] ?? ''));

        if (!ChamadoHelper::categoriaValida($categoria)) {
            return json_encode(['success' => false, 'message' => 'Selecione uma categoria.'], JSON_UNESCAPED_UNICODE);
        }
        if ($assunto === '' || mb_strlen($assunto) > 160) {
            return json_encode(['success' => false, 'message' => 'Informe um assunto (até 160 caracteres).'], JSON_UNESCAPED_UNICODE);
        }
        if ($mensagem === '') {
            return json_encode(['success' => false, 'message' => 'Escreva a mensagem.'], JSON_UNESCAPED_UNICODE);
        }

        $operadoraId = OperadoraScope::getOperadoraId();
        $anexoPath = null;
        $anexoNome = null;
        if (!empty($_FILES['anexo']) && is_array($_FILES['anexo']) && ($_FILES['anexo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $saved = ChamadoAnexoHelper::salvarUpload($operadoraId, $_FILES['anexo']);
            if ($saved === null) {
                return json_encode(['success' => false, 'message' => 'Anexo inválido (imagem até 5 MB).'], JSON_UNESCAPED_UNICODE);
            }
            $anexoPath = $saved['relative'];
            $anexoNome = $saved['nome'];
        }

        $chamado = new Chamado();
        $chamado->operadora_id = $operadoraId;
        $chamado->usuario_id = $usuarioId;
        $chamado->categoria = $categoria;
        $chamado->assunto = $assunto;
        if (!$chamado->cadastrar()) {
            return json_encode(['success' => false, 'message' => 'Não foi possível abrir o chamado.'], JSON_UNESCAPED_UNICODE);
        }

        $msg = new ChamadoMensagem();
        $msg->chamado_id = $chamado->id;
        $msg->autor_tipo = 'usuario';
        $msg->autor_id = $usuarioId;
        $msg->mensagem = $mensagem;
        $msg->anexo_path = $anexoPath;
        $msg->anexo_nome = $anexoNome;
        if (!$msg->cadastrar()) {
            return json_encode(['success' => false, 'message' => 'Chamado criado, mas a mensagem falhou.'], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'success' => true,
            'message' => 'Chamado aberto: '.$chamado->numero,
            'id' => $chamado->id,
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function detalhe(array $post): string
    {
        $id = (int)($post['id'] ?? 0);
        $chamado = self::chamadoAutorizado($id);
        if (!$chamado) {
            return json_encode(['success' => false, 'message' => 'Chamado não encontrado.'], JSON_UNESCAPED_UNICODE);
        }

        return json_encode(['success' => true, 'chamado' => self::detalhePayload($chamado)], JSON_UNESCAPED_UNICODE);
    }

    private static function responder(array $post): string
    {
        $userData = SessionUser::getUserLogedData();
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        $isAdmin = !empty($userData['usuario']['is_admin']);
        $id = (int)($post['id'] ?? 0);
        $mensagem = trim((string)($post['mensagem'] ?? ''));

        $chamado = self::chamadoAutorizado($id);
        if (!$chamado) {
            return json_encode(['success' => false, 'message' => 'Chamado não encontrado.'], JSON_UNESCAPED_UNICODE);
        }
        if (!ChamadoHelper::podeResponder($chamado->status)) {
            return json_encode(['success' => false, 'message' => 'Chamado finalizado.'], JSON_UNESCAPED_UNICODE);
        }
        if ($mensagem === '') {
            return json_encode(['success' => false, 'message' => 'Escreva uma mensagem.'], JSON_UNESCAPED_UNICODE);
        }

        $operadoraId = OperadoraScope::getOperadoraId();
        $anexoPath = null;
        $anexoNome = null;
        if (!empty($_FILES['anexo']) && is_array($_FILES['anexo']) && ($_FILES['anexo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $saved = ChamadoAnexoHelper::salvarUpload($operadoraId, $_FILES['anexo']);
            if ($saved === null) {
                return json_encode(['success' => false, 'message' => 'Anexo inválido.'], JSON_UNESCAPED_UNICODE);
            }
            $anexoPath = $saved['relative'];
            $anexoNome = $saved['nome'];
        }

        $msg = new ChamadoMensagem();
        $msg->chamado_id = $chamado->id;
        $msg->autor_tipo = $isAdmin ? 'admin' : 'usuario';
        $msg->autor_id = $usuarioId;
        $msg->mensagem = $mensagem;
        $msg->anexo_path = $anexoPath;
        $msg->anexo_nome = $anexoNome;
        if (!$msg->cadastrar()) {
            return json_encode(['success' => false, 'message' => 'Falha ao enviar.'], JSON_UNESCAPED_UNICODE);
        }

        $novoStatus = $isAdmin ? 'aguardando_usuario' : 'em_andamento';
        $chamado->atualizarStatus($novoStatus);

        return json_encode([
            'success' => true,
            'message' => 'Mensagem enviada.',
            'chamado' => self::detalhePayload(Chamado::getById($id)),
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function fechar(array $post): string
    {
        $userData = SessionUser::getUserLogedData();
        if (empty($userData['usuario']['is_admin'])) {
            return json_encode(['success' => false, 'message' => 'Somente administradores.'], JSON_UNESCAPED_UNICODE);
        }
        $id = (int)($post['id'] ?? 0);
        $chamado = self::chamadoAutorizado($id);
        if (!$chamado) {
            return json_encode(['success' => false, 'message' => 'Chamado não encontrado.'], JSON_UNESCAPED_UNICODE);
        }
        $chamado->atualizarStatus('fechado');

        return json_encode(['success' => true, 'message' => 'Chamado fechado.'], JSON_UNESCAPED_UNICODE);
    }

    private static function chamadoAutorizado(int $id): ?Chamado
    {
        $chamado = Chamado::getById($id);
        if (!$chamado) {
            return null;
        }
        $userData = SessionUser::getUserLogedData();
        $isAdmin = !empty($userData['usuario']['is_admin']);
        $usuarioId = (int)($userData['usuario']['id'] ?? 0);
        if (!$isAdmin && (int)$chamado->usuario_id !== $usuarioId) {
            return null;
        }

        return $chamado;
    }

    private static function resumoChamado(Chamado $ob): array
    {
        return [
            'id' => $ob->id,
            'numero' => $ob->numero,
            'categoria' => $ob->categoria,
            'categoria_label' => ChamadoHelper::labelCategoria($ob->categoria),
            'assunto' => $ob->assunto,
            'status' => $ob->status,
            'status_label' => ChamadoHelper::labelStatus($ob->status),
            'created_at' => FormatHelper::dateTimeBr($ob->created_at),
            'updated_at' => FormatHelper::dateTimeBr($ob->updated_at),
        ];
    }

    private static function detalhePayload(?Chamado $chamado): array
    {
        if (!$chamado) {
            return [];
        }
        $userData = SessionUser::getUserLogedData();
        $isAdmin = !empty($userData['usuario']['is_admin']);

        $mensagens = [];
        foreach (ChamadoMensagem::listarPorChamado($chamado->id) as $m) {
            $autorNome = 'Usuário';
            if ($m->autor_tipo === 'admin') {
                $autorNome = 'Suporte Well';
            } else {
                $u = Usuario::getById((int)$m->autor_id);
                if ($u) {
                    $autorNome = $u->nome;
                }
            }
            $mensagens[] = [
                'id' => $m->id,
                'autor_tipo' => $m->autor_tipo,
                'autor_nome' => $autorNome,
                'mensagem' => $m->mensagem,
                'anexo' => !empty($m->anexo_path),
                'anexo_url' => !empty($m->anexo_path) ? URL.'/painel/suporte/anexo/'.$m->id : null,
                'created_at' => FormatHelper::dateTimeBr($m->created_at),
            ];
        }

        $payload = self::resumoChamado($chamado);
        $payload['pode_responder'] = ChamadoHelper::podeResponder($chamado->status);
        $payload['pode_fechar'] = $isAdmin && ChamadoHelper::podeResponder($chamado->status);
        $payload['mensagens'] = $mensagens;

        return $payload;
    }
}
