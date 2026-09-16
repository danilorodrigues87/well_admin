<?php

namespace App\Controller\Admin;

use App\Model\Entity\HelpArtigo;
use App\Model\Entity\HelpCategoria;
use App\Utils\View;

class Ajuda extends Page
{
    public static function index($request): string
    {
        $content = self::renderLista();

        return self::getPage('Central de Ajuda', $content, 'ajuda');
    }

    public static function artigo($request, string $slug): string
    {
        $content = self::renderArtigo($slug);

        return self::getPage('Central de Ajuda', $content, 'ajuda');
    }

    private static function renderLista(): string
    {
        if (!HelpCategoria::tabelasExistem()) {
            return '<div class="alert alert-warning">Documentação não configurada. Execute migration 030.</div>';
        }

        $base = rtrim((string)URL, '/').'/painel/ajuda';
        $html = '<h1 class="mt-4 mb-3">Central de ajuda</h1>'
            .'<p class="text-muted">Tutoriais para usar o painel Well.</p>';

        foreach (HelpCategoria::listAll(true) as $c) {
            $arts = HelpArtigo::listByCategoria($c->id, true);
            if (!$arts) {
                continue;
            }
            $html .= '<div class="card shadow mb-3"><div class="card-header"><strong>'
                .htmlspecialchars($c->titulo, ENT_QUOTES, 'UTF-8').'</strong></div><ul class="list-group list-group-flush">';
            foreach ($arts as $a) {
                $html .= '<li class="list-group-item"><a href="'.$base.'/'.rawurlencode($a->slug).'">'
                    .htmlspecialchars($a->titulo, ENT_QUOTES, 'UTF-8').'</a>';
                if ($a->resumo !== '') {
                    $html .= '<div class="small text-muted">'.htmlspecialchars($a->resumo, ENT_QUOTES, 'UTF-8').'</div>';
                }
                $html .= '</li>';
            }
            $html .= '</ul></div>';
        }

        return $html;
    }

    private static function renderArtigo(string $slug): string
    {
        $artigo = HelpArtigo::getBySlug($slug, true);
        if (!$artigo) {
            return '<div class="alert alert-warning mt-4">Artigo não encontrado.</div>';
        }

        $base = rtrim((string)URL, '/').'/painel/ajuda';

        return '<nav class="mt-3 mb-2"><a href="'.$base.'">&larr; Voltar</a></nav>'
            .'<h1 class="mb-3">'.htmlspecialchars($artigo->titulo, ENT_QUOTES, 'UTF-8').'</h1>'
            .'<div class="help-body">'.$artigo->corpo.'</div>';
    }
}
