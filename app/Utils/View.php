<?php

namespace App\Utils;

class View
{
    private static array $vars = [];

    public static function init(array $vars = []): void
    {
        self::$vars = $vars;
    }

    private static function getContentView(string $view): string
    {
        $file = __DIR__.'/../../resources/view/'.$view.'.html';
        return file_exists($file) ? (string)file_get_contents($file) : '';
    }

    public static function render(string $view, array $vars = []): string
    {
        $contentView = self::getContentView($view);
        $vars = array_merge(self::$vars, $vars);
        $keys = array_map(fn ($item) => '{{'.$item.'}}', array_keys($vars));
        return str_replace($keys, array_values($vars), $contentView);
    }
}
