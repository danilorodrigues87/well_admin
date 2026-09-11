<?php

namespace App\Controller\Admin;

class Alert
{
    public static function getError(string $message): string
    {
        return '<div class="alert alert-danger" role="alert">'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</div>';
    }

    public static function getSuccess(string $message): string
    {
        return '<div class="alert alert-success" role="alert">'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</div>';
    }
}
