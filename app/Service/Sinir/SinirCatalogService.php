<?php

namespace App\Service\Sinir;

use App\Common\Helpers\IbamaCodigoHelper;

/**
 * Consulta listas oficiais SINIR (POST /retornaLista*) e sugere mapeamento para tipos_residuos.
 */
class SinirCatalogService
{
    private SinirGateway $gateway;
    private SinirAuthService $auth;

    /** @var array<string,array<int,array<string,mixed>>> */
    private array $catalog = [];

    public function __construct(?SinirGateway $gateway = null, ?SinirAuthService $auth = null)
    {
        $this->gateway = $gateway ?? new SinirGateway(60);
        $this->auth = $auth ?? new SinirAuthService($this->gateway);
    }

    /**
     * @return array{ok:bool,error:?string,catalog:array<string,array<int,array<string,mixed>>>}
     */
    public function loadCatalog(): array
    {
        $tokenResult = $this->auth->obtainAccessToken();
        if (!$tokenResult['ok'] || empty($tokenResult['token'])) {
            return [
                'ok' => false,
                'error' => $tokenResult['error'] ?? 'Falha ao obter token de acesso',
                'catalog' => [],
            ];
        }

        $token = (string)$tokenResult['token'];
        // SINIR nacional (2026): listas via GET; POST retorna HTTP 405.
        $endpoints = [
            'residuos' => 'retornaListaResiduo',
            'classes' => 'retornaListaClasse',
            'unidades' => 'retornaListaUnidade',
            'tecnologias' => 'retornaListaTratamento',
            'estados' => 'retornaListaEstadoFisico',
            'acondicionamentos' => 'retornaListaAcondicionamento',
        ];

        $errors = [];
        foreach ($endpoints as $key => $path) {
            $response = $this->gateway->get($path, $token);

            if (!$response['ok']) {
                $errors[] = $path.': '.($response['error'] ?? 'HTTP '.$response['status']);
                $this->catalog[$key] = [];
                continue;
            }

            $this->catalog[$key] = $this->extractList($response['body']);
        }

        if ($errors !== [] && ($this->catalog['residuos'] ?? []) === []) {
            return [
                'ok' => false,
                'error' => implode('; ', $errors),
                'catalog' => $this->catalog,
            ];
        }

        return [
            'ok' => true,
            'error' => $errors !== [] ? implode('; ', $errors) : null,
            'catalog' => $this->catalog,
        ];
    }

    /** @return array<string,array<int,array<string,mixed>>> */
    public function getCatalog(): array
    {
        return $this->catalog;
    }

    /**
     * @param array<string,mixed> $tipoRow
     * @return array{
     *   ibama_local:string,
     *   ibama_sinir:?string,
     *   ibama_descricao:?string,
     *   tra_codigo:?int,
     *   tie_codigo:?int,
     *   tia_codigo:?int,
     *   cla_codigo:?int,
     *   uni_codigo:?int,
     *   notes:list<string>
     * }
     */
    public function suggestForTipo(array $tipoRow): array
    {
        $localIbama = IbamaCodigoHelper::normalize((string)($tipoRow['cod_ibama'] ?? ''));
        $residuo = $this->findResiduo($localIbama);
        $sinirIbama = $residuo !== null ? $this->ibamaFromResiduoRow($residuo) : null;
        $notes = [];

        if ($localIbama !== '' && $residuo === null) {
            $notes[] = 'cod_ibama não encontrado na lista SINIR';
        } elseif ($sinirIbama !== null && $localIbama !== '' && $sinirIbama !== $localIbama) {
            $notes[] = 'cod_ibama local difere do SINIR ('.$sinirIbama.')';
        }

        $nome = mb_strtolower((string)($tipoRow['nome'] ?? ''));
        $classeNome = mb_strtolower((string)($tipoRow['classe_nome'] ?? ''));
        $classeId = (int)($tipoRow['classe_id'] ?? 0);
        $hazardous = $this->isHazardousResiduo($residuo, $localIbama);

        $tie = $this->pickEstado($nome);
        $tia = $this->pickAcondicionamento($nome);
        $cla = $this->pickClasse($classeId, $classeNome, $hazardous);
        $uni = $this->pickUnidade();
        $tra = $this->pickTecnologia($nome, $classeId, $hazardous);

        if ($tie === null) {
            $notes[] = 'tie_codigo não resolvido';
        }
        if ($tia === null) {
            $notes[] = 'tia_codigo não resolvido';
        }
        if ($cla === null) {
            $notes[] = 'cla_codigo não resolvido';
        }
        if ($uni === null) {
            $notes[] = 'uni_codigo não resolvido';
        }
        if ($tra === null) {
            $notes[] = 'tra_codigo não resolvido';
        }

        return [
            'ibama_local' => $localIbama,
            'ibama_sinir' => $sinirIbama,
            'ibama_descricao' => $residuo !== null ? $this->descricaoFromResiduoRow($residuo) : null,
            'tra_codigo' => $tra,
            'tie_codigo' => $tie,
            'tia_codigo' => $tia,
            'cla_codigo' => $cla,
            'uni_codigo' => $uni,
            'notes' => $notes,
        ];
    }

    /** @param array<string,mixed> $residuoRow */
    public function ibamaFromResiduoRow(array $residuoRow): ?string
    {
        $raw = (string)($residuoRow['tpre3Numero']
            ?? $residuoRow['resCodigoIbama']
            ?? $residuoRow['resCodigo']
            ?? $residuoRow['codigoIbama']
            ?? '');

        return IbamaCodigoHelper::normalize($raw) ?: null;
    }

    /** @param array<string,mixed> $residuoRow */
    private function descricaoFromResiduoRow(array $residuoRow): ?string
    {
        $desc = trim((string)($residuoRow['tpre3Descricao']
            ?? $residuoRow['resDescricao']
            ?? $residuoRow['descricao']
            ?? ''));

        return $desc !== '' ? $desc : null;
    }

    /** @return array<string,mixed>|null */
    private function findResiduo(string $codIbama): ?array
    {
        if ($codIbama === '') {
            return null;
        }

        $index = $this->indexResiduosByIbama($this->catalog['residuos'] ?? []);
        $digits = preg_replace('/\D/', '', $codIbama) ?? '';

        return $index[$codIbama] ?? ($digits !== '' ? ($index[$digits] ?? null) : null);
    }

    /**
     * @param array<int,array<string,mixed>> $residuos
     * @return array<string,array<string,mixed>>
     */
    private function indexResiduosByIbama(array $residuos): array
    {
        $index = [];
        foreach ($residuos as $row) {
            if (!is_array($row)) {
                continue;
            }
            $ibama = $this->ibamaFromResiduoRow($row);
            if ($ibama === null) {
                continue;
            }
            $index[$ibama] = $row;
            $digits = preg_replace('/\D/', '', $ibama) ?? '';
            if ($digits !== '') {
                $index[$digits] = $row;
            }
        }

        return $index;
    }

    /** @param array<string,mixed>|null $residuoRow */
    private function isHazardousResiduo(?array $residuoRow, string $localIbama): bool
    {
        $raw = (string)($residuoRow['tpre3Numero'] ?? $residuoRow['resCodigoIbama'] ?? $localIbama);

        return str_contains($raw, '(*)') || str_contains($raw, '*');
    }

    private function pickEstado(string $nome): ?int
    {
        if (preg_match('/\b(óleo|oleo|líquido|liquido|agu|agua|água|solvente)\b/u', $nome)) {
            return $this->findInList(
                $this->catalog['estados'] ?? [],
                ['tpestCodigo', 'tieCodigo', 'codigo'],
                ['tpestDescricao', 'tieDescricao', 'descricao'],
                ['liquido']
            );
        }

        return $this->findInList(
            $this->catalog['estados'] ?? [],
            ['tpestCodigo', 'tieCodigo', 'codigo'],
            ['tpestDescricao', 'tieDescricao', 'descricao'],
            ['solido'],
            ['semiss']
        );
    }

    private function pickAcondicionamento(string $nome): ?int
    {
        $rules = [
            ['big bag', 'big bag'],
            ['tambor', 'tambor'],
            ['caixa', 'caixa'],
            ['saco', 'saco'],
            ['granel', 'granel'],
        ];

        foreach ($rules as [$needle, $keyword]) {
            if (str_contains($nome, $needle)) {
                $code = $this->findInList(
                    $this->catalog['acondicionamentos'] ?? [],
                    ['tipoCodigo', 'tiaCodigo', 'codigo'],
                    ['tipoDescricao', 'tiaDescricao', 'descricao'],
                    [$keyword]
                );
                if ($code !== null) {
                    return $code;
                }
            }
        }

        return $this->findInList(
            $this->catalog['acondicionamentos'] ?? [],
            ['tipoCodigo', 'tiaCodigo', 'codigo'],
            ['tipoDescricao', 'tiaDescricao', 'descricao'],
            ['granel', 'saco', 'caixa']
        );
    }

    private function pickClasse(int $classeId, string $classeNome, bool $hazardous): ?int
    {
        $wantClassI = $hazardous
            || in_array($classeId, [1, 2, 3, 5], true)
            || str_contains($classeNome, 'perigoso')
            || (str_contains($classeNome, 'classe i') && !str_contains($classeNome, 'classe ii'));

        foreach ($this->catalog['classes'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = $this->firstInt($item, ['tpclaCodigo', 'claCodigo', 'codigo']);
            if ($code <= 0) {
                continue;
            }
            $sigla = mb_strtolower((string)($item['tpclaSigla'] ?? $item['claSigla'] ?? ''));
            $desc = mb_strtolower($this->firstString($item, ['tpclaDescricao', 'claDescricao', 'descricao']));

            if ($wantClassI) {
                if ($sigla === 'i' || preg_match('/\bclasse\s+i(?!i)/u', $desc)) {
                    return $code;
                }
                continue;
            }

            if (str_contains($sigla, 'iia') || str_contains($sigla, 'ii a')
                || str_contains($desc, 'ii a') || str_contains($desc, 'classe ii')) {
                return $code;
            }
        }

        return null;
    }

    private function pickUnidade(): ?int
    {
        return $this->findInList(
            $this->catalog['unidades'] ?? [],
            ['tpuniCodigo', 'uniCodigo', 'codigo'],
            ['tpuniDescricao', 'uniDescricao', 'descricao', 'tpuniSigla'],
            ['quilograma', 'kg']
        ) ?? $this->findInList(
            $this->catalog['unidades'] ?? [],
            ['tpuniCodigo', 'uniCodigo', 'codigo'],
            ['tpuniDescricao', 'uniDescricao', 'descricao', 'tpuniSigla'],
            ['tonelada', 'ton']
        );
    }

    private function pickTecnologia(string $nome, int $classeId, bool $hazardous): ?int
    {
        if ($hazardous || $classeId === 1) {
            $code = $this->findInList(
                $this->catalog['tecnologias'] ?? [],
                ['tipoCodigo', 'traCodigo', 'codigo'],
                ['tipoDescricao', 'traDescricao', 'descricao'],
                ['incinera', 'tratamento']
            );
            if ($code !== null) {
                return $code;
            }
        }

        if (preg_match('/\b(rsss|saude|saúde|hospital|infect|anatom)\b/u', $nome) || $classeId === 1) {
            $code = $this->findInList(
                $this->catalog['tecnologias'] ?? [],
                ['tipoCodigo', 'traCodigo', 'codigo'],
                ['tipoDescricao', 'traDescricao', 'descricao'],
                ['rsss', 'saúde', 'saude', 'incinera']
            );
            if ($code !== null) {
                return $code;
            }
        }

        if (preg_match('/\b(compost|organ|poda|jardim)\b/u', $nome)) {
            $code = $this->findInList(
                $this->catalog['tecnologias'] ?? [],
                ['tipoCodigo', 'traCodigo', 'codigo'],
                ['tipoDescricao', 'traDescricao', 'descricao'],
                ['compost']
            );
            if ($code !== null) {
                return $code;
            }
        }

        return $this->findInList(
            $this->catalog['tecnologias'] ?? [],
            ['tipoCodigo', 'traCodigo', 'codigo'],
            ['tipoDescricao', 'traDescricao', 'descricao'],
            ['reciclagem', 'recicl']
        ) ?? $this->findInList(
            $this->catalog['tecnologias'] ?? [],
            ['tipoCodigo', 'traCodigo', 'codigo'],
            ['tipoDescricao', 'traDescricao', 'descricao'],
            ['aterro classe ii', 'aterro']
        );
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param list<string> $codeKeys
     * @param list<string> $descKeys
     * @param list<string> $keywords
     * @param list<string> $excludeKeywords
     */
    private function findInList(
        array $items,
        array $codeKeys,
        array $descKeys,
        array $keywords,
        array $excludeKeywords = []
    ): ?int {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $desc = $this->normalizeSearch($this->firstString($item, $descKeys));
            if ($desc === '') {
                continue;
            }
            $excluded = false;
            foreach ($excludeKeywords as $exclude) {
                if (str_contains($desc, $this->normalizeSearch($exclude))) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }
            foreach ($keywords as $keyword) {
                if (str_contains($desc, $this->normalizeSearch($keyword))) {
                    $code = $this->firstInt($item, $codeKeys);
                    if ($code > 0) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    /** @param array<string,mixed> $item @param list<string> $keys */
    private function firstString(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (!empty($item[$key])) {
                return (string)$item[$key];
            }
        }

        return '';
    }

    /** @param array<string,mixed> $item @param list<string> $keys */
    private function firstInt(array $item, array $keys): int
    {
        foreach ($keys as $key) {
            if (isset($item[$key]) && is_numeric($item[$key])) {
                return (int)$item[$key];
            }
        }

        return 0;
    }

    private function normalizeSearch(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return str_replace(
            ['á', 'à', 'â', 'ã', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ü', 'ç'],
            ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'u', 'c'],
            $value
        );
    }

    /** @param array<string,mixed>|list<mixed>|null $body @return array<int,array<string,mixed>> */
    private function extractList(?array $body): array
    {
        if ($body === null) {
            return [];
        }
        if (array_is_list($body)) {
            return array_values(array_filter($body, 'is_array'));
        }
        foreach (['objetoResposta', 'lista', 'dados', 'residuos'] as $key) {
            if (isset($body[$key]) && is_array($body[$key]) && array_is_list($body[$key])) {
                return array_values(array_filter($body[$key], 'is_array'));
            }
        }

        return [];
    }
}
