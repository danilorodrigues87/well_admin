<?php

namespace App\Common;

use App\Model\Entity\Operadora;

class ColetaDefaults
{
    public static function transportador(): array
    {
        $op = Operadora::getById(OperadoraScope::getOperadoraId());
        if ($op && $op->transportador_nome !== '') {
            return [
                'nome' => $op->transportador_nome,
                'cnpj' => $op->transportador_cnpj,
                'motorista' => (string)Environment::get('COLETA_MOTORISTA_PADRAO', ''),
            ];
        }

        return [
            'nome' => (string)Environment::get('COLETA_TRANSPORTADOR', 'Well Soluções Ambientais'),
            'cnpj' => (string)Environment::get('COLETA_TRANSPORTADOR_CNPJ', '18.675.233/0001-50'),
            'motorista' => (string)Environment::get('COLETA_MOTORISTA_PADRAO', ''),
        ];
    }

    public static function destinador(): array
    {
        $op = Operadora::getById(OperadoraScope::getOperadoraId());
        if ($op && $op->destinador_nome !== '') {
            return [
                'nome' => $op->destinador_nome,
                'cnpj' => $op->destinador_cnpj,
                'endereco' => $op->destinador_endereco,
                'telefone' => $op->destinador_telefone,
                'responsavel' => $op->destinador_responsavel,
            ];
        }

        return [
            'nome' => (string)Environment::get('COLETA_DESTINADOR', 'Well Soluções Ambientais'),
            'cnpj' => (string)Environment::get('COLETA_DESTINADOR_CNPJ', '18.675.233/0001-50'),
            'endereco' => (string)Environment::get('COLETA_DESTINADOR_END', 'Av. Industrial Q09 L15'),
            'telefone' => (string)Environment::get('COLETA_DESTINADOR_FONE', '66996800006'),
            'responsavel' => (string)Environment::get('COLETA_DESTINADOR_RESP', ''),
        ];
    }

    /** @return string[] */
    public static function tratamentos(): array
    {
        return [
            'Aterro Sanitário',
            'Autoclave',
            'Blendagem',
            'Compostagem',
            'Coprocessamento',
            'Incineração',
            'Reciclagem',
            'Reutilização',
            'Tratamento Físico-Químico',
            'Outros',
        ];
    }

    public static function diasProximaColeta(): int
    {
        return (int)Environment::get('COLETA_DIAS_PROXIMA', 7);
    }
}
