<?php

namespace App\Service\Inter;

use App\Common\CobrancaConfig;
use App\Model\Entity\Cliente as EntityCliente;

class InterPayloadBuilder
{
    public const VALOR_MINIMO_INTER = 2.5;

    /**
     * @return array{ok:bool,payload:?array,error:?string}
     */
    public static function build(
        EntityCliente $cliente,
        string $competenciaYm,
        float $valorNominal,
        string $dataVencimento,
        ?array $multaMoraOverride = null
    ): array {
        $validation = self::validateCliente($cliente);
        if ($validation !== null) {
            return ['ok' => false, 'payload' => null, 'error' => $validation];
        }

        if ($valorNominal < self::VALOR_MINIMO_INTER) {
            return [
                'ok' => false,
                'payload' => null,
                'error' => 'Valor mínimo do boleto Inter é R$ '.number_format(self::VALOR_MINIMO_INTER, 2, ',', '.'),
            ];
        }

        [$ano, $mes] = array_map('intval', explode('-', $competenciaYm));
        $competenciaLabel = sprintf('%02d/%04d', $mes, $ano);

        $payload = [
            'seuNumero' => self::seuNumero($competenciaYm, $cliente->id),
            'valorNominal' => round($valorNominal, 2),
            'dataVencimento' => $dataVencimento,
            'numDiasAgenda' => 60,
            'pagador' => self::pagador($cliente),
            'mensagem' => [
                'linha1' => 'Competência '.$competenciaLabel.' - Well Eco',
            ],
        ];

        $payload = array_merge($payload, CobrancaConfig::toInterPayload($dataVencimento, $multaMoraOverride));

        return ['ok' => true, 'payload' => $payload, 'error' => null];
    }

    private static function seuNumero(string $competenciaYm, int $clienteId): string
    {
        return 'WE-'.str_replace('-', '', $competenciaYm).'-'.$clienteId;
    }

    /** @return array<string,mixed> */
    private static function pagador(EntityCliente $cliente): array
    {
        $cnpj = preg_replace('/\D/', '', $cliente->cnpj);
        $telefone = preg_replace('/\D/', '', $cliente->telefone);
        $ddd = strlen($telefone) >= 10 ? substr($telefone, 0, 2) : '11';
        $fone = strlen($telefone) >= 10 ? substr($telefone, 2) : $telefone;

        return [
            'cpfCnpj' => $cnpj,
            'tipoPessoa' => strlen($cnpj) === 11 ? 'FISICA' : 'JURIDICA',
            'nome' => mb_substr($cliente->razao_social ?: $cliente->nome_fantasia, 0, 100),
            'email' => mb_substr($cliente->email, 0, 120),
            'telefone' => $fone !== '' ? $fone : '000000000',
            'ddd' => $ddd,
            'cep' => preg_replace('/\D/', '', $cliente->cep),
            'endereco' => mb_substr($cliente->logradouro, 0, 90),
            'numero' => mb_substr($cliente->numero ?: 'S/N', 0, 10),
            'bairro' => mb_substr($cliente->bairro, 0, 60),
            'cidade' => mb_substr($cliente->cidade, 0, 60),
            'uf' => strtoupper(mb_substr($cliente->uf, 0, 2)),
        ];
    }

    private static function validateCliente(EntityCliente $cliente): ?string
    {
        $cnpj = preg_replace('/\D/', '', $cliente->cnpj);
        if ($cnpj === '' || (strlen($cnpj) !== 11 && strlen($cnpj) !== 14)) {
            return 'CNPJ/CPF inválido ou ausente';
        }
        if (trim($cliente->razao_social.$cliente->nome_fantasia) === '') {
            return 'Nome/razão social ausente';
        }
        if (preg_replace('/\D/', '', $cliente->cep) === '') {
            return 'CEP ausente';
        }
        if (trim($cliente->logradouro) === '' || trim($cliente->cidade) === '' || trim($cliente->uf) === '') {
            return 'Endereço incompleto';
        }

        return null;
    }
}
