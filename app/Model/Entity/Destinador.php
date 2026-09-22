<?php

namespace App\Model\Entity;

use App\Model\Db\Database;
use App\Model\Entity\Concerns\TenantScoped;
use PDO;

class Destinador
{
    use TenantScoped;

    public int $id = 0;
    public int $operadora_id = 1;
    public string $nome = '';
    public string $cnpj = '';
    public string $endereco = '';
    public string $telefone = '';
    public string $responsavel = '';
    public ?int $sinir_cod_unidade = null;
    public int $is_padrao = 0;
    public int $ativo = 1;

    public static function count(string $where = '1=1', array $params = []): int
    {
        [$where, $params] = self::tenantWhere($where, $params);
        $db = new Database();
        $stmt = $db->execute('SELECT COUNT(*) AS qtd FROM destinadores WHERE '.$where, $params);

        return (int)$stmt->fetch(PDO::FETCH_ASSOC)['qtd'];
    }

    /** @return self[] */
    public static function list(string $where, array $params, string $limit): array
    {
        [$where, $params] = self::tenantWhere($where, $params);
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM destinadores WHERE '.$where.' ORDER BY is_padrao DESC, nome ASC LIMIT '.$limit,
            $params
        );
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = self::fromArray($row);
        }

        return $items;
    }

    /** @return self[] */
    public static function listAtivos(): array
    {
        return self::list('ativo = 1', [], '200');
    }

    public static function getById(int $id): ?self
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM destinadores WHERE id = ? AND operadora_id = ?',
            [$id, ...self::tenantIdParams()]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function getPadrao(): ?self
    {
        $db = new Database();
        $stmt = $db->execute(
            'SELECT * FROM destinadores WHERE operadora_id = ? AND is_padrao = 1 AND ativo = 1 LIMIT 1',
            self::tenantIdParams()
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function insert(array $data): int
    {
        return (int)(new Database('destinadores'))->insert(self::ensureTenantInsert($data));
    }

    public static function update(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $db = new Database();
        $fields = array_keys($data);
        $db->execute(
            'UPDATE destinadores SET '.implode('=?, ', $fields).'=? WHERE id = ? AND operadora_id = ?',
            [...array_values($data), $id, ...self::tenantIdParams()]
        );
    }

    /** @param array<string, mixed> $row */
    private static function fromArray(array $row): self
    {
        $d = new self();
        $d->id = (int)$row['id'];
        $d->operadora_id = (int)($row['operadora_id'] ?? 1);
        $d->nome = (string)$row['nome'];
        $d->cnpj = (string)$row['cnpj'];
        $d->endereco = (string)($row['endereco'] ?? '');
        $d->telefone = (string)($row['telefone'] ?? '');
        $d->responsavel = (string)($row['responsavel'] ?? '');
        $d->sinir_cod_unidade = isset($row['sinir_cod_unidade']) && $row['sinir_cod_unidade'] !== null
            ? (int)$row['sinir_cod_unidade'] : null;
        $d->is_padrao = (int)($row['is_padrao'] ?? 0);
        $d->ativo = (int)($row['ativo'] ?? 1);

        return $d;
    }
}
