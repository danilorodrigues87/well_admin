<?php

namespace App\Controller\Admin;

use App\Service\DashboardService;
use App\Utils\View;

class Home
{
    public static function index($request): string
    {
        $kpis = DashboardService::kpis();

        $content = View::render('admin/modules/home/index', [
            'site' => SITE,
            'coletas_mes' => $kpis['coletas_mes'],
            'rascunhos' => $kpis['rascunhos'],
            'urgentes' => $kpis['urgentes'],
            'atrasados' => $kpis['atrasados'],
            'sem_coletor' => $kpis['sem_coletor'],
            'pend_recebimento' => $kpis['pend_recebimento'],
            'clientes_ativos' => $kpis['clientes_ativos'],
        ]);

        return Page::getPage('Dashboard — '.SITE, $content, 'dashboard');
    }
}
