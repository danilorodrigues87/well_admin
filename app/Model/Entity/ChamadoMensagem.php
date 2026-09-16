<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use PDO;

class ChamadoMensagem
{
    public int $id = 0;
    public int $chamado_id = 0;
    public string $autor_tipo = 'usuario';
    public int $autor_id = 0;
    public string $mensagem = '';
    public ?string $anexo_path = null;
    public ?string $anexo_nome = null;
    public ?string $created_at = null;

    public static function tabelaExiste(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = (new Database())->execute("SHOW TABLES LIKE 'chamado_mensagens'");
            $ok = (bool)$st->fetch();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $row = $db->execute('SELECT * FROM chamado_mensagens WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    /** @return self[] */
    public static function listarPorChamado(int $chamadoId): array
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM chamado_mensagens WHERE chamado_id = ? ORDER BY created_at ASC, id ASC',
            [$chamadoId]
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    public function cadastrar(): bool
    {
        $db = new Database('chamado_mensagens');
        $this->id = (int)$db->insert([
            'chamado_id' => $this->chamado_id,
            'autor_tipo' => $this->autor_tipo,
            'autor_id' => $this->autor_id,
            'mensagem' => $this->mensagem,
            'anexo_path' => $this->anexo_path,
            'anexo_nome' => $this->anexo_nome,
        ]);

        return $this->id > 0;
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $m = new self();
        $m->id = (int)$row['id'];
        $m->chamado_id = (int)$row['chamado_id'];
        $m->autor_tipo = (string)$row['autor_tipo'];
        $m->autor_id = (int)$row['autor_id'];
        $m->mensagem = (string)$row['mensagem'];
        $m->anexo_path = isset($row['anexo_path']) ? (string)$row['anexo_path'] : null;
        $m->anexo_nome = isset($row['anexo_nome']) ? (string)$row['anexo_nome'] : null;
        $m->created_at = isset($row['created_at']) ? (string)$row['created_at'] : null;

        return $m;
    }
}
