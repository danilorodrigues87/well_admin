<?php

namespace App\Common;

use App\Http\GeradorContext;
use App\Session\Gerador\Login as GeradorSession;

class GeradorScope
{
    private static ?int $overrideClienteId = null;
    private static ?int $overrideOperadoraId = null;

    public static function setOverride(?int $clienteId, ?int $operadoraId = null): void
    {
        self::$overrideClienteId = $clienteId !== null && $clienteId > 0 ? $clienteId : null;
        self::$overrideOperadoraId = $operadoraId !== null && $operadoraId > 0 ? $operadoraId : null;
    }

    public static function getClienteId(): int
    {
        if (self::$overrideClienteId !== null) {
            return self::$overrideClienteId;
        }
        $ctx = GeradorContext::clienteId();
        if ($ctx > 0) {
            return $ctx;
        }
        $session = GeradorSession::getData();

        return (int)($session['cliente_id'] ?? 0);
    }

    public static function getOperadoraId(): int
    {
        if (self::$overrideOperadoraId !== null) {
            return self::$overrideOperadoraId;
        }
        $ctx = GeradorContext::operadoraId();
        if ($ctx > 0) {
            return $ctx;
        }
        $session = GeradorSession::getData();

        return (int)($session['operadora_id'] ?? 1);
    }

    public static function pertenceColeta(int $coletaId): bool
    {
        if ($coletaId <= 0 || self::getClienteId() <= 0) {
            return false;
        }

        return OperadoraScope::pertence('coletas', $coletaId)
            && self::coletaDoCliente($coletaId);
    }

    private static function coletaDoCliente(int $coletaId): bool
    {
        $db = new \App\Model\Db\Database();
        $row = $db->execute(
            'SELECT cliente_id FROM coletas WHERE id = ? AND operadora_id = ? LIMIT 1',
            [$coletaId, self::getOperadoraId()]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row && (int)$row['cliente_id'] === self::getClienteId();
    }
}
