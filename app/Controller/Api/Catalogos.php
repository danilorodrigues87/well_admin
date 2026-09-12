<?php

namespace App\Controller\Api;

use App\Common\ColetaDefaults;
use App\Common\Helpers\ApiHelper;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Veiculo as EntityVeiculo;
use App\Service\ColetaApiPresenter;

class Catalogos extends BaseApi
{
    public static function veiculos($request): \App\Http\Response
    {
        $items = array_map(
            fn ($v) => ColetaApiPresenter::veiculo($v),
            EntityVeiculo::list('ativo = 1', [], '999')
        );

        return ApiHelper::ok(['items' => $items]);
    }

    public static function tiposResiduos($request): \App\Http\Response
    {
        $items = array_map(
            fn ($t) => ColetaApiPresenter::tipoResiduo($t),
            EntityTipoResiduo::list('t.ativo = 1', [], '999')
        );

        return ApiHelper::ok(['items' => $items]);
    }

    public static function tratamentos($request): \App\Http\Response
    {
        return ApiHelper::ok(['items' => ColetaDefaults::tratamentos()]);
    }
}
