<?php

namespace App\Middleware;

use App\Core\Response;

/**
 * Security Middleware
 * 
 * Handles input sanitization and basic rate limiting
 */
class SecurityMiddleware
{
    /**
     * Sanitize all incoming global data
     */
    public function sanitize()
    {
        $_GET = $this->sanitizeArray($_GET);
        $_POST = $this->sanitizeArray($_POST);
        
        // Sanitize JSON input if present
        $json = file_get_contents('php://input');
        if (!empty($json)) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $sanitized = $this->sanitizeArray($data);
                // We can't easily overwrite php://input, but we can store it in a global or similar
                $GLOBALS['sanitized_input'] = $sanitized;
            }
        }
        
        return true;
    }

    /**
     * Recursive array sanitization
     */
    private function sanitizeArray($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sanitizeArray($value);
            } else {
                $array[$key] = htmlspecialchars(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');
            }
        }
        return $array;
    }

    /**
     * Basic Rate Limiting
     * Uses a simple flat file/memory cache approach for demo
     */
    public function rateLimit($limit = 60, $period = 60)
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'rate_limit_' . md5($ip);
        $file = sys_get_temp_dir() . '/' . $key;

        $now = time();
        $data = ['count' => 0, 'start' => $now];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
        }

        if ($now - $data['start'] > $period) {
            $data = ['count' => 1, 'start' => $now];
        } else {
            $data['count']++;
        }

        file_put_contents($file, json_encode($data));

        if ($data['count'] > $limit) {
            Response::error('Too many requests. Please try again later.', 429);
            return false;
        }

        return true;
    }
}
