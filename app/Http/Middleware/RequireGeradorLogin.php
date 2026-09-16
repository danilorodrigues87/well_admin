<?php

namespace App\Http\Middleware;

use App\Common\GeradorScope;
use App\Common\OperadoraScope;
use App\Model\Entity\ClienteContrato;
use App\Service\TermosDeUsoService;
use App\Session\Gerador\Login as GeradorSession;

class RequireGeradorLogin
{
    private const TERMOS_WHITELIST = [
        '/gerador/termos-de-uso',
        '/gerador/aceita-termos',
        '/gerador/logout',
    ];

    private const CONTRATO_WHITELIST = [
        '/gerador/contrato',
        '/gerador/aceita-contrato',
    ];

    public function handle($request, $next)
    {
        if (!GeradorSession::isLogged()) {
            $request->getRouter()->redirect('/gerador/login');
        }

        $data = GeradorSession::getData();
        OperadoraScope::setOverride((int)($data['operadora_id'] ?? 1));
        GeradorScope::setOverride((int)($data['cliente_id'] ?? 0), (int)($data['operadora_id'] ?? 1));

        $uri = $request->getRouter()->getUri();
        $clienteUsuarioId = (int)($data['cliente_usuario_id'] ?? 0);
        $clienteId = (int)($data['cliente_id'] ?? 0);

        if (!in_array($uri, self::TERMOS_WHITELIST, true)) {
            if ($clienteUsuarioId > 0 && !TermosDeUsoService::clienteUsuarioAceitouVersaoAtual($clienteUsuarioId)) {
                $request->getRouter()->redirect('/gerador/termos-de-uso');
            }
        }

        $gateWhitelist = array_merge(self::TERMOS_WHITELIST, self::CONTRATO_WHITELIST);
        if (!in_array($uri, $gateWhitelist, true) && $clienteId > 0) {
            if (ClienteContrato::temPendenteAssinatura($clienteId)) {
                $request->getRouter()->redirect('/gerador/contrato');
            }
        }

        return $next($request);
    }
}
