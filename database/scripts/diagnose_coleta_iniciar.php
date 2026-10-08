<?php

/**
 * Diagnóstico rápido do botão Coletar (iniciar rascunho).
 * Uso: php database/scripts/diagnose_coleta_iniciar.php
 */
require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;
use App\Model\Entity\Destinador as EntityDestinador;
use App\Model\Entity\Transportadora as EntityTransportadora;

$db = new Database();

$cols = $db->execute("SHOW COLUMNS FROM coletas LIKE 'client_uuid'")->fetchAll(\PDO::FETCH_ASSOC);
echo 'client_uuid: '.(empty($cols) ? 'MISSING — rode apply_migration_051.php' : 'OK').PHP_EOL;

$tr = EntityTransportadora::getPadrao();
echo 'transportadora padrão: '.($tr ? '#'.$tr->id.' '.$tr->nome : 'MISSING').PHP_EOL;

$de = EntityDestinador::getPadrao();
echo 'destinador padrão: '.($de ? '#'.$de->id.' '.$de->nome : 'MISSING').PHP_EOL;

if (empty($cols)) {
    exit(1);
}
echo "ok\n";
