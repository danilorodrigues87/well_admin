<?php

namespace App\Common;

/**
 * Nome da empresa — sempre via .env (nunca "Well Eco" em comunicações ao cliente).
 */
class CompanyConfig
{
    public static function name(): string
    {
        return trim((string)Environment::get('COMPANY_NAME', 'Well Soluções Ambientais'));
    }

    public static function shortName(): string
    {
        return trim((string)Environment::get('COMPANY_SHORT_NAME', 'Well S.A.'));
    }

    /** Remetente de e-mail (fallback: abreviado). */
    public static function mailFromName(): string
    {
        $from = trim((string)Environment::get('MAIL_FROM_NAME', Environment::get('SMTP_FROM_NAME', '')));

        return $from !== '' ? $from : self::shortName();
    }
}
