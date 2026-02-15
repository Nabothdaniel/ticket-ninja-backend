<?php

namespace App\Core;

/**
 * Application Core Class
 * 
 * Handles routing, middleware, and request processing
 */
class Application
{
    private $router;
    private $middleware = [];
    
    public function __construct()
    {
        $this->router = new Router();
        $this->registerRoutes();
    }
    
    /**
     * Register all application routes
     */
    private function registerRoutes()
    {
        // Health check endpoint
        $this->router->get('/', function() {
            return Response::success([
                'message' => 'Welcome to TicketNinja API',
                'version' => '1.0.0',
                'features' => [
                    'Event Management',
                    'AI-Powered Analytics',
                    'Attendee Tracking',
                    'Withdrawal Management',
                    'Real-time Insights'
                ],
                'docs' => '/api/docs'
            ]);
        });
        
        $this->router->get('/health', function() {
            return Response::success([
                'status' => 'healthy',
                'service' => 'TicketNinja API',
                'timestamp' => time()
            ]);
        });
        
        // API Routes
        $this->registerApiRoutes();
    }
    
    /**
     * Register API routes with versioning
     */
    private function registerApiRoutes()
    {
        $prefix = '/api';
        
        // Authentication routes (public)
        $this->router->post($prefix . '/auth/register', 'AuthController@register');
        $this->router->post($prefix . '/auth/login', 'AuthController@login');
        $this->router->post($prefix . '/auth/forgot-password', 'AuthController@forgotPassword');
        $this->router->post($prefix . '/auth/verify-otp', 'AuthController@verifyOtp');
        $this->router->post($prefix . '/auth/refresh', 'AuthController@refresh');
        $this->router->post($prefix . '/auth/logout', 'AuthController@logout');

        
        // Protected routes (require authentication)
        $authMiddleware = new \App\Middleware\AuthMiddleware();
        
        // User routes
        $this->router->get($prefix . '/user/profile', 'UserController@getProfile', [$authMiddleware]);
        $this->router->put($prefix . '/user/profile', 'UserController@updateProfile', [$authMiddleware]);
        
        // Event routes
        $this->router->get($prefix . '/events', 'EventController@index');
        $this->router->get($prefix . '/events/{id}', 'EventController@show');
        $this->router->post($prefix . '/events', 'EventController@create', [$authMiddleware]);
        $this->router->put($prefix . '/events/{id}', 'EventController@update', [$authMiddleware]);
        $this->router->delete($prefix . '/events/{id}', 'EventController@delete', [$authMiddleware]);
        $this->router->get($prefix . '/events/user/{userId}', 'EventController@getUserEvents', [$authMiddleware]);
        
        // Analytics routes
        $this->router->get($prefix . '/analytics/system-overview', 'AnalyticsController@systemOverview', [$authMiddleware]);
        $this->router->get($prefix . '/analytics/overview', 'AnalyticsController@overview', [$authMiddleware]);
        $this->router->get($prefix . '/analytics/events/{eventId}', 'AnalyticsController@eventAnalytics', [$authMiddleware]);
        $this->router->get($prefix . '/analytics/ai-insights', 'AnalyticsController@aiInsights', [$authMiddleware]);
        
        // Attendee routes
        $this->router->get($prefix . '/attendees', 'AttendeeController@index', [$authMiddleware]);
        $this->router->get($prefix . '/attendees/{id}', 'AttendeeController@show', [$authMiddleware]);
        $this->router->post($prefix . '/attendees', 'AttendeeController@create', [$authMiddleware]);
        $this->router->put($prefix . '/attendees/{id}', 'AttendeeController@update', [$authMiddleware]);
        $this->router->delete($prefix . '/attendees/{id}', 'AttendeeController@delete', [$authMiddleware]);
        $this->router->get($prefix . '/attendees/event/{eventId}', 'AttendeeController@getEventAttendees', [$authMiddleware]);
        $this->router->get($prefix . '/attendees/user/{userId}', 'AttendeeController@getUserTickets', [$authMiddleware]);
        $this->router->post($prefix . '/attendees/bulk-email', 'AttendeeController@sendBulkEmails', [$authMiddleware]);
        
        // Withdrawal routes
        $this->router->get($prefix . '/withdrawals/balance', 'WithdrawalController@getBalance', [$authMiddleware]);
        $this->router->get($prefix . '/withdrawals', 'WithdrawalController@index', [$authMiddleware]);
        $this->router->post($prefix . '/withdrawals', 'WithdrawalController@create', [$authMiddleware]);

        // Ticket Verification routes
        $this->router->post($prefix . '/tickets/verify', 'TicketController@verify', [$authMiddleware]);
        $this->router->get($prefix . '/withdrawals/{id}', 'WithdrawalController@show', [$authMiddleware]);

        $this->router->put($prefix . '/withdrawals/{id}/status', 'WithdrawalController@updateStatus', [$authMiddleware]);
        
        // Payment routes (Paystack & Flutterwave)
        $this->router->get($prefix . '/paystack/verify/{reference}', 'PaystackController@verifyPayment');
        $this->router->get($prefix . '/payment/verify/{transactionId}', 'PaymentController@verifyPayment');
        $this->router->get($prefix . '/payment/banks', 'PaymentController@getBanks');
        $this->router->post($prefix . '/payment/verify-account', 'PaymentController@verifyAccount');
        $this->router->post($prefix . '/payment/process-transfer', 'PaymentController@processTransfer', [$authMiddleware]);
        
        // Settings Routes
        $this->router->get($prefix . '/settings', 'SettingsController@getSettings', [$authMiddleware]);
        $this->router->put($prefix . '/settings', 'SettingsController@updateSettings', [$authMiddleware]);

        // Ticket Verification
        $this->router->post($prefix . '/tickets/verify', 'TicketController@verify', [$authMiddleware]);
    }
    
    /**
     * Run the application
     */
    public function run()
    {
        // Security checks
        $security = new \App\Middleware\SecurityMiddleware();
        $security->sanitize();
        $security->rateLimit();

        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove the base directory from the path (for subdirectory installations)
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/' && strpos($path, $scriptName) === 0) {
            $path = substr($path, strlen($scriptName));
        }
        
        // Normalize slashes (collapse multiple slashes into one)
        $path = preg_replace('#/+#', '/', $path);
        
        // Ensure path starts with /
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        // Remove trailing slash except for root
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        
        $this->router->dispatch($method, $path);
    }
}
