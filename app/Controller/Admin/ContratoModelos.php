<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CsrfHelper;
use App\Model\Entity\ContratoModelo;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class ContratoModelos extends Page
{
    private static function requireAdmin(): void
    {
        $user = SessionUser::getUserLogedData();
        if (empty($user['usuario']['is_admin'])) {
            header('Location: '.URL.'/painel/contratos');
            exit;
        }
    }

    public static function index($request): string
    {
        self::requireAdmin();

        if (!ContratoModelo::tabelaExiste()) {
            $content = '<div class="container-fluid px-4 mt-4"><div class="alert alert-warning">'
                .'Execute a migration <code>050_contrato_modelos.sql</code> e o seed '
                .'<code>php database/scripts/apply_migration_050.php</code>.</div></div>';

            return self::getPage('Modelos de contrato', $content, 'contratos');
        }

        $rows = '';
        foreach (ContratoModelo::listLatestBySlug() as $m) {
            $ativo = $m->ativo ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>';
            $rows .= '<tr>'
                .'<td><code>'.htmlspecialchars($m->slug, ENT_QUOTES, 'UTF-8').'</code></td>'
                .'<td>'.htmlspecialchars($m->nome, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>v'.(int)$m->versao.'</td>'
                .'<td>'.htmlspecialchars($m->pricing_variant, ENT_QUOTES, 'UTF-8').'</td>'
                .'<td>'.$ativo.'</td>'
                .'<td class="text-end">'
                .'<a class="btn btn-sm btn-outline-primary" href="'.URL.'/painel/contratos/modelos/'.$m->id.'">Editar</a>'
                .'</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum modelo. Rode o seed.</td></tr>';
        }

        $content = View::render('admin/modules/contratos/modelos/index', [
            'rows' => $rows,
            'placeholders_help' => self::placeholdersHelpHtml(),
            'csrf_reimport' => CsrfHelper::field(),
        ]);

        return self::getPage('Modelos jurídicos', $content, 'contratos');
    }

    public static function edit($request, int $id): string
    {
        self::requireAdmin();
        $modelo = ContratoModelo::getById($id);
        if (!$modelo) {
            $request->getRouter()->redirect('/painel/contratos/modelos');
        }

        $pricingOpts = '';
        foreach (['FRANQUIA_KG', 'TABELA_TONELADA_CACAMBA', 'VALOR_GLOBAL_OBRA'] as $pv) {
            $sel = $modelo->pricing_variant === $pv ? ' selected' : '';
            $pricingOpts .= '<option value="'.$pv.'"'.$sel.'>'.$pv.'</option>';
        }

        $qp = $request->getQueryParams();
        $alerta = '';
        if (($qp['ok'] ?? '') === '1') {
            $alerta = '<div class="alert alert-success">Modelo salvo.</div>';
        } elseif (($qp['erro'] ?? '') === 'csrf') {
            $alerta = '<div class="alert alert-danger">Sessão expirada.</div>';
        } elseif (($qp['erro'] ?? '') === 'campos') {
            $alerta = '<div class="alert alert-danger">Preencha nome, título e corpo HTML.</div>';
        }

        $content = View::render('admin/modules/contratos/modelos/form', [
            'alerta' => $alerta,
            'id' => $id,
            'slug' => htmlspecialchars($modelo->slug, ENT_QUOTES, 'UTF-8'),
            'nome' => htmlspecialchars($modelo->nome, ENT_QUOTES, 'UTF-8'),
            'versao' => (int)$modelo->versao,
            'titulo' => htmlspecialchars($modelo->titulo, ENT_QUOTES, 'UTF-8'),
            'body_html' => htmlspecialchars($modelo->body_html, ENT_QUOTES, 'UTF-8'),
            'pricing_options' => $pricingOpts,
            'ativo_checked' => $modelo->ativo ? ' checked' : '',
            'csrf_field' => CsrfHelper::field(),
            'placeholders_help' => self::placeholdersHelpHtml(),
        ]);

        return self::getPage('Editar modelo — '.$modelo->slug, $content, 'contratos');
    }

    public static function salvar($request, int $id): void
    {
        self::requireAdmin();
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/contratos/modelos/'.$id.'?erro=csrf');
            exit;
        }
        $modelo = ContratoModelo::getById($id);
        if (!$modelo) {
            header('Location: '.URL.'/painel/contratos/modelos');
            exit;
        }

        $nome = trim((string)($post['nome'] ?? ''));
        $titulo = trim((string)($post['titulo'] ?? ''));
        $body = (string)($post['body_html'] ?? '');
        $pricing = trim((string)($post['pricing_variant'] ?? 'FRANQUIA_KG'));
        $ativo = !empty($post['ativo']) ? 1 : 0;
        $novaVersao = !empty($post['nova_versao']);

        if ($nome === '' || $titulo === '' || trim($body) === '') {
            header('Location: '.URL.'/painel/contratos/modelos/'.$id.'?erro=campos');
            exit;
        }

        if ($novaVersao) {
            ContratoModelo::update($id, ['ativo' => 0]);
            ContratoModelo::insert([
                'slug' => $modelo->slug,
                'nome' => $nome,
                'versao' => $modelo->versao + 1,
                'titulo' => $titulo,
                'body_html' => $body,
                'pricing_variant' => $pricing,
                'flags_json' => $modelo->flags_json,
                'ativo' => $ativo,
            ]);
            header('Location: '.URL.'/painel/contratos/modelos');
            exit;
        }

        ContratoModelo::update($id, [
            'nome' => $nome,
            'titulo' => $titulo,
            'body_html' => $body,
            'pricing_variant' => $pricing,
            'ativo' => $ativo,
        ]);
        header('Location: '.URL.'/painel/contratos/modelos/'.$id.'?ok=1');
        exit;
    }

    public static function reimportarSeed($request): void
    {
        self::requireAdmin();
        $post = $request->getPostVars();
        if (!CsrfHelper::validate($post['_csrf'] ?? null)) {
            header('Location: '.URL.'/painel/contratos/modelos');
            exit;
        }
        $script = dirname(__DIR__, 3).'/database/scripts/seed_contrato_modelos.php';
        if (is_file($script)) {
            include $script;
        }
        header('Location: '.URL.'/painel/contratos/modelos?reimport=1');
        exit;
    }

    private static function placeholdersHelpHtml(): string
    {
        $items = [
            '{{qualificacao_preambulo}}', '{{frequencia_coleta}}', '{{valor_mensal}}', '{{franquia_resumo}}',
            '{{linhas_excedente_html}}', '{{taxa_adesao_bloco}}', '{{indice_reajuste}}', '{{data_inicio_br}}',
            '{{data_fim_br}}', '{{foro_cidade}}', '{{foro_uf}}', '{{promocao_html}}', '{{clausulas_extra_html}}',
            '{{assinaturas_html}}', '{{contratante_razao}} no título',
        ];

        return '<ul class="small mb-0"><li>'.implode('</li><li>', array_map(
            fn ($p) => htmlspecialchars($p, ENT_QUOTES, 'UTF-8'),
            $items
        )).'</li></ul>';
    }
}
