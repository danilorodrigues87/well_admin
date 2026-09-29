<?php

namespace App\Common\Contrato;

final class ContractType
{
    public const GENERICO = 'GENERICO';
    public const RSS_CLINICA = 'RSS_CLINICA';
    public const RSS_HOSPITAL = 'RSS_HOSPITAL';
    public const CLASSE_I_II = 'CLASSE_I_II';
    public const CLASSE_I = 'CLASSE_I';
    public const RECICLAVEIS = 'RECICLAVEIS';
    public const PNEUS = 'PNEUS';
    public const RCC_PADRAO = 'RCC_PADRAO';
    public const OBRA_GRANDE_PORTE = 'OBRA_GRANDE_PORTE';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::GENERICO,
            self::RSS_CLINICA,
            self::RSS_HOSPITAL,
            self::CLASSE_I_II,
            self::CLASSE_I,
            self::RECICLAVEIS,
            self::PNEUS,
            self::RCC_PADRAO,
            self::OBRA_GRANDE_PORTE,
        ];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::RSS_CLINICA => 'RSS — Clínica',
            self::RSS_HOSPITAL => 'RSS — Hospital',
            self::CLASSE_I_II => 'Classe I + Classe II',
            self::CLASSE_I => 'Classe I (perigosos)',
            self::RECICLAVEIS => 'Classe II-B — Recicláveis',
            self::PNEUS => 'Classe II-A — Pneus',
            self::RCC_PADRAO => 'RCC — Construção civil',
            self::OBRA_GRANDE_PORTE => 'Obra / grande porte',
            default => 'Genérico (modelo simples)',
        };
    }

    public static function normalize(?string $raw): string
    {
        $raw = strtoupper(trim((string)$raw));
        if ($raw === '' || !in_array($raw, self::all(), true)) {
            return self::GENERICO;
        }

        return $raw;
    }
}
