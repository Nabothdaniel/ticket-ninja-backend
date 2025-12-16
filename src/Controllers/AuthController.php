<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\User;
use App\Utils\Validator;
use Firebase\JWT\JWT;
use App\Services\NotificationService;

/**
 * Authentication Controller
 * 
 * Handles user registration, login, and password recovery
 */
class AuthController
{
    private $userModel;
    private $notifier;

    public function __construct()
    {
        $this->userModel = new User();
        $this->notifier = new NotificationService(); // NotificationService instance
    }
    
    /**
     * Register a new user
     */
    public function register()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator($input, [
            'full_name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6',
            'phone' => 'required',
            'industry' => 'required',
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }
        
        // Check if user already exists
        $existingUser = $this->userModel->findByEmail($input['email']);
        if ($existingUser) {
            Response::error('User with this email already exists', 409);
        }
        
        try {
            // Create user
            $userId = $this->userModel->createUser([
                'full_name' => $input['full_name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'phone' => $input['phone'],
                'industry' => $input['industry'],
            ]);
            
            // Get created user
            $user = $this->userModel->find($userId);
            unset($user['hashed_password']);

            // Generate OTP
            $otp = rand(100000, 999999); // 6-digit OTP
            
            // Store OTP in database (optional: with expiration, e.g., 10 minutes)
            $this->userModel->update($userId, ['otp' => $otp, 'otp_expires' => date('Y-m-d H:i:s', time() + 600)]);

            // Send OTP email
            $this->notifier->sendEmail(
                $user['email'],
                "Your OTP for TicketNinja",
                "<p>Hello {$user['full_name']},</p>
                 <p>Your One-Time Password (OTP) is: <strong>{$otp}</strong></p>
                 <p>This OTP is valid for 10 minutes.</p>"
            );
            
            // Generate JWT token
            $token = $this->generateToken($user);
            
            Response::success([
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'phone' => $user['phone'],
                'token' => $token,
                'otp_sent' => true
            ], 'Registration successful. OTP sent to email.', 201);
            
        } catch (\Exception $e) {
            Response::error('Registration failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Login user
     */
    public function login()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator($input, [
            'email' => 'required|email',
            'password' => 'required'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }
        
        // Verify credentials
        if (!$this->userModel->verifyPassword($input['email'], $input['password'])) {
            Response::error('Invalid email or password', 401);
        }
        
        // Get user
        $user = $this->userModel->findByEmail($input['email']);
        unset($user['hashed_password']);
        
        // Generate JWT token
        $token = $this->generateToken($user);
        
        Response::success([
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'phone' => $user['phone'],
            'company_name' => $user['company_name'],
            'token' => $token
        ], 'Login successful');
    }
    
    /**
     * Forgot password
     */
    public function forgotPassword()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator($input, [
            'email' => 'required|email'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }
        
        $user = $this->userModel->findByEmail($input['email']);
        
        if (!$user) {
            // For security, don't reveal if email exists
            Response::success(null, 'If the email exists, a password reset link has been sent');
        }
        
        // TODO: Implement password reset logic (email sending, token generation)
        Response::success(null, 'Password reset functionality will be implemented');
    }
    
    /**
     * Generate JWT token
     */
    private function generateToken($user)
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production';
        $issuedAt = time();
        $expirationTime = $issuedAt + ($_ENV['JWT_EXPIRATION'] ?? 1800); // 30 minutes

        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name']
        ];
        
        return JWT::encode($payload, $secret, $_ENV['JWT_ALGORITHM'] ?? 'HS256');
    }

    /**
 * Verify OTP
 */
public function verifyOtp()
{
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $validator = new Validator($input, [
        'email' => 'required|email',
        'otp' => 'required|digits:6'
    ]);

    if (!$validator->validate()) {
        Response::validationError($validator->errors());
    }

    $user = $this->userModel->findByEmail($input['email']);

    if (!$user) {
        Response::error('User not found', 404);
    }

    // Check OTP and expiration
    if ($user['otp'] !== $input['otp']) {
        Response::error('Invalid OTP', 400);
    }

    if (strtotime($user['otp_expires']) < time()) {
        Response::error('OTP expired', 400);
    }

    // OTP valid → clear OTP and activate user (optional)
    $this->userModel->update($user['id'], ['otp' => null, 'otp_expires' => null, 'is_verified' => 1]);

    // Generate JWT token
    $token = $this->generateToken($user);

    Response::success([
        'id' => $user['id'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'token' => $token
    ], 'OTP verified successfully');
}

}
?>
