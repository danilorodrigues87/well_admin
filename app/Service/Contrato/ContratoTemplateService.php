<?php

namespace App\Service\Contrato;

use App\Common\Contrato\ContractType;
use App\Model\Entity\ClienteContrato;
use App\Model\Entity\ContratoModelo;
use App\Model\Entity\Plano;

class ContratoTemplateService
{
    public static function renderFromContratoId(int $contratoId): string
    {
        $contrato = ClienteContrato::getById($contratoId);
        if (!$contrato) {
            throw new \InvalidArgumentException('Contrato não encontrado.');
        }

        $plano = Plano::getById($contrato->plano_id);
        $slug = ContractType::normalize($plano?->contrato_modelo_tipo ?? ContractType::GENERICO);
        if ($slug === ContractType::GENERICO) {
            throw new \RuntimeException('Modelo genérico não usa template DB.');
        }

        $modelo = null;
        if ($contrato->contrato_modelo_id) {
            $modelo = ContratoModelo::getById((int)$contrato->contrato_modelo_id);
        }
        if ($modelo === null) {
            $modelo = ContratoModelo::resolveAtivo($contrato->operadora_id, $slug);
        }
        if ($modelo === null) {
            throw new \RuntimeException('Modelo de contrato não encontrado: '.$slug);
        }

        return self::render($modelo, $contrato);
    }

    public static function render(ContratoModelo $modelo, ClienteContrato $contrato): string
    {
        $vars = ContratoVariableResolver::forContrato($contrato, $modelo);
        $body = self::applyPlaceholders($modelo->body_html, $vars);

        $shellPath = dirname(__DIR__, 3).'/resources/view/contratos/shell.html';
        if (is_file($shellPath)) {
            $shell = file_get_contents($shellPath);
            if ($shell !== false) {
                $shell = str_replace('{{titulo}}', $vars['titulo'] ?? 'Contrato', $shell);
                $shell = str_replace('{{URL}}', $vars['URL'] ?? '', $shell);
                $shell = str_replace('{{body_html}}', $body, $shell);
                $shell = str_replace('{{logo_html}}', $vars['logo_html'] ?? '', $shell);

                return $shell;
            }
        }

        return self::wrapMinimal($vars['titulo'] ?? 'Contrato', $vars['URL'] ?? '', $body);
    }

    /** @param array<string,string> $vars */
    private static function applyPlaceholders(string $html, array $vars): string
    {
        foreach ($vars as $key => $val) {
            $html = str_replace('{{'.$key.'}}', $val, $html);
        }

        return preg_replace('/\{\{[a-z0-9_]+\}\}/i', '', $html) ?? $html;
    }

    private static function wrapMinimal(string $titulo, string $url, string $body): string
    {
        $t = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');

        return '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8"><title>'.$t
            .'</title><link rel="stylesheet" href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            .'/resources/css/contrato.css"></head><body>'.$body.'</body></html>';
    }
}
