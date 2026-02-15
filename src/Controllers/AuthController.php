<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\User;
use App\Models\RefreshToken;
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
    private $refreshTokenModel;
    private $notifier;

    public function __construct()
    {
        $this->userModel = new User();
        $this->refreshTokenModel = new RefreshToken();
        $this->notifier = new NotificationService();
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
            'role' => 'required|in:organizer,attendee',
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
                'role' => $input['role'],
                'phone' => $input['phone'] ?? null,
                'company_name' => $input['company_name'] ?? null,
                'bio' => $input['bio'] ?? null,
            ]);
            
            // Get created user
            $user = $this->userModel->find($userId);
            unset($user['password']);

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
            
            // Generate tokens
            $tokens = $this->issueTokens($user);
            
            Response::success([
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'phone' => $user['phone'],
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
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
        unset($user['password']);
        
        // Generate tokens
        $tokens = $this->issueTokens($user);
        
        Response::success([
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'phone' => $user['phone'],
            'company_name' => $user['company_name'],
            'role' => $user['role'],
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token']
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
     * Issue dual tokens (Access + Refresh)
     */
    private function issueTokens($user)
    {
        $accessToken = $this->generateAccessToken($user);
        $refreshToken = bin2hex(random_bytes(40));
        $expiresAt = date('Y-m-d H:i:s', time() + ($_ENV['JWT_REFRESH_EXPIRATION'] ?? 604800)); // 7 days

        $this->refreshTokenModel->createToken($user['id'], $refreshToken, $expiresAt);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken
        ];
    }

    /**
     * Generate JWT Access Token
     */
    private function generateAccessToken($user)
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-this-in-production';
        $issuedAt = time();
        $expirationTime = $issuedAt + ($_ENV['JWT_EXPIRATION'] ?? 3600); // Default 1 hour
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user_id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'role' => $user['role'] ?? 'attendee'
        ];
        
        return JWT::encode($payload, $secret, $_ENV['JWT_ALGORITHM'] ?? 'HS256');
    }

    /**
     * Refresh access token
     */
    public function refresh()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? null;

        if (!$refreshToken) {
            Response::error('Refresh token required', 400);
        }

        $tokenData = $this->refreshTokenModel->findByToken($refreshToken);

        if (!$tokenData || strtotime($tokenData['expires_at']) < time()) {
            Response::error('Invalid or expired refresh token', 401);
        }

        $user = $this->userModel->find($tokenData['user_id']);
        if (!$user) {
            Response::error('User not found', 404);
        }

        $newAccessToken = $this->generateAccessToken($user);
        
        Response::success([
            'access_token' => $newAccessToken
        ], 'Token refreshed');
    }

    /**
     * Logout (Revoke refresh token)
     */
    public function logout()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $refreshToken = $input['refresh_token'] ?? null;

        if ($refreshToken) {
            $this->refreshTokenModel->revokeToken($refreshToken);
        }

        Response::success(null, 'Logged out successfully');
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

    // Generate tokens
    $tokens = $this->issueTokens($user);

    Response::success([
        'id' => $user['id'],
        'email' => $user['email'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
        'access_token' => $tokens['access_token'],
        'refresh_token' => $tokens['refresh_token']
    ], 'OTP verified successfully');
}

}
