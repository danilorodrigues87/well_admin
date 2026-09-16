<?php

namespace App\Controller\PublicPages;

use App\Common\CompanyConfig;
use App\Common\Environment;
use App\Utils\View;

class Privacidade
{
    public static function index($request): string
    {
        $content = View::render('public/privacidade', [
            'company_name' => htmlspecialchars(CompanyConfig::name(), ENT_QUOTES, 'UTF-8'),
            'company_short' => htmlspecialchars(CompanyConfig::shortName(), ENT_QUOTES, 'UTF-8'),
            'atualizado' => '16 de setembro de 2026',
            'contato_email' => htmlspecialchars(
                trim((string)Environment::get('MAIL_FROM', Environment::get('SMTP_FROM', 'contato@well.eco'))),
                ENT_QUOTES,
                'UTF-8'
            ),
            'url_politica' => rtrim((string)URL, '/').'/privacidade',
            'URL' => rtrim((string)URL, '/'),
        ]);

        return View::render('login/page', [
            'title' => 'Política de Privacidade — '.CompanyConfig::name(),
            'content' => View::render('public/privacidade_wrap', ['inner' => $content]),
        ]);
    }
}
