<?php

namespace App\Common\Helpers;

class ContratoTemplateHelper
{
    public static function modeloPadrao(): string
    {
        $path = __DIR__.'/../../../resources/view/admin/modules/contratos/modelo_padrao.html';
        $html = @file_get_contents($path);

        return ($html !== false && trim($html) !== '') ? $html : self::fallback();
    }

    public static function resolverModelo(?int $operadoraId = null): string
    {
        return self::modeloPadrao();
    }

    public static function aplicar(string $html, array $vars): string
    {
        $mapa = [];
        foreach ($vars as $k => $v) {
            $k = (string)$k;
            $val = (string)$v;
            $mapa['{{'.$k.'}}'] = $val;
            $mapa['{'.$k.'}'] = $val;
        }

        return str_replace(array_keys($mapa), array_values($mapa), $html);
    }

    public static function render(array $vars, ?int $operadoraId = null): string
    {
        return self::aplicar(self::resolverModelo($operadoraId), $vars);
    }

    private static function fallback(): string
    {
        return '<div>{{contratada}}{{contratante}}{{plano}}{{clausula_1}}{{clausula_2}}{{clausula_3}}{{data_contrato}}</div>';
    }
}
