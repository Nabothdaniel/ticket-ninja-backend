<?php

namespace App\Core;

/**
 * Response Class
 * 
 * Standardized API response formatting
 */
class Response
{
    private static $requestId = null;

    /**
     * Set request id for response correlation
     */
    public static function setRequestId($requestId)
    {
        self::$requestId = $requestId;
    }

    /**
     * Get request id from context
     */
    private static function getRequestId()
    {
        if (self::$requestId) {
            return self::$requestId;
        }

        if (!empty($_SERVER['HTTP_X_REQUEST_ID'])) {
            self::$requestId = $_SERVER['HTTP_X_REQUEST_ID'];
            return self::$requestId;
        }

        return null;
    }

    /**
     * Send a success response
     */
    public static function success($data = null, $message = 'Success', $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];

        $requestId = self::getRequestId();
        if ($requestId) {
            $response['request_id'] = $requestId;
        }

        echo json_encode($response);
        exit;
    }
    
    /**
     * Send an error response
     */
    public static function error($message = 'Error', $code = 400, $errors = null, $errorCode = null)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        $requestId = self::getRequestId();
        if ($errorCode === null) {
            $errorCode = self::defaultErrorCode($code);
        }

        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode
        ];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        if ($requestId) {
            $response['request_id'] = $requestId;
        }
        
        echo json_encode($response);
        exit;
    }

    /**
     * Default error code map by status
     */
    private static function defaultErrorCode($code)
    {
        $map = [
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            422 => 'validation_error',
            429 => 'rate_limited',
            500 => 'internal_error'
        ];

        return $map[$code] ?? 'unknown_error';
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
