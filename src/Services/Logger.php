<?php

namespace App\Services;

/**
 * Structured JSON logger for platform observability.
 */
class Logger
{
    private static function logPath()
    {
        return __DIR__ . '/../../storage/logs/app.log';
    }

    private static function ensurePath()
    {
        $dir = dirname(self::logPath());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public static function info($message, $context = [])
    {
        self::write('INFO', $message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::write('WARNING', $message, $context);
    }

    public static function error($message, $context = [])
    {
        self::write('ERROR', $message, $context);
    }

    private static function write($level, $message, $context = [])
    {
        self::ensurePath();

        $record = [
            'ts' => date('c'),
            'level' => $level,
            'message' => $message,
            'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
            'path' => $_SERVER['REQUEST_URI'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'context' => $context
        ];

        @file_put_contents(self::logPath(), json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

