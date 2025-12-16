<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\User;
use App\Middleware\AuthMiddleware;

/**
 * User Controller
 * 
 * Handles user profile operations
 */
class UserController
{
    private $userModel;
    
    public function __construct()
    {
        $this->userModel = new User();
    }
    
    /**
     * Get user profile
     */
    public function getProfile()
    {
        $authUser = AuthMiddleware::getAuthUser();
        
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $user = $this->userModel->find($authUser['user_id']);
        
        if (!$user) {
            Response::notFound('User not found');
        }
        
        unset($user['hashed_password']);
        
        // Get user stats
        $stats = $this->userModel->getUserStats($user['id']);
        $user['stats'] = $stats;
        
        Response::success($user);
    }
    
    /**
     * Update user profile
     */
    public function updateProfile()
    {
        $authUser = AuthMiddleware::getAuthUser();
        
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Fields that can be updated
        $allowedFields = ['full_name', 'phone', 'industry', 'preferences'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                if ($field === 'preferences' && is_array($input[$field])) {
                    $updateData[$field] = json_encode($input[$field]);
                } else {
                    $updateData[$field] = $input[$field];
                }
            }
        }
        
        if (empty($updateData)) {
            Response::error('No valid fields to update');
        }
        
        try {
            $this->userModel->update($authUser['user_id'], $updateData);
            
            $updatedUser = $this->userModel->find($authUser['user_id']);
            unset($updatedUser['hashed_password']);
            
            Response::success($updatedUser, 'Profile updated successfully');
            
        } catch (\Exception $e) {
            Response::error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }
}
?>
