<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\Transportadora as EntityTransportadora;

/**
 * Validação antes de "Gerar MTR" (checklist gerador / transportador / destinador).
 */
class ColetaSinirPrecheckService
{
    /** @return array{ok:bool,checks:array<int,array{key:string,label:string,ok:bool,detail:string}>,errors:string[]} */
    public function forColeta(int $coletaId): array
    {
        if (!SinirConfig::isEnabled()) {
            return [
                'ok' => false,
                'checks' => [],
                'errors' => ['Integração SINIR desabilitada (SINIR_ENABLED).'],
            ];
        }

        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return [
                'ok' => false,
                'checks' => [],
                'errors' => ['Somente coletas finalizadas podem gerar MTR.'],
            ];
        }

        $checks = [];
        $errors = [];

        $cliente = EntityCliente::getById($coleta->cliente_id);
        $geradorOk = $cliente && (int)($cliente->sinir_cod_unidade ?? 0) > 0;
        $checks[] = [
            'key' => 'gerador',
            'label' => 'Gerador (cliente)',
            'ok' => $geradorOk,
            'detail' => $geradorOk
                ? 'Unidade SINIR: '.$cliente->sinir_cod_unidade
                : 'Cadastre o código unidade SINIR no cliente.',
        ];
        if (!$geradorOk) {
            $errors[] = 'Cliente sem código unidade SINIR.';
        }

        $transportadora = $coleta->transportadora_id
            ? EntityTransportadora::getById((int)$coleta->transportadora_id)
            : EntityTransportadora::getPadrao();
        $credentials = (new SinirCredentialsResolver())->forColeta($coletaId);
        $transUnidade = (int)($transportadora->sinir_cod_unidade ?? 0);
        $tokenOk = trim((string)($credentials['integration_token'] ?? '')) !== '';
        $transOk = $transportadora && $transUnidade > 0 && $tokenOk;
        $checks[] = [
            'key' => 'transportador',
            'label' => 'Transportador',
            'ok' => $transOk,
            'detail' => $transportadora
                ? ($transUnidade > 0
                    ? $transportadora->nome.' · unidade '.$transUnidade
                    : $transportadora->nome.' · falta unidade SINIR')
                : 'Transportadora não vinculada à coleta.',
        ];
        if (!$transOk) {
            $errors[] = 'Transportadora sem unidade SINIR ou token de integração.';
        }

        $destinador = $coleta->destinador_id
            ? EntityDestinador::getById((int)$coleta->destinador_id)
            : EntityDestinador::getPadrao();
        $destUnidade = (int)($destinador->sinir_cod_unidade ?? 0);
        $destOk = $destinador && $destUnidade > 0;
        $checks[] = [
            'key' => 'destinador',
            'label' => 'Destinador',
            'ok' => $destOk,
            'detail' => $destinador
                ? ($destUnidade > 0
                    ? $destinador->nome.' · unidade '.$destUnidade
                    : $destinador->nome.' · falta unidade SINIR')
                : 'Destinador não vinculado à coleta.',
        ];
        if (!$destOk) {
            $errors[] = 'Destinador sem código unidade SINIR.';
        }

        $built = (new SinirPayloadBuilder())->buildManifestoLote($coletaId);
        $payloadOk = $built['ok'];
        $checks[] = [
            'key' => 'residuos',
            'label' => 'Resíduos e manifesto',
            'ok' => $payloadOk,
            'detail' => $payloadOk
                ? 'Itens prontos para envio.'
                : implode(' ', $built['errors'] ?? ['Itens incompletos.']),
        ];
        if (!$payloadOk) {
            $errors = array_merge($errors, $built['errors'] ?? []);
        }

        return [
            'ok' => $errors === [],
            'checks' => $checks,
            'errors' => array_values(array_unique($errors)),
        ];
    }
}
