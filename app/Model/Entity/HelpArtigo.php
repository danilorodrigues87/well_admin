<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class HelpArtigo
{
    public int $id = 0;
    public int $id_categoria = 0;
    public string $titulo = '';
    public string $slug = '';
    public string $resumo = '';
    public string $corpo = '';
    public ?string $video_url = null;
    public int $publicado = 0;

    /** @return self[] */
    public static function listByCategoria(int $categoriaId, bool $onlyPublished = true): array
    {
        $db = new Database();
        $sql = 'SELECT * FROM help_artigos WHERE id_categoria = ?';
        $params = [$categoriaId];
        if ($onlyPublished) {
            $sql .= ' AND publicado = 1';
        }
        $sql .= ' ORDER BY ordem ASC, titulo ASC';
        $stmt = $db->execute($sql, $params);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    public static function getBySlug(string $slug, bool $onlyPublished = true): ?self
    {
        $db = new Database();
        $sql = 'SELECT * FROM help_artigos WHERE slug = ?';
        $params = [$slug];
        if ($onlyPublished) {
            $sql .= ' AND publicado = 1';
        }
        $sql .= ' LIMIT 1';
        $row = $db->execute($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $a = new self();
        $a->id = (int)$row['id'];
        $a->id_categoria = (int)$row['id_categoria'];
        $a->titulo = (string)$row['titulo'];
        $a->slug = (string)$row['slug'];
        $a->resumo = (string)($row['resumo'] ?? '');
        $a->corpo = (string)($row['corpo'] ?? '');
        $a->video_url = isset($row['video_url']) ? (string)$row['video_url'] : null;
        $a->publicado = (int)($row['publicado'] ?? 0);

        return $a;
    }
}
