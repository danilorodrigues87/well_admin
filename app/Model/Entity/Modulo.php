<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class Modulo
{
    public int $id = 0;
    public string $slug = '';
    public string $label = '';
    public string $grupo = '';

    public static function getAll(): array
    {
        $db = new Database('modulos');
        $stmt = $db->select(null, 'ordem ASC, label ASC');
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $m = new self();
            $m->id = (int)$row['id'];
            $m->slug = (string)$row['slug'];
            $m->label = (string)$row['label'];
            $m->grupo = (string)$row['grupo'];
            $items[] = $m;
        }
        return $items;
    }
}
