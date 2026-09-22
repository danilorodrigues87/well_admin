<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\Transportadora as EntityTransportadora;

/**
 * Token de integração e unidades SINIR por coleta (Well ou transportadora terceira).
 */
class SinirCredentialsResolver
{
    /**
     * @return array{
     *   ok:bool,
     *   integration_token:?string,
     *   transportador_unidade:?int,
     *   destinador_unidade:?int,
     *   errors:string[]
     * }
     */
    public function forColeta(int $coletaId): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            return ['ok' => false, 'integration_token' => null, 'transportador_unidade' => null, 'destinador_unidade' => null, 'errors' => ['Coleta não encontrada.']];
        }

        $transportadora = $coleta->transportadora_id
            ? EntityTransportadora::getById((int)$coleta->transportadora_id)
            : EntityTransportadora::getPadrao();
        $destinador = $coleta->destinador_id
            ? EntityDestinador::getById((int)$coleta->destinador_id)
            : EntityDestinador::getPadrao();

        $errors = [];
        $token = trim((string)($transportadora->sinir_integration_token ?? ''));
        if ($token === '') {
            if (!SinirConfig::isConfigured()) {
                $errors[] = 'Configure token SINIR na operadora (.env) ou no cadastro da transportadora.';
            } else {
                $token = SinirConfig::integrationToken();
            }
        }

        $transUnidade = (int)($transportadora->sinir_cod_unidade ?? 0);
        if ($transUnidade <= 0 && SinirConfig::isConfigured()) {
            $transUnidade = (int)SinirConfig::unidade();
        }
        if ($transUnidade <= 0) {
            $errors[] = 'Transportadora sem código unidade SINIR.';
        }

        $destUnidade = (int)($destinador->sinir_cod_unidade ?? 0);
        if ($destUnidade <= 0 && SinirConfig::isConfigured()) {
            $destUnidade = (int)SinirConfig::destinadorUnidade();
        }
        if ($destUnidade <= 0) {
            $errors[] = 'Destinador sem código unidade SINIR.';
        }

        if ($token === '') {
            $errors[] = 'Token de integração SINIR ausente.';
        }

        return [
            'ok' => $errors === [],
            'integration_token' => $token !== '' ? $token : null,
            'transportador_unidade' => $transUnidade > 0 ? $transUnidade : null,
            'destinador_unidade' => $destUnidade > 0 ? $destUnidade : null,
            'errors' => $errors,
        ];
    }
}
