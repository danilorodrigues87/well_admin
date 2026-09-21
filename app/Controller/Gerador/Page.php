<?php

namespace App\Controller\Gerador;

use App\Common\CompanyConfig;
use App\Common\GeradorScope;
use App\Common\Helpers\CsrfHelper;
use App\Session\Gerador\Login as GeradorSession;
use App\Utils\View;

class Page
{
    /** @var list<array{slug:string,label:string,link:string,icon:string}> */
    private static array $menuItems = [
        ['slug' => 'dashboard', 'label' => 'Início', 'link' => '/gerador', 'icon' => 'fas fa-chart-line'],
        ['slug' => 'coletas', 'label' => 'Coletas', 'link' => '/gerador/coletas', 'icon' => 'fas fa-truck'],
        ['slug' => 'agendamentos', 'label' => 'Agendamentos', 'link' => '/gerador/agendamentos', 'icon' => 'fas fa-calendar-check'],
        ['slug' => 'boletos', 'label' => 'Financeiro', 'link' => '/gerador/boletos', 'icon' => 'fas fa-file-invoice-dollar'],
        ['slug' => 'perfil', 'label' => 'Meu perfil', 'link' => '/gerador/perfil', 'icon' => 'fas fa-id-badge'],
    ];

    public static function render(string $title, string $content, string $current = 'dashboard', string $scripts = ''): string
    {
        $session = GeradorSession::getData() ?? [];
        $operadoraId = GeradorScope::getOperadoraId();

        return View::render('gerador/page', [
            'title' => $title,
            'content' => $content,
            'scripts' => $scripts,
            'menu' => self::buildMenu($current, $session),
            'user_nome' => htmlspecialchars((string)($session['nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'cliente_nome' => htmlspecialchars((string)($session['cliente_nome'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'company_name' => htmlspecialchars(CompanyConfig::name($operadoraId), ENT_QUOTES, 'UTF-8'),
            'company_short' => htmlspecialchars(CompanyConfig::shortName($operadoraId), ENT_QUOTES, 'UTF-8'),
            'csrf_field' => CsrfHelper::field(),
        ]);
    }

    /** @param array<string,mixed> $session */
    private static function buildMenu(string $current, array $session): string
    {
        $links = '';
        foreach (self::$menuItems as $item) {
            $links .= View::render('admin/menu/link', [
                'label' => $item['label'],
                'link' => URL.$item['link'],
                'icon' => $item['icon'],
                'current' => $item['slug'] === $current ? 'active' : '',
            ]);
        }

        return View::render('admin/menu/sidebar', [
            'links' => View::render('admin/menu/box', ['links' => $links]),
            'user' => htmlspecialchars((string)($session['cliente_nome'] ?? $session['nome'] ?? 'Gerador'), ENT_QUOTES, 'UTF-8'),
        ]);
    }

    public static function paginationNav(int $page, int $pages, string $basePath, array $query = []): string
    {
        if ($pages <= 1) {
            return '';
        }

        $buildUrl = static function (int $p) use ($basePath, $query): string {
            $params = array_merge($query, ['page' => $p]);
            $qs = http_build_query($params);

            return URL.$basePath.($qs !== '' ? '?'.$qs : '');
        };

        $prev = $page > 1
            ? '<li class="page-item"><a class="page-link" href="'.htmlspecialchars($buildUrl($page - 1), ENT_QUOTES, 'UTF-8').'">&laquo; Anterior</a></li>'
            : '<li class="page-item disabled"><span class="page-link">&laquo; Anterior</span></li>';
        $next = $page < $pages
            ? '<li class="page-item"><a class="page-link" href="'.htmlspecialchars($buildUrl($page + 1), ENT_QUOTES, 'UTF-8').'">Próxima &raquo;</a></li>'
            : '<li class="page-item disabled"><span class="page-link">Próxima &raquo;</span></li>';

        return '<nav aria-label="Paginação" class="py-2"><ul class="pagination justify-content-center mb-0">'
            .$prev
            .'<li class="page-item disabled"><span class="page-link">Página '.$page.' de '.$pages.'</span></li>'
            .$next
            .'</ul></nav>';
    }
}
