<?php

namespace App\Service;

use App\Common\CompanyConfig;
use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\ClienteUsuario;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\ColetaSolicitacao;
use App\Utils\View;

class GeradorNotificacaoService
{
    /** @return list<string> */
    private static function destinatariosCliente(int $clienteId): array
    {
        $emails = [];
        $cliente = EntityCliente::getById($clienteId);
        if ($cliente && trim($cliente->email) !== '' && filter_var($cliente->email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = strtolower(trim($cliente->email));
        }

        $usuario = ClienteUsuario::getByClienteId($clienteId);
        if ($usuario && trim($usuario->email) !== '' && filter_var($usuario->email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = strtolower(trim($usuario->email));
        }

        return array_values(array_unique($emails));
    }

    public static function solicitacaoAprovada(int $solicitacaoId): void
    {
        $s = ColetaSolicitacao::getById($solicitacaoId);
        if (!$s || $s->status !== 'aprovada') {
            return;
        }

        $cliente = EntityCliente::getById($s->cliente_id);
        if (!$cliente) {
            return;
        }

        $data = $s->data_aprovada ?? $s->data_desejada;
        $dataBr = $data ? date('d/m/Y', strtotime($data)) : '—';
        $portal = URL.'/gerador/agendamentos';
        $resposta = trim((string)($s->resposta_admin ?? ''));

        $html = View::render('email/gerador_solicitacao_aprovada', [
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'data_coleta' => htmlspecialchars($dataBr, ENT_QUOTES, 'UTF-8'),
            'tipo' => $s->tipo === 'extra' ? 'Coleta extra (valor negociado)' : 'Coleta inclusa no plano',
            'bloco_valor_extra' => ($s->tipo === 'extra' && $s->valor_cobranca_extra)
                ? '<li><strong>Valor extra:</strong> R$ '
                    .htmlspecialchars(number_format((float)$s->valor_cobranca_extra, 2, ',', '.'), ENT_QUOTES, 'UTF-8')
                    .'</li>'
                : '',
            'bloco_resposta' => $resposta !== ''
                ? '<p><strong>Observação da equipe:</strong><br>'
                    .htmlspecialchars($resposta, ENT_QUOTES, 'UTF-8').'</p>'
                : '',
            'portal_url' => htmlspecialchars($portal, ENT_QUOTES, 'UTF-8'),
            'empresa_nome' => htmlspecialchars(CompanyConfig::name(), ENT_QUOTES, 'UTF-8'),
            'empresa_sigla' => htmlspecialchars(CompanyConfig::shortName(), ENT_QUOTES, 'UTF-8'),
        ]);

        $subject = CompanyConfig::shortName().' — coleta agendada para '.$dataBr;
        self::enviarParaCliente($s->cliente_id, $subject, $html);
    }

    public static function solicitacaoRecusada(int $solicitacaoId): void
    {
        $s = ColetaSolicitacao::getById($solicitacaoId);
        if (!$s || $s->status !== 'recusada') {
            return;
        }

        $cliente = EntityCliente::getById($s->cliente_id);
        if (!$cliente) {
            return;
        }

        $portal = URL.'/gerador/agendamentos';
        $html = View::render('email/gerador_solicitacao_recusada', [
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'motivo' => htmlspecialchars(trim((string)($s->resposta_admin ?? '')), ENT_QUOTES, 'UTF-8'),
            'portal_url' => htmlspecialchars($portal, ENT_QUOTES, 'UTF-8'),
            'empresa_nome' => htmlspecialchars(CompanyConfig::name(), ENT_QUOTES, 'UTF-8'),
            'empresa_sigla' => htmlspecialchars(CompanyConfig::shortName(), ENT_QUOTES, 'UTF-8'),
        ]);

        $subject = CompanyConfig::shortName().' — solicitação de coleta não aprovada';
        self::enviarParaCliente($s->cliente_id, $subject, $html);
    }

    public static function coletaMtrDisponivel(int $coletaId): void
    {
        $coleta = EntityColeta::getById($coletaId);
        if (!$coleta || $coleta->status !== 'finalizada') {
            return;
        }

        $cliente = EntityCliente::getById((int)$coleta->cliente_id);
        if (!$cliente) {
            return;
        }

        $numero = \App\Common\Helpers\ColetaMtrHelper::numeroExibicao($coleta) ?? '—';
        $portal = URL.'/gerador/coletas/'.$coletaId;

        $html = View::render('email/gerador_mtr_disponivel', [
            'cliente_nome' => htmlspecialchars($cliente->nome_fantasia, ENT_QUOTES, 'UTF-8'),
            'numero_mtr' => htmlspecialchars((string)$numero, ENT_QUOTES, 'UTF-8'),
            'data_coleta' => $coleta->data_coleta
                ? htmlspecialchars(date('d/m/Y', strtotime($coleta->data_coleta)), ENT_QUOTES, 'UTF-8')
                : '—',
            'portal_url' => htmlspecialchars($portal, ENT_QUOTES, 'UTF-8'),
            'empresa_nome' => htmlspecialchars(CompanyConfig::name(), ENT_QUOTES, 'UTF-8'),
            'empresa_sigla' => htmlspecialchars(CompanyConfig::shortName(), ENT_QUOTES, 'UTF-8'),
        ]);

        $subject = CompanyConfig::shortName().' — MTR '.$numero.' disponível no portal';
        self::enviarParaCliente((int)$coleta->cliente_id, $subject, $html);
    }

    private static function enviarParaCliente(int $clienteId, string $subject, string $html): void
    {
        foreach (self::destinatariosCliente($clienteId) as $email) {
            MailService::enviarHtml($email, $subject, $html);
        }
    }
}
