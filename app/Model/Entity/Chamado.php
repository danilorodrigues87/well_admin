<?php

namespace App\Model\Entity;

use App\Common\OperadoraScope;
use App\Model\Db\Database;
use PDO;

class Chamado
{
    public int $id = 0;
    public string $numero = '';
    public int $operadora_id = 1;
    public int $usuario_id = 0;
    public string $categoria = 'duvida';
    public string $assunto = '';
    public string $status = 'aberto';
    public string $prioridade = 'normal';
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $fechado_em = null;

    public static function tabelaExiste(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $st = (new Database())->execute("SHOW TABLES LIKE 'chamados'");
            $ok = (bool)$st->fetch();
        } catch (\Throwable) {
            $ok = false;
        }

        return $ok;
    }

    public static function getById(int $id, ?int $operadoraId = null): ?self
    {
        $operadoraId = $operadoraId ?? OperadoraScope::getOperadoraId();
        $db = new Database();
        $row = $db->execute(
            'SELECT * FROM chamados WHERE id = ? AND operadora_id = ? LIMIT 1',
            [$id, $operadoraId]
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::fromArray($row) : null;
    }

    /** @return self[] */
    public static function listForUser(int $operadoraId, int $usuarioId, bool $isAdmin, ?string $status, ?string $busca, int $limit = 100): array
    {
        $db = new Database();
        $where = 'operadora_id = ?';
        $params = [$operadoraId];
        if (!$isAdmin) {
            $where .= ' AND usuario_id = ?';
            $params[] = $usuarioId;
        }
        if ($status !== null && $status !== '' && \App\Common\Helpers\ChamadoHelper::statusValido($status)) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }
        if ($busca !== null && trim($busca) !== '') {
            $where .= ' AND (numero LIKE ? OR assunto LIKE ?)';
            $like = '%'.trim($busca).'%';
            $params[] = $like;
            $params[] = $like;
        }

        $stmt = $db->execute(
            'SELECT * FROM chamados WHERE '.$where.' ORDER BY updated_at DESC, id DESC LIMIT '.(int)$limit,
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    public function cadastrar(): bool
    {
        $db = new Database('chamados');
        $this->id = (int)$db->insert([
            'numero' => 'TMP-'.bin2hex(random_bytes(4)),
            'operadora_id' => $this->operadora_id,
            'usuario_id' => $this->usuario_id,
            'categoria' => $this->categoria ?: 'duvida',
            'assunto' => $this->assunto,
            'status' => $this->status ?: 'aberto',
            'prioridade' => $this->prioridade ?: 'normal',
        ]);
        if ($this->id <= 0) {
            return false;
        }
        $this->numero = 'WELL-'.date('Y').'-'.str_pad((string)$this->id, 5, '0', STR_PAD_LEFT);
        $db->update('id = '.$this->id, ['numero' => $this->numero]);

        return true;
    }

    public function atualizarStatus(string $status): bool
    {
        $dados = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (in_array($status, ['resolvido', 'fechado'], true)) {
            $dados['fechado_em'] = date('Y-m-d H:i:s');
            $this->fechado_em = $dados['fechado_em'];
        } else {
            $dados['fechado_em'] = null;
            $this->fechado_em = null;
        }
        $this->status = $status;

        return (new Database('chamados'))->update('id = '.(int)$this->id, $dados);
    }

    public function tocarUpdatedAt(): bool
    {
        return (new Database('chamados'))->update('id = '.(int)$this->id, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,mixed> $row */
    private static function fromArray(array $row): self
    {
        $c = new self();
        $c->id = (int)$row['id'];
        $c->numero = (string)$row['numero'];
        $c->operadora_id = (int)$row['operadora_id'];
        $c->usuario_id = (int)$row['usuario_id'];
        $c->categoria = (string)$row['categoria'];
        $c->assunto = (string)$row['assunto'];
        $c->status = (string)$row['status'];
        $c->prioridade = (string)$row['prioridade'];
        $c->created_at = isset($row['created_at']) ? (string)$row['created_at'] : null;
        $c->updated_at = isset($row['updated_at']) ? (string)$row['updated_at'] : null;
        $c->fechado_em = isset($row['fechado_em']) ? (string)$row['fechado_em'] : null;

        return $c;
    }
}
