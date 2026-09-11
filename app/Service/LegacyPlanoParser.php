<?php

namespace App\Service;

/**
 * Converte textos legados planos.saldo_residuo / valor_exced em itens estruturados.
 */
class LegacyPlanoParser
{
    /** @return list<array{nome:string,cod_ibama:?string,saldo_incluso:float,unidade:string,valor_excedente:float}> */
    public static function parseParSaldoExced(string $saldoText, string $excedText): array
    {
        $saldos = self::parseSaldo($saldoText);
        $excedentes = self::parseExced($excedText);
        $items = [];

        foreach ($saldos as $key => $saldo) {
            $exced = $excedentes[$key] ?? self::findExcedFallback($saldo, $excedentes);
            $items[] = [
                'nome' => $saldo['nome'],
                'cod_ibama' => $saldo['cod_ibama'],
                'saldo_incluso' => $saldo['saldo_incluso'],
                'unidade' => $saldo['unidade'],
                'valor_excedente' => (float)($exced['valor_excedente'] ?? 0),
            ];
        }

        foreach ($excedentes as $key => $exced) {
            if (isset($saldos[$key])) {
                continue;
            }
            $items[] = [
                'nome' => $exced['nome'],
                'cod_ibama' => $exced['cod_ibama'],
                'saldo_incluso' => 0,
                'unidade' => 'kg',
                'valor_excedente' => $exced['valor_excedente'],
            ];
        }

        return $items;
    }

    /** @return array<string, array{nome:string,cod_ibama:?string,saldo_incluso:float,unidade:string}> */
    private static function parseSaldo(string $text): array
    {
        $text = self::normalizeText($text);
        if ($text === '') {
            return [];
        }

        $items = [];
        if (!preg_match_all(
            '/(?:(\d{2}\.\d{2}\.\d{2})-)?(.+?)\s+Saldo\s+([\d.,]+)\s*(Kg|kg|L|l|Unid|Un|un)?/u',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        foreach ($matches as $m) {
            $nome = self::cleanNome($m[2]);
            if ($nome === '') {
                continue;
            }
            $key = self::itemKey($m[1] ?? null, $nome);
            $items[$key] = [
                'nome' => $nome,
                'cod_ibama' => isset($m[1]) ? trim($m[1]) : null,
                'saldo_incluso' => self::parseDecimal($m[3]),
                'unidade' => self::normalizeUnit($m[4] ?? 'kg'),
            ];
        }

        return $items;
    }

    /** @return array<string, array{nome:string,cod_ibama:?string,valor_excedente:float}> */
    private static function parseExced(string $text): array
    {
        $text = self::normalizeText($text);
        if ($text === '') {
            return [];
        }

        $items = [];
        if (!preg_match_all(
            '/(?:(\d{2}\.\d{2}\.\d{2})-)?(.+?)\s+Valor\s+R\$\s*(-?[\d.,]+)/u',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        foreach ($matches as $m) {
            $nome = self::cleanNome($m[2]);
            if ($nome === '') {
                continue;
            }
            $key = self::itemKey($m[1] ?? null, $nome);
            $items[$key] = [
                'nome' => $nome,
                'cod_ibama' => isset($m[1]) ? trim($m[1]) : null,
                'valor_excedente' => self::parseDecimal($m[3]),
            ];
        }

        return $items;
    }

    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    private static function cleanNome(string $nome): string
    {
        $nome = trim($nome, " \t\n\r\0\x0B-");
        return trim(preg_replace('/\s+/u', ' ', $nome) ?? $nome);
    }

    private static function itemKey(?string $codIbama, string $nome): string
    {
        $cod = $codIbama ? trim($codIbama) : '';
        $n = mb_strtolower(self::cleanNome($nome));
        return $cod !== '' ? 'cod:'.$cod : 'nome:'.$n;
    }

    /** @param array<string, array{nome:string,cod_ibama:?string,valor_excedente:float}> $excedentes */
    private static function findExcedFallback(array $saldo, array $excedentes): ?array
    {
        $key = self::itemKey($saldo['cod_ibama'], $saldo['nome']);
        if (isset($excedentes[$key])) {
            return $excedentes[$key];
        }
        $nomeKey = 'nome:'.mb_strtolower($saldo['nome']);
        if (isset($excedentes[$nomeKey])) {
            return $excedentes[$nomeKey];
        }
        foreach ($excedentes as $exced) {
            if ($saldo['cod_ibama'] && $exced['cod_ibama'] === $saldo['cod_ibama']) {
                return $exced;
            }
        }
        return null;
    }

    private static function parseDecimal(string $raw): float
    {
        $raw = trim(str_replace(',', '.', $raw));
        return $raw === '' ? 0.0 : (float)$raw;
    }

    private static function normalizeUnit(string $unit): string
    {
        $u = mb_strtolower(trim($unit));
        return match ($u) {
            'l' => 'l',
            'un', 'unid' => 'un',
            default => 'kg',
        };
    }
}
