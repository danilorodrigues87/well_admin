<?php

namespace App\Service;

use App\Common\OperadoraScope;
use App\Model\Entity\Operadora;
use App\Model\Entity\OperadoraConfig as EntityOperadoraConfig;

class OperadoraConfigService
{
    /** @return array<string,mixed>|null */
    public static function getOperadoraAtual(): ?array
    {
        $op = Operadora::getById(OperadoraScope::getOperadoraId());
        if (!$op) {
            return null;
        }

        return [
            'id' => $op->id,
            'nome_fantasia' => $op->nome_fantasia,
            'nome_curto' => $op->nome_curto,
            'razao_social' => $op->razao_social,
            'cnpj' => $op->cnpj,
            'transportador_nome' => $op->transportador_nome,
            'transportador_cnpj' => $op->transportador_cnpj,
            'destinador_nome' => $op->destinador_nome,
            'destinador_cnpj' => $op->destinador_cnpj,
            'destinador_endereco' => $op->destinador_endereco,
            'destinador_telefone' => $op->destinador_telefone,
            'destinador_responsavel' => $op->destinador_responsavel,
        ];
    }

    /** @param array<string,mixed> $post */
    public static function saveOperadora(array $post): void
    {
        $id = OperadoraScope::getOperadoraId();
        Operadora::update($id, [
            'nome_fantasia' => trim((string)($post['nome_fantasia'] ?? '')),
            'nome_curto' => trim((string)($post['nome_curto'] ?? '')),
            'razao_social' => trim((string)($post['razao_social'] ?? '')),
            'cnpj' => trim((string)($post['cnpj'] ?? '')),
            'transportador_nome' => trim((string)($post['transportador_nome'] ?? '')),
            'transportador_cnpj' => trim((string)($post['transportador_cnpj'] ?? '')),
            'destinador_nome' => trim((string)($post['destinador_nome'] ?? '')),
            'destinador_cnpj' => trim((string)($post['destinador_cnpj'] ?? '')),
            'destinador_endereco' => trim((string)($post['destinador_endereco'] ?? '')),
            'destinador_telefone' => trim((string)($post['destinador_telefone'] ?? '')),
            'destinador_responsavel' => trim((string)($post['destinador_responsavel'] ?? '')),
        ]);
    }

    /** @return array<string,string> */
    public static function getCobrancaKeys(): array
    {
        return EntityOperadoraConfig::getMany([
            'cobranca.multa_tipo',
            'cobranca.multa_taxa',
            'cobranca.multa_valor',
            'cobranca.mora_tipo',
            'cobranca.mora_taxa',
            'cobranca.mora_valor',
        ]);
    }
}
