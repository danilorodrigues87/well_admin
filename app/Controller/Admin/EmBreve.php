<?php

namespace App\Controller\Admin;

use App\Utils\View;

class EmBreve extends Page
{
    public static function show($request, string $moduleSlug, string $title): string
    {
        $content = View::render('admin/modules/em_breve/index', [
            'titulo' => $title,
        ]);

        return self::getPage($title, $content, $moduleSlug);
    }
}
