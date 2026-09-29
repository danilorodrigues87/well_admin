<?php

namespace App\Model\Entity;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use PDO;

class ContratoModelo
{
    public int $id = 0;
    public int $operadora_id = 1;
    public string $slug = '';
    public string $nome = '';
    public int $versao = 1;
    public string $titulo = '';
    public string $body_html = '';
    public string $pricing_variant = 'FRANQUIA_KG';
    public ?string $flags_json = null;
    public int $ativo = 1;

    public static function tabelaExiste(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $ok = (bool)(new Database())->execute("SHOW TABLES LIKE 'contrato_modelos'")->fetch();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    public static function resolveAtivo(int $operadoraId, string $slug): ?self
    {
        if (!self::tabelaExiste() || trim($slug) === '') {
            return null;
        }
        $db = new Database();
        $row = $db->execute(
            'SELECT * FROM contrato_modelos
             WHERE operadora_id = ? AND slug = ? AND ativo = 1
             ORDER BY versao DESC LIMIT 1',
            [$operadoraId, $slug]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    public static function getById(int $id): ?self
    {
        if (!self::tabelaExiste() || $id <= 0) {
            return null;
        }
        $db = new Database();
        $row = $db->execute(
            'SELECT * FROM contrato_modelos WHERE id = ? AND operadora_id = ? LIMIT 1',
            [$id, OperadoraScope::getOperadoraId()]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    /** @return self[] */
    public static function listLatestBySlug(): array
    {
        if (!self::tabelaExiste()) {
            return [];
        }
        $db = new Database();
        $stmt = $db->execute(
            'SELECT cm.* FROM contrato_modelos cm
             INNER JOIN (
               SELECT operadora_id, slug, MAX(versao) AS max_ver
               FROM contrato_modelos
               WHERE operadora_id = ?
               GROUP BY operadora_id, slug
             ) t ON t.operadora_id = cm.operadora_id AND t.slug = cm.slug AND t.max_ver = cm.versao
             ORDER BY cm.nome',
            [OperadoraScope::getOperadoraId()]
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    public static function insert(array $data): int
    {
        $data['operadora_id'] = OperadoraScope::getOperadoraId();

        return (int)(new Database('contrato_modelos'))->insert($data);
    }

    public static function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE contrato_modelos SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, OperadoraScope::getOperadoraId()]
        );
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $m = new self();
        $m->id = (int)$row['id'];
        $m->operadora_id = (int)$row['operadora_id'];
        $m->slug = (string)$row['slug'];
        $m->nome = (string)$row['nome'];
        $m->versao = (int)$row['versao'];
        $m->titulo = (string)$row['titulo'];
        $m->body_html = (string)$row['body_html'];
        $m->pricing_variant = (string)($row['pricing_variant'] ?? 'FRANQUIA_KG');
        $m->flags_json = isset($row['flags_json']) && $row['flags_json'] !== null
            ? (is_string($row['flags_json']) ? $row['flags_json'] : json_encode($row['flags_json'], JSON_UNESCAPED_UNICODE))
            : null;
        $m->ativo = (int)($row['ativo'] ?? 1);

        return $m;
    }
}
