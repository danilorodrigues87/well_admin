<?php

namespace App\Controller\Admin;

use App\Common\Helpers\CsrfHelper;
use App\Common\SystemModules;
use App\Session\User\Login as SessionUser;
use App\Utils\View;

class Page
{
    public static function getPage(
        string $title,
        string $content,
        string $currentSlug = 'dashboard',
        string $scripts = ''
    ): string {
        $userData = SessionUser::getUserLogedData();
        $usuario = $userData['usuario'] ?? [];

        return View::render('admin/page', [
            'title' => $title,
            'content' => $content,
            'scripts' => $scripts,
            'menu' => self::getMenu($currentSlug, $usuario['modulos'] ?? []),
            'user' => htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES, 'UTF-8'),
            'funcao' => htmlspecialchars($usuario['funcao_nome'] ?? '', ENT_QUOTES, 'UTF-8'),
            'csrf_field' => CsrfHelper::field(),
        ]);
    }

    public static function crudScripts(string $baseUrl, bool $autoLoad = true): string
    {
        return View::render('admin/_partials/crud_scripts', [
            'base_url' => $baseUrl,
            'listagem_path' => ltrim($baseUrl, '/'),
            'auto_load' => $autoLoad ? 'true' : 'false',
        ]);
    }

    public static function getMenu(string $currentSlug, array $permittedSlugs): string
    {
        $links = '';

        foreach (SystemModules::getMenuGroups() as $group) {
            if (($group['type'] ?? '') === 'link') {
                $slug = (string)($group['slug'] ?? '');
                if (!in_array($slug, $permittedSlugs, true)) {
                    continue;
                }

                $links .= View::render('admin/menu/link', [
                    'label' => $group['label'],
                    'link' => URL.$group['link'],
                    'icon' => $group['icon'],
                    'current' => $slug === $currentSlug ? 'active' : '',
                ]);
                continue;
            }

            $subLinks = '';
            $hasActive = false;

            foreach ($group['items'] ?? [] as $item) {
                $slug = (string)($item['slug'] ?? '');
                if (!in_array($slug, $permittedSlugs, true)) {
                    continue;
                }
                if ($slug === $currentSlug) {
                    $hasActive = true;
                }

                $subLinks .= View::render('admin/menu/sub_link', [
                    'label' => $item['label'],
                    'link' => URL.$item['link'],
                    'active' => $slug === $currentSlug ? 'active' : '',
                ]);
            }

            if ($subLinks === '') {
                continue;
            }

            $links .= View::render('admin/menu/dropdown', [
                'label' => $group['label'],
                'icon' => $group['icon'],
                'subLinks' => $subLinks,
                'name' => $group['collapse_id'] ?? uniqid('menu-'),
                'current' => $hasActive ? 'active' : '',
                'expanded' => $hasActive ? 'true' : 'false',
                'show' => $hasActive ? 'show' : '',
            ]);
        }

        return View::render('admin/menu/sidebar', [
            'links' => View::render('admin/menu/box', ['links' => $links]),
            'user' => htmlspecialchars(SessionUser::getUserLogedData()['usuario']['nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8'),
        ]);
    }

    public static function jsonLista(array $conteudo): string
    {
        $json = json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return json_encode([
                'success' => false,
                'itens' => '<div class="alert alert-danger m-3">Erro ao montar a lista.</div>',
                'pagination' => '',
            ], JSON_UNESCAPED_UNICODE);
        }
        return $json;
    }
}
