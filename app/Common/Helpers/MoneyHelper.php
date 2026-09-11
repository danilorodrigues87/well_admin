<?php

namespace App\Common\Helpers;

class MoneyHelper
{
    public static function format(float|string|null $value): string
    {
        return 'R$ '.number_format((float)$value, 2, ',', '.');
    }

    public static function parse(string $raw): float
    {
        $raw = trim(str_replace(['R$', ' '], '', $raw));
        if ($raw === '') {
            return 0.0;
        }
        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }
        return (float)$raw;
    }
}
