<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Withdrawal;
use App\Middleware\AuthMiddleware;
use App\Utils\Validator;

/**
 * Withdrawal Controller
 * 
 * Handles withdrawal requests
 */
class WithdrawalController
{
    private $withdrawalModel;
    
    public function __construct()
    {
        $this->withdrawalModel = new Withdrawal();
    }
    
    /**
     * Get all withdrawals
     */
    public function index()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $withdrawals = $this->withdrawalModel->getByUser($authUser['user_id']);
        
        Response::success($withdrawals);
    }
    
    /**
     * Get single withdrawal
     */
    public function show($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $withdrawal = $this->withdrawalModel->find($id);
        
        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }
        
        // Check if withdrawal belongs to user
        if ($withdrawal['user_id'] !== $authUser['user_id']) {
            Response::forbidden('Access denied');
        }
        
        Response::success($withdrawal);
    }
    
    /**
     * Create new withdrawal request
     */
    public function create()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator($input, [
            'amount' => 'required|numeric',
            'bank_name' => 'required',
            'account_number' => 'required',
            'account_name' => 'required'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }
        
        // Validate amount
        if ($input['amount'] <= 0) {
            Response::error('Amount must be greater than zero', 400);
        }
        
        try {
            $withdrawalId = $this->withdrawalModel->create([
                'user_id' => $authUser['user_id'],
                'amount' => $input['amount'],
                'bank_name' => $input['bank_name'],
                'account_number' => $input['account_number'],
                'account_name' => $input['account_name'],
                'status' => 'Pending'
            ]);
            
            $withdrawal = $this->withdrawalModel->find($withdrawalId);
            
            Response::success($withdrawal, 'Withdrawal request created successfully', 201);
            
        } catch (\Exception $e) {
            Response::error('Failed to create withdrawal request: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update withdrawal status
     */
    public function updateStatus($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = new Validator($input, [
            'status' => 'required'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }
        
        $allowedStatuses = ['Pending', 'Processing', 'Completed', 'Failed', 'Cancelled'];
        if (!in_array($input['status'], $allowedStatuses)) {
            Response::error('Invalid status', 400);
        }
        
        try {
            $this->withdrawalModel->updateStatus($id, $input['status']);
            $withdrawal = $this->withdrawalModel->find($id);
            
            Response::success($withdrawal, 'Withdrawal status updated successfully');
            
        } catch (\Exception $e) {
            Response::error('Failed to update withdrawal status: ' . $e->getMessage(), 500);
        }
    }
}
?>
