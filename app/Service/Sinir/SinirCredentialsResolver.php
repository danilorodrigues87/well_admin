<?php

namespace App\Service\Sinir;

use App\Common\SinirConfig;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\Transportadora as EntityTransportadora;

/**
 * Credenciais SINIR por papel (multitenancy).
 *
 * - Emissão MTR → token do **cliente gerador**
 * - Recebimento / CDF → token do **destinador** → operadora → `.env`
 * - Listas / sync → `SinirConfig` (operadora → `.env`)
 */
class SinirCredentialsResolver
{
    /**
     * @return array{
     *   ok:bool,
     *   integration_token:?string,
     *   token_source:?string,
     *   transportador_unidade:?int,
     *   destinador_unidade:?int,
     *   gerador_unidade:?int,
     *   errors:string[]
     * }
     */
    public function forEmissaoManifesto(int $coletaId): array
    {
        $base = $this->resolverUnidadesEParceiros($coletaId);
        if ($base['coleta'] === null) {
            return $this->failEmissao($base['errors']);
        }

        $errors = $base['errors'];
        $cliente = $base['cliente'];
        $geradorUnidade = $cliente ? (int)($cliente->sinir_cod_unidade ?? 0) : 0;
        if ($geradorUnidade <= 0) {
            $errors[] = 'Cliente sem código unidade SINIR (cadastro do gerador).';
        }

        $token = $cliente ? trim((string)($cliente->sinir_integration_token ?? '')) : '';
        if ($token === '') {
            $errors[] = 'Cliente sem Token API WS do gerador. Cadastre em Clientes (admin) '
                .'ou no perfil do portal gerador — o token deve ser gerado no portal MTR '
                .'na unidade geradora do cliente.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'integration_token' => null,
                'token_source' => null,
                'transportador_unidade' => $base['transportador_unidade'],
                'destinador_unidade' => $base['destinador_unidade'],
                'gerador_unidade' => $geradorUnidade > 0 ? $geradorUnidade : null,
                'errors' => $errors,
            ];
        }

        return [
            'ok' => true,
            'integration_token' => $token,
            'token_source' => 'cliente',
            'transportador_unidade' => $base['transportador_unidade'],
            'destinador_unidade' => $base['destinador_unidade'],
            'gerador_unidade' => $geradorUnidade,
            'errors' => [],
        ];
    }

    /**
     * Token do destinador para receber MTR / emitir CDF.
     *
     * @return array{
     *   ok:bool,
     *   integration_token:?string,
     *   token_source:?string,
     *   transportador_unidade:?int,
     *   destinador_unidade:?int,
     *   errors:string[]
     * }
     */
    public function forRecebimento(int $coletaId): array
    {
        $base = $this->resolverUnidadesEParceiros($coletaId);
        if ($base['coleta'] === null) {
            return [
                'ok' => false,
                'integration_token' => null,
                'token_source' => null,
                'transportador_unidade' => null,
                'destinador_unidade' => null,
                'errors' => $base['errors'],
            ];
        }

        $errors = $base['errors'];
        $destinador = $base['destinador'];
        $token = $destinador ? trim((string)($destinador->sinir_integration_token ?? '')) : '';
        $source = 'destinador';

        if ($token === '') {
            $opToken = SinirConfig::integrationToken();
            if ($opToken !== '') {
                $token = $opToken;
                $source = 'operadora';
            }
        }

        if ($token === '') {
            $errors[] = 'Destinador sem Token API WS. Cadastre em Destinadores '
                .'ou configure o token da operadora (tabela operadoras / .env piloto).';
        }

        return [
            'ok' => $errors === [],
            'integration_token' => $token !== '' ? $token : null,
            'token_source' => $token !== '' ? $source : null,
            'transportador_unidade' => $base['transportador_unidade'],
            'destinador_unidade' => $base['destinador_unidade'],
            'errors' => $errors,
        ];
    }

    /**
     * @deprecated Use forRecebimento() — mantido para chamadas antigas.
     * @return array{
     *   ok:bool,
     *   integration_token:?string,
     *   token_source:?string,
     *   transportador_unidade:?int,
     *   destinador_unidade:?int,
     *   errors:string[]
     * }
     */
    public function forColeta(int $coletaId): array
    {
        return $this->forRecebimento($coletaId);
    }

    /**
     * @return array{
     *   ok:bool,
     *   coleta:?EntityColeta,
     *   cliente:?EntityCliente,
     *   transportadora:?EntityTransportadora,
     *   destinador:?EntityDestinador,
     *   transportador_unidade:?int,
     *   destinador_unidade:?int,
     *   errors:string[]
     * }
     */
    private function resolverUnidadesEParceiros(int $coletaId): array
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta) {
            return [
                'ok' => false,
                'coleta' => null,
                'cliente' => null,
                'transportadora' => null,
                'destinador' => null,
                'transportador_unidade' => null,
                'destinador_unidade' => null,
                'errors' => ['Coleta não encontrada.'],
            ];
        }

        $cliente = EntityCliente::getById((int)$coleta->cliente_id);
        $transportadora = $coleta->transportadora_id
            ? EntityTransportadora::getById((int)$coleta->transportadora_id)
            : EntityTransportadora::getPadrao();
        $destinador = $coleta->destinador_id
            ? EntityDestinador::getById((int)$coleta->destinador_id)
            : EntityDestinador::getPadrao();

        $errors = [];
        if (!$cliente) {
            $errors[] = 'Cliente da coleta não encontrado.';
        }
        if (!$transportadora) {
            $errors[] = 'Transportadora não cadastrada.';
        }
        if (!$destinador) {
            $errors[] = 'Destinador não cadastrado.';
        }

        $transUnidade = (int)($transportadora->sinir_cod_unidade ?? 0);
        if ($transUnidade <= 0) {
            $transUnidade = (int)SinirConfig::unidade();
        }
        if ($transUnidade <= 0) {
            $errors[] = 'Transportadora sem código unidade SINIR.';
        }

        $destUnidade = (int)($destinador->sinir_cod_unidade ?? 0);
        if ($destUnidade <= 0) {
            $destUnidade = (int)SinirConfig::destinadorUnidade();
        }
        if ($destUnidade <= 0) {
            $errors[] = 'Destinador sem código unidade SINIR.';
        }

        return [
            'ok' => $errors === [],
            'coleta' => $coleta,
            'cliente' => $cliente,
            'transportadora' => $transportadora,
            'destinador' => $destinador,
            'transportador_unidade' => $transUnidade > 0 ? $transUnidade : null,
            'destinador_unidade' => $destUnidade > 0 ? $destUnidade : null,
            'errors' => $errors,
        ];
    }

    /** @param string[] $errors */
    private function failEmissao(array $errors): array
    {
        return [
            'ok' => false,
            'integration_token' => null,
            'token_source' => null,
            'transportador_unidade' => null,
            'destinador_unidade' => null,
            'gerador_unidade' => null,
            'errors' => $errors,
        ];
    }
}
