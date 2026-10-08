<?php

/**
 * Smoke: fluxo do wizard de coleta (serviço = mesma regra da API).
 *
 * Uso: php database/scripts/api_coleta_wizard_smoke.php
 *
 * Cria rascunho → transporte → item → relatório → destinador → cancela.
 * Não chama SINIR / finalizar.
 */

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Entity\Cliente as EntityCliente;
use App\Model\Entity\Coleta as EntityColeta;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\TipoResiduo as EntityTipoResiduo;
use App\Model\Entity\Transportadora as EntityTransportadora;
use App\Model\Entity\Usuario as EntityUsuario;
use App\Model\Entity\Veiculo as EntityVeiculo;
use App\Service\ColetaApiPresenter;
use App\Service\ColetaService;

function smokeFail(string $msg): never
{
    fwrite(STDERR, '[FALHA] '.$msg.PHP_EOL);
    exit(1);
}

function smokeOk(string $msg): void
{
    echo '[OK] '.$msg.PHP_EOL;
}

$transportadoras = EntityTransportadora::listAtivas();
if ($transportadoras === []) {
    smokeFail('Nenhuma transportadora ativa.');
}
$padraoT = EntityTransportadora::getPadrao() ?? $transportadoras[0];
$presT = ColetaApiPresenter::transportadora($padraoT);
if (empty($presT['id']) || empty($presT['label'])) {
    smokeFail('Presenter transportadora incompleto.');
}
smokeOk('Catálogo transportadoras: '.count($transportadoras).' (padrão #'.$padraoT->id.')');

$destinadores = EntityDestinador::listAtivos();
if ($destinadores === []) {
    smokeFail('Nenhum destinador ativo.');
}
$padraoD = EntityDestinador::getPadrao() ?? $destinadores[0];
$presD = ColetaApiPresenter::destinador($padraoD);
if (empty($presD['id']) || empty($presD['label'])) {
    smokeFail('Presenter destinador incompleto.');
}
smokeOk('Catálogo destinadores: '.count($destinadores).' (padrão #'.$padraoD->id.')');

$clientes = EntityCliente::list("c.status = 'ativo'", [], '1');
if ($clientes === []) {
    smokeFail('Nenhum cliente ativo para iniciar rascunho.');
}
$cliente = $clientes[0];

$coletores = EntityUsuario::getColetoresAtivos();
if ($coletores === []) {
    smokeFail('Nenhum coletor ativo.');
}
$coletor = $coletores[0];

$veiculos = EntityVeiculo::list('ativo = 1', [], '1');
$veiculoId = $veiculos !== [] ? $veiculos[0]->id : 0;

$tipos = EntityTipoResiduo::list('t.ativo = 1', [], '1');
if ($tipos === []) {
    smokeFail('Nenhum tipo de resíduo ativo.');
}
$tipo = $tipos[0];

$coletaId = ColetaService::iniciarRascunho($cliente->id, $coletor->id, true, date('Y-m-d'));
smokeOk('Rascunho #'.$coletaId.' cliente #'.$cliente->id);

ColetaService::salvarEtapaTransporte($coletaId, [
    'transportadora_id' => $padraoT->id,
    'veiculo_id' => $veiculoId,
    'coletor_id' => $coletor->id,
    'motorista_nome' => $coletor->nome,
    'data_coleta' => date('Y-m-d'),
    'hora' => date('H:i'),
]);
smokeOk('Etapa 1 transporte salva');

ColetaService::adicionarItem($coletaId, $tipo->id, 1.5, 'kg');
smokeOk('Item adicionado (tipo #'.$tipo->id.')');

$resumo = ColetaService::salvarRascunhoFinal($coletaId, 'Smoke teste relatório etapa B', []);
if ((int)$resumo['itens'] < 1 || !ColetaService::rascunhoFinalConferido($coletaId)) {
    smokeFail('Rascunho final não conferido após salvar relatório.');
}
smokeOk('Etapa 3 relatório (itens='.$resumo['itens'].', evidencias='.$resumo['evidencias'].')');

$dataReceb = date('Y-m-d');
ColetaService::salvarEtapaDestinador($coletaId, [
    'destinador_id' => $padraoD->id,
    'tratamento' => 'Autoclave',
    'observacao_destinador' => 'Smoke destinador',
    'situacao_recebimento' => 'recebido',
    'data_recebimento' => $dataReceb,
]);
smokeOk('Etapa 4 destinador salva (data_recebimento='.$dataReceb.')');

// PATCH parcial só destinador (não reenvia transporte)
ColetaService::salvarTransporte($coletaId, [
    'destinador_id' => $padraoD->id,
    'tratamento' => 'Incineracao',
    'situacao_recebimento' => 'recebido',
    'data_recebimento' => $dataReceb,
]);
$detalhe = ColetaService::detalhar($coletaId);
$payload = ColetaApiPresenter::coletaDetalhe($detalhe);
if ((int)($payload['coleta']['transportadora_id'] ?? 0) !== $padraoT->id) {
    smokeFail('Presenter sem transportadora_id.');
}
if ((int)($payload['coleta']['destinador_id'] ?? 0) !== $padraoD->id) {
    smokeFail('Presenter sem destinador_id.');
}
if (($payload['coleta']['tratamento'] ?? '') !== 'Incineracao') {
    smokeFail('PATCH parcial destinador não atualizou tratamento.');
}
smokeOk('Presenter detalhe com FKs + PATCH parcial destinador');

ColetaService::cancelar($coletaId, 'Smoke api_coleta_wizard — limpeza');
$after = EntityColeta::getById($coletaId);
if (!$after || $after->status !== 'cancelada') {
    smokeFail('Cancelamento do rascunho de smoke falhou.');
}
smokeOk('Rascunho #'.$coletaId.' cancelado (limpeza)');

echo PHP_EOL.'Smoke wizard API OK.'.PHP_EOL;
exit(0);
