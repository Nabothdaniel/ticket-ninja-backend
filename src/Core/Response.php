<?php

namespace App\Core;

/**
 * Response Class
 * 
 * Standardized API response formatting
 */
class Response
{
    /**
     * Send a success response
     */
    public static function success($data = null, $message = 'Success', $code = 200)
    {
        http_response_code($code);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
    
    /**
     * Send an error response
     */
    public static function error($message = 'Error', $code = 400, $errors = null)
    {
        http_response_code($code);
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send a validation error response
     */
    public static function validationError($errors, $message = 'Validation failed')
    {
        self::error($message, 422, $errors);
    }
    
    /**
     * Send an unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        self::error($message, 401);
    }
    
    /**
     * Send a forbidden response
     */
    public static function forbidden($message = 'Forbidden')
    {
        self::error($message, 403);
    }
    
    /**
     * Send a not found response
     */
    public static function notFound($message = 'Resource not found')
    {
        self::error($message, 404);
    }
}
?>
