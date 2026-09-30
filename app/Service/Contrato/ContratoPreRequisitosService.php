<?php

namespace App\Service\Contrato;

use App\Common\Contrato\ContractType;
use App\Model\Entity\Cliente;
use App\Model\Entity\ContratoModelo;
use App\Model\Entity\Plano;
use App\Model\Entity\PlanoItem;
use App\Common\OperadoraScope;

/**
 * Validações antes de criar/enviar contrato.
 */
final class ContratoPreRequisitosService
{
    /** @return list<string> */
    public static function errosQualificacaoCliente(Cliente $cliente): array
    {
        $erros = [];
        if (trim($cliente->responsavel) === '') {
            $erros[] = 'Nome do responsável';
        }
        if (trim($cliente->responsavel_cpf) === '') {
            $erros[] = 'CPF do responsável';
        }
        if (trim($cliente->responsavel_rg) === '') {
            $erros[] = 'RG do responsável';
        }
        if (trim($cliente->responsavel_cargo) === '') {
            $erros[] = 'Cargo do responsável';
        }

        return $erros;
    }

    /** @return list<string> */
    public static function errosPlano(int $planoId): array
    {
        $erros = [];
        $plano = Plano::getById($planoId);
        if (!$plano) {
            return ['Plano inválido'];
        }

        $slug = ContractType::normalize($plano->contrato_modelo_tipo ?? ContractType::GENERICO);
        if ($slug !== ContractType::GENERICO) {
            if (!ContratoModelo::tabelaExiste()) {
                $erros[] = 'Tabela de modelos jurídicos não instalada (migration 050)';
            } elseif (ContratoModelo::resolveAtivo(OperadoraScope::getOperadoraId(), $slug) === null) {
                $erros[] = 'Modelo jurídico «'.ContractType::label($slug).'» não cadastrado (seed de modelos)';
            }
        }

        $itens = PlanoItem::getByPlanoId($planoId);
        if ($itens === []) {
            $erros[] = 'Plano sem itens de resíduo (franquia/excedente)';
        }

        return $erros;
    }

    /** @return list<string> */
    public static function errosCompletos(Cliente $cliente, int $planoId): array
    {
        return array_merge(
            self::errosQualificacaoCliente($cliente),
            self::errosPlano($planoId)
        );
    }
}
