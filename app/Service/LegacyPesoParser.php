<?php

namespace App\Service;

class LegacyPesoParser
{
    /** @return list<array{nome:string,quantidade:float,unidade:string,cod_ibama:?string}> */
    public static function parse(string $peso): array
    {
        $peso = trim($peso);
        if ($peso === '' || preg_match('/^\s*-\s*Kg\s*$/iu', $peso)) {
            return [];
        }

        $items = [];
        foreach (self::splitChunks($peso) as $chunk) {
            $item = self::parseChunk($chunk);
            if ($item !== null && $item['quantidade'] > 0) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /** @return list<string> */
    private static function splitChunks(string $peso): array
    {
        $normalized = preg_replace(
            '/(\s(?:Kg|kg|L|l|Un|un|g))\s*-\s*(?=\d)/u',
            '$1|||',
            $peso
        ) ?? $peso;

        $parts = preg_split('/,\s*|\|\|\|/u', $normalized) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    /** @return array{nome:string,quantidade:float,unidade:string,cod_ibama:?string}|null */
    private static function parseChunk(string $chunk): ?array
    {
        if (preg_match('/^(.+?)\s*-\s*([\d.,]+)\s*(Kg|kg|L|l|Un|un|g)?\s*$/u', $chunk, $m)) {
            return self::buildItem($m[1], $m[2], $m[3] ?? 'kg');
        }

        if (preg_match('/^(.+?)\s+([\d.,]+)\s*(Kg|kg|L|l|Un|un|g)\s*$/u', $chunk, $m)) {
            return self::buildItem($m[1], $m[2], $m[3]);
        }

        return null;
    }

    /** @return array{nome:string,quantidade:float,unidade:string,cod_ibama:?string} */
    private static function buildItem(string $nome, string $qtyRaw, string $unitRaw): array
    {
        $nome = trim($nome);
        $codIbama = null;

        if (preg_match('/^(\d{2}\.\d{2}\.\d{2})-(.+)$/u', $nome, $m)) {
            $codIbama = $m[1];
            $nome = trim($m[2]);
        }

        return [
            'nome' => $nome,
            'quantidade' => self::parseQuantity($qtyRaw),
            'unidade' => self::normalizeUnit($unitRaw),
            'cod_ibama' => $codIbama,
        ];
    }

    private static function parseQuantity(string $raw): float
    {
        $raw = trim(str_replace(',', '.', $raw));
        if ($raw === '') {
            return 0.0;
        }

        if (preg_match('/^(\d+)\.(\d+)$/', $raw, $m)) {
            if ($m[1] !== '0' && strlen($m[2]) === 3) {
                return (float)($m[1].$m[2]);
            }
        }

        return (float)$raw;
    }

    private static function normalizeUnit(string $unit): string
    {
        $u = strtolower(trim($unit));
        if ($u === 'l') {
            return 'l';
        }
        if ($u === 'un') {
            return 'un';
        }

        return 'kg';
    }
}
