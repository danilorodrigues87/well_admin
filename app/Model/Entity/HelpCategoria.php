<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class HelpCategoria
{
    public int $id = 0;
    public string $titulo = '';
    public string $slug = '';
    public int $ordem = 0;
    public int $ativo = 1;

    public static function tabelasExistem(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = (new Database())->execute("SHOW TABLES LIKE 'help_categorias'");
            $ok = (bool)$st->fetch();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    /** @return self[] */
    public static function listAll(bool $onlyActive = true): array
    {
        $db = new Database();
        $sql = 'SELECT * FROM help_categorias';
        if ($onlyActive) {
            $sql .= ' WHERE ativo = 1';
        }
        $sql .= ' ORDER BY ordem ASC, titulo ASC';
        $stmt = $db->execute($sql);
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->titulo = (string)$row['titulo'];
        $c->slug = (string)$row['slug'];
        $c->ordem = (int)$row['ordem'];
        $c->ativo = (int)$row['ativo'];

        return $c;
    }
}
