<?php

namespace App\Controller\Admin;

use App\Model\Entity\Funcao as EntityFuncao;
use App\Utils\View;

/** Funcionários — mesma gestão de usuários, módulo separado no menu. */
class Funcionarios extends Usuarios
{
    public static function index($request): string
    {
        $funcoes = EntityFuncao::getAll();
        $options = '';
        foreach ($funcoes as $f) {
            $options .= '<option value="'.$f->id.'">'.\App\Common\Helpers\CrudHelper::e($f->nome).'</option>';
        }
        $content = View::render('admin/modules/usuarios/index', [
            'csrf_field' => \App\Common\Helpers\CsrfHelper::field(),
            'funcoes_options' => $options,
            'transportadoras_options' => Usuarios::transportadorasOptionsHtml(0),
            'titulo_pagina' => 'Funcionários',
        ]);
        return Page::getPage('Funcionários', $content, 'funcionarios', Page::crudScripts('/painel/funcionarios'));
    }
}
