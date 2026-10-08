<?php

require dirname(__DIR__, 2).'/includes/app.php';

use App\Model\Db\Database;

$db = new Database();
$check = $db->execute("SHOW COLUMNS FROM coletas LIKE 'client_uuid'")->fetch(PDO::FETCH_ASSOC);
if ($check) {
    echo "client_uuid already exists\n";
    exit(0);
}

$db->execute(
    'ALTER TABLE coletas
      ADD COLUMN client_uuid char(36) DEFAULT NULL AFTER id,
      ADD UNIQUE KEY uk_coletas_operadora_client_uuid (operadora_id, client_uuid)'
);
echo "migration 051 applied\n";
