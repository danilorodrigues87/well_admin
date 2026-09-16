<?php

namespace App\Common;

use App\Model\Entity\Operadora;

/**
 * Nome da empresa — operadora ativa ou .env (nunca "Well Eco" em comunicações ao cliente).
 */
class CompanyConfig
{
    public static function name(?int $operadoraId = null): string
    {
        $fromOperadora = self::operadoraField($operadoraId, 'nome_fantasia');
        if ($fromOperadora !== '') {
            return $fromOperadora;
        }

        return trim((string)Environment::get('COMPANY_NAME', 'Well Soluções Ambientais'));
    }

    public static function shortName(?int $operadoraId = null): string
    {
        $fromOperadora = self::operadoraField($operadoraId, 'nome_curto');
        if ($fromOperadora !== '') {
            return $fromOperadora;
        }

        return trim((string)Environment::get('COMPANY_SHORT_NAME', 'Well S.A.'));
    }

    private static function operadoraField(?int $operadoraId, string $field): string
    {
        $id = $operadoraId ?? OperadoraScope::getOperadoraId();
        if ($id <= 0) {
            return '';
        }

        $op = Operadora::getById($id);
        if (!$op) {
            return '';
        }

        return trim((string)($op->$field ?? ''));
    }

    /** Remetente de e-mail (fallback: abreviado). */
    public static function mailFromName(): string
    {
        $from = trim((string)Environment::get('MAIL_FROM_NAME', Environment::get('SMTP_FROM_NAME', '')));

        return $from !== '' ? $from : self::shortName();
    }
}
