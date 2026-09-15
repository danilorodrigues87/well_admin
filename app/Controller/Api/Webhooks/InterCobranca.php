<?php

namespace App\Controller\Api\Webhooks;

use App\Common\Helpers\ApiHelper;
use App\Common\InterConfig;
use App\Http\Response;
use App\Service\Inter\InterWebhookService;

class InterCobranca
{
    public static function receber($request): Response
    {
        $secret = InterConfig::webhookSecret();
        if ($secret !== '') {
            $header = trim((string)($_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? ''));
            if (!hash_equals($secret, $header)) {
                return ApiHelper::error('Não autorizado', 401);
            }
        }

        $raw = (string)file_get_contents('php://input');
        $result = InterWebhookService::processCobrancaPayload($raw);

        if (!$result['ok']) {
            return ApiHelper::error($result['errors'][0] ?? 'Payload inválido', 400);
        }

        return ApiHelper::ok([
            'processed' => $result['processed'],
            'skipped' => $result['skipped'],
        ]);
    }
}
