<?php

namespace App\Common\Helpers;

/**
 * Código IBAMA — Lista Brasileira de Resíduos Sólidos (formato oficial XX.XX.XX).
 */
class IbamaCodigoHelper
{
    /** Normaliza para XX.XX.XX ou string vazia se inválido. */
    public static function normalize(?string $cod): string
    {
        if ($cod === null) {
            return '';
        }
        $cod = trim($cod);
        if ($cod === '') {
            return '';
        }

        if (preg_match('/[.\s]/', $cod)) {
            $groups = [];
            foreach (preg_split('/[.\s]+/', $cod) as $part) {
                $d = preg_replace('/\D/', '', $part) ?? '';
                if ($d === '') {
                    continue;
                }
                if (strlen($d) > 2) {
                    $d = substr($d, -2);
                }
                $groups[] = str_pad($d, 2, '0', STR_PAD_LEFT);
            }
            while (count($groups) < 3) {
                $groups[] = '00';
            }
            $groups = array_slice($groups, 0, 3);
            $digits = implode('', $groups);
            if ($digits === '' || preg_match('/^0+$/', $digits)) {
                return '';
            }

            return $groups[0].'.'.$groups[1].'.'.$groups[2];
        }

        $digits = preg_replace('/\D/', '', $cod) ?? '';
        if ($digits === '' || preg_match('/^0+$/', $digits)) {
            return '';
        }
        if (strlen($digits) > 6) {
            $digits = substr($digits, -6);
        }
        $digits = str_pad($digits, 6, '0', STR_PAD_RIGHT);

        return substr($digits, 0, 2).'.'.substr($digits, 2, 2).'.'.substr($digits, 4, 2);
    }

    public static function isValid(?string $cod): bool
    {
        $n = self::normalize($cod);

        return $n !== '' && $n !== '00.00.00';
    }

    /** Rótulo para listagens: "XX.XX.XX — Nome" ou só o nome. */
    public static function label(?string $cod, string $nome): string
    {
        $n = self::normalize($cod);

        return $n !== '' ? $n.' — '.$nome : $nome;
    }
}
