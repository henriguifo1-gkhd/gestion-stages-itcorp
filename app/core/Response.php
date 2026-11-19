<?php

namespace App\Core;

/**
 * Petites aides pour réponses HTTP / JSON / redirections.
 */
class Response
{
    public static function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function redirect(string $to): void
    {
        header('Location: ' . $to);
        exit;
    }
}
