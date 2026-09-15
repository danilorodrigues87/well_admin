<?php

namespace App\Service\Banco;

/**
 * Contrato para gateways bancários (Inter, mock, outros).
 */
interface BancoGatewayInterface
{
    public function isConfigured(): bool;

    /**
     * @return array{ok:bool,token:?string,expires_in:?int,error:?string,raw_status:int}
     */
    public function obtainAccessToken(bool $forceRefresh = false): array;

    /**
     * Emite cobrança BolePix (boleto + QR Code PIX).
     *
     * @param array<string,mixed> $payload Corpo conforme API Inter cobranca/v3/cobrancas
     * @return array{ok:bool,codigo_solicitacao:?string,body:?array,error:?string,raw_status:int}
     */
    public function emitirCobranca(array $payload): array;

    /**
     * @return array{ok:bool,body:?array,error:?string,raw_status:int}
     */
    public function consultarCobranca(string $codigoSolicitacao): array;

    /**
     * @return array{ok:bool,body:?array,error:?string,raw_status:int}
     */
    public function cancelarCobranca(string $codigoSolicitacao, string $motivo): array;
}
