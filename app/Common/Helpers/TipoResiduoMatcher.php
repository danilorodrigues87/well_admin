<?php

namespace App\Common\Helpers;

use App\Model\Entity\TipoResiduo as EntityTipoResiduo;

/**
 * Resolve tipo_residuo_id a partir de nome/código legado (import/backfill).
 */
class TipoResiduoMatcher
{
    /** @var array<string,int>|null */
    private static ?array $byCod = null;
    /** @var array<string,int>|null */
    private static ?array $byNomeExact = null;
    /** @var array<string,int>|null */
    private static ?array $byNomeNorm = null;
    /** @var list<array{id:int,nome:string,norm:string}>|null */
    private static ?array $tipoList = null;

    /** Nomes truncados do parser legado → tipo (alta confiança operacional Well). */
    private const LEGACY_ALIASES = [
        'agu' => 16,
        'aplainamento' => 86,
        '1 big bags de' => 19,
        '2 big bags de' => 19,
        '3 big bags de' => 19,
        '4 big bags de' => 19,
        '5 big bags de' => 19,
        '6 big bags de' => 19,
        '7 big bags de' => 19,
        '8 big bags de' => 19,
        '9 big bags de' => 19,
        '10 big bags de' => 19,
        '11 big bags de' => 19,
        'materiais improprios para consumo ou processamento' => 48,
    ];

    public static function resolve(?string $codIbama, ?string $nome): ?int
    {
        return self::resolveDetailed($codIbama, $nome)['id'];
    }

    /**
     * @return array{id:?int,method:string,score:float,material:string}
     */
    public static function resolveDetailed(?string $codIbama, ?string $nome): array
    {
        self::loadIndex();
        $empty = ['id' => null, 'method' => 'none', 'score' => 0.0, 'material' => ''];

        $cod = IbamaCodigoHelper::normalize(trim((string)$codIbama));
        if ($cod !== '' && isset(self::$byCod[$cod])) {
            return ['id' => self::$byCod[$cod], 'method' => 'cod_param', 'score' => 100.0, 'material' => ''];
        }

        $nome = trim((string)$nome);
        if ($nome === '') {
            return $empty;
        }

        $codFromNome = self::extractCodFromNome($nome);
        if ($codFromNome !== '' && isset(self::$byCod[$codFromNome])) {
            return ['id' => self::$byCod[$codFromNome], 'method' => 'cod_nome', 'score' => 100.0, 'material' => ''];
        }

        $tipoId = self::matchByNome($nome);
        if ($tipoId !== null) {
            return ['id' => $tipoId, 'method' => 'nome_exato', 'score' => 100.0, 'material' => $nome];
        }

        $material = self::extractMaterial($nome);
        if ($material !== '' && $material !== $nome) {
            $tipoId = self::matchByNome($material);
            if ($tipoId !== null) {
                return ['id' => $tipoId, 'method' => 'material_exato', 'score' => 95.0, 'material' => $material];
            }
        }

        $pn = self::normNome($nome);
        $matNorm = self::normNome($material !== '' ? $material : $nome);

        $aliasKey = mb_strtolower(trim($nome));
        if (isset(self::LEGACY_ALIASES[$aliasKey])) {
            return [
                'id' => self::LEGACY_ALIASES[$aliasKey],
                'method' => 'alias_legado',
                'score' => 90.0,
                'material' => $material,
            ];
        }

        $kw = self::matchKeywords($matNorm !== '' ? $matNorm : $pn);
        if ($kw !== null) {
            return ['id' => $kw, 'method' => 'keyword', 'score' => 85.0, 'material' => $material];
        }

        $best = self::bestSimilar($matNorm !== '' ? $matNorm : $pn, 50.0);
        if ($best !== null) {
            return [
                'id' => $best['id'],
                'method' => 'similar_material',
                'score' => $best['score'],
                'material' => $material,
            ];
        }

        $bestFull = self::bestSimilar($pn, 55.0);
        if ($bestFull !== null) {
            return [
                'id' => $bestFull['id'],
                'method' => 'similar_nome',
                'score' => $bestFull['score'],
                'material' => $material,
            ];
        }

        $fallback = EntityTipoResiduo::findByNome($nome)?->id;

        return $fallback !== null
            ? ['id' => $fallback, 'method' => 'find_by_nome', 'score' => 70.0, 'material' => $material]
            : $empty;
    }

    private static function matchByNome(string $nome): ?int
    {
        $lower = mb_strtolower(trim($nome));
        if (isset(self::$byNomeExact[$lower])) {
            return self::$byNomeExact[$lower];
        }

        return self::$byNomeNorm[self::normNome($nome)] ?? null;
    }

    private static function extractCodFromNome(string $nome): string
    {
        if (preg_match('/(\d{2}\.\d{2}\.\d{2})/u', $nome, $m)) {
            return IbamaCodigoHelper::normalize($m[1]);
        }

        return '';
    }

    /** Remove prefixo de embalagem legado ("3 Big Bags de Papelão"). */
    private static function extractMaterial(string $nome): string
    {
        $material = preg_replace(
            '/^\d+\s+(?:big\s+bags?|sacos?|caixas?|tambores?|bombonas?|granel(?:es)?|outros?)\s+(?:de\s+)?/iu',
            '',
            trim($nome)
        ) ?? trim($nome);

        $material = preg_replace('/^\d{2}(?:\.\d{2}){0,2}-?\s*/u', '', $material) ?? $material;
        $material = trim($material, " \t-.,;");

        if (preg_match('/^(.+?)\s+\([\s\S]*$/u', $material, $m)) {
            $material = trim($m[1]);
        }

        return trim($material);
    }

    private static function matchKeywords(string $pn): ?int
    {
        $keywords = [
            ['papelao', 42], ['papel', 19],
            ['borracha', 28], ['madeira', 27], ['vidro', 21],
            ['vacinas', 16], ['frascos', 16], [' servico de saude', 16], [' rss', 16],
            ['agu', 16], ['impropri', 48], ['impr prios', 48], ['consumo ou processamento', 48], ['lacticin', 75],
            ['aplainamento', 86], ['serragem', 86], ['mdf', 87],
            ['estopa', 35], ['medicament', 18], ['perfuro', 17], ['escarif', 17],
            ['filtros contamin', 38], ['filtro contamin', 38], ['filtros papel', 39],
            ['vasilhame', 36], ['terra contamin', 37],
            ['sucata ferros', 44], ['sucata', 44], ['pneu', 53],
            ['eletronic', 29], ['lampada', 30], ['tonner', 45],
            ['plastic', 20], ['metal', 34], ['cobre', 41], ['ferro', 60],
            ['cimento', 63], ['sacaria', 70],
        ];
        foreach ($keywords as [$kw, $tid]) {
            if (str_contains($pn, $kw)) {
                return $tid;
            }
        }

        return null;
    }

    /** @return array{id:int,score:float}|null */
    private static function bestSimilar(string $pn, float $minScore): ?array
    {
        $best = 0.0;
        $bestId = null;
        foreach (self::$tipoList ?? [] as $t) {
            similar_text($pn, $t['norm'], $pct);
            if ($pct > $best && $pct >= $minScore) {
                $best = $pct;
                $bestId = $t['id'];
            }
        }

        return $bestId !== null ? ['id' => $bestId, 'score' => $best] : null;
    }

    private static function loadIndex(): void
    {
        if (self::$byCod !== null) {
            return;
        }
        self::$byCod = [];
        self::$byNomeExact = [];
        self::$byNomeNorm = [];
        self::$tipoList = [];
        foreach (EntityTipoResiduo::list('t.ativo = 1', [], '9999') as $t) {
            $id = $t->id;
            self::$byNomeExact[mb_strtolower(trim($t->nome))] = $id;
            self::$byNomeNorm[self::normNome($t->nome)] = $id;
            $cod = IbamaCodigoHelper::normalize($t->cod_ibama);
            if ($cod !== '') {
                self::$byCod[$cod] = $id;
            }
            self::$tipoList[] = ['id' => $id, 'nome' => $t->nome, 'norm' => self::normNome($t->nome)];
        }
    }

    private static function normNome(string $nome): string
    {
        $nome = mb_strtolower(trim($nome));
        $nome = preg_replace('/\s+/u', ' ', $nome) ?? $nome;
        $trans = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);

        return preg_replace('/[^a-z0-9 ]/', ' ', is_string($trans) ? $trans : $nome) ?? $nome;
    }
}
