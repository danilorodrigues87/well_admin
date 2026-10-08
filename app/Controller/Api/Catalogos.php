<?php

namespace App\Controller\Api;

use App\Common\ColetaDefaults;
use App\Common\Helpers\ApiHelper;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Transportadora as EntityTransportadora;
use App\Model\Entity\Usuario as EntityUsuario;
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

    public static function coletores($request): \App\Http\Response
    {
        $items = array_map(
            fn ($u) => ColetaApiPresenter::coletor($u),
            EntityUsuario::getColetoresAtivos()
        );

        return ApiHelper::ok(['items' => $items]);
    }

    public static function transportadoras($request): \App\Http\Response
    {
        $items = array_map(
            fn ($t) => ColetaApiPresenter::transportadora($t),
            EntityTransportadora::listAtivas()
        );

        return ApiHelper::ok(['items' => $items]);
    }

    public static function destinadores($request): \App\Http\Response
    {
        $items = array_map(
            fn ($d) => ColetaApiPresenter::destinador($d),
            EntityDestinador::listAtivos()
        );

        return ApiHelper::ok(['items' => $items]);
    }
}
