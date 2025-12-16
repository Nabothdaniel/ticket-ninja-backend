<?php

namespace App\Middleware;

use App\Core\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Authentication Middleware
 * 
 * Validates JWT tokens and protects routes
 */
class AuthMiddleware
{
    /**
     * Handle the middleware logic
     */
    public function handle()
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (empty($authHeader)) {
            Response::unauthorized('Authorization header missing');
            return false;
        }
        
        // Extract token from "Bearer <token>"
        $token = str_replace('Bearer ', '', $authHeader);
        
        if (empty($token)) {
            Response::unauthorized('Token missing');
            return false;
        }
        
        try {
            $secret = $_ENV['JWT_SECRET'] ??'your-secret-key-change-this-in-production';
            $algorithm = $_ENV['JWT_ALGORITHM'] ?? 'HS256';
            
            $decoded = JWT::decode($token, new Key($secret, $algorithm));
            
            // Store user data in global scope for controller access
            $GLOBALS['auth_user'] = (array) $decoded;
            
            return true;
            
        } catch (\Exception $e) {
            Response::unauthorized('Invalid or expired token: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get authenticated user from token
     */
    public static function getAuthUser()
    {
        return $GLOBALS['auth_user'] ?? null;
    }
}
?>
