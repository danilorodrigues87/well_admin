<?php

namespace App\Model\Entity\Concerns;

use App\Common\OperadoraScope;

/** Filtro operadora_id em queries de dados tenant. */
trait TenantScoped
{
    /**
     * @param array<int|string, mixed> $params
     * @return array{0: string, 1: array<int|string, mixed>}
     */
    protected static function tenantWhere(string $where, array $params, string $column = 'operadora_id'): array
    {
        $params[] = OperadoraScope::getOperadoraId();
        $where = trim($where);
        if ($where === '' || $where === '1=1') {
            return [$column.' = ?', $params];
        }

        return [$where.' AND '.$column.' = ?', $params];
    }

    /** @return array{0: int} */
    protected static function tenantIdParams(): array
    {
        return [OperadoraScope::getOperadoraId()];
    }

    protected static function ensureTenantInsert(array $data): array
    {
        if (!isset($data['operadora_id'])) {
            $data['operadora_id'] = OperadoraScope::getOperadoraId();
        }

        return $data;
    }
}
