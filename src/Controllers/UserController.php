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
        
        unset($user['password']);
        
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
        $allowedFields = ['full_name', 'phone', 'interests', 'ticket_delivery'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateData[$field] = $input[$field];
            }
        }
        
        if (empty($updateData)) {
            Response::error('No valid fields to update');
        }
        
        try {
            $this->userModel->update($authUser['user_id'], $updateData);
            
            $updatedUser = $this->userModel->find($authUser['user_id']);
            unset($updatedUser['password']);
            
            Response::success($updatedUser, 'Profile updated successfully');
            
        } catch (\Exception $e) {
            Response::error('Failed to update profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Admin: list users
     */
    public function listUsers()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            Response::forbidden('Admin access required');
        }

        $users = $this->userModel->all(200, 0);
        foreach ($users as &$user) {
            unset($user['password']);
        }

        Response::success($users);
    }

    /**
     * Admin: list assignable agents
     */
    public function listAgents()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            Response::forbidden('Admin access required');
        }

        $sql = "SELECT id, full_name, email, role, created_at
                FROM users
                WHERE role IN ('agent','admin')
                ORDER BY created_at DESC";
        $agents = $this->userModel->query($sql);
        Response::success($agents);
    }

    /**
     * Admin: update a user's role
     */
    public function updateUserRole($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            Response::forbidden('Admin access required');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $role = strtolower((string)($input['role'] ?? ''));

        $allowedRoles = ['admin', 'agent', 'organizer', 'attendee'];
        if (!in_array($role, $allowedRoles, true)) {
            Response::error('Invalid role', 422, null, 'invalid_role');
        }

        $targetUser = $this->userModel->find($id);
        if (!$targetUser) {
            Response::notFound('User not found');
        }

        $this->userModel->update($id, ['role' => $role]);
        $updated = $this->userModel->find($id);
        unset($updated['password']);

        Response::success($updated, 'User role updated');
    }
}
