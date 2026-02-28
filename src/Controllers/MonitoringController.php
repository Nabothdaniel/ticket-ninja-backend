<?php

namespace App\Controllers;

use App\Core\Response;
use App\Middleware\AuthMiddleware;

/**
 * Monitoring and basic operational metrics endpoints.
 */
class MonitoringController
{
    public function health()
    {
        Response::success([
            'status' => 'ok',
            'time' => date('c'),
            'php_version' => PHP_VERSION
        ], 'Service healthy');
    }

    public function metrics()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            Response::forbidden('Admin access required');
        }

        $logPath = __DIR__ . '/../../storage/logs/app.log';
        $logSize = file_exists($logPath) ? filesize($logPath) : 0;
        $lineCount = 0;
        $errorCount = 0;

        if (file_exists($logPath)) {
            $fh = fopen($logPath, 'r');
            if ($fh) {
                while (($line = fgets($fh)) !== false) {
                    $lineCount++;
                    if (strpos($line, '"level":"ERROR"') !== false) {
                        $errorCount++;
                    }
                }
                fclose($fh);
            }
        }

        Response::success([
            'time' => date('c'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'log_file_size_bytes' => $logSize,
            'log_lines' => $lineCount,
            'error_lines' => $errorCount
        ], 'Monitoring metrics');
    }
}

