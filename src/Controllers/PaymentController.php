<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Withdrawal;
use App\Middleware\AuthMiddleware;

/**
 * Payment Controller
 * 
 * Handles Flutterwave payment processing
 */
class PaymentController
{
    private $flutterwaveSecretKey;
    private $flutterwavePublicKey;
    private $withdrawalModel;
    
    public function __construct()
    {
        $this->flutterwaveSecretKey = $_ENV['FLUTTERWAVE_SECRET_KEY'] ?? '';
        $this->flutterwavePublicKey = $_ENV['FLUTTERWAVE_PUBLIC_KEY'] ?? '';
        $this->withdrawalModel = new Withdrawal();
    }
    
    /**
     * Verify payment transaction
     */
    public function verifyPayment($transactionId)
    {
        try {
            $url = "https://api.flutterwave.com/v3/transactions/{$transactionId}/verify";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->flutterwaveSecretKey}",
                "Content-Type: application/json"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                Response::error('Payment verification failed', 400);
            }
            
            $data = json_decode($response, true);
            
            if ($data['status'] === 'success' && $data['data']['status'] === 'successful') {
                Response::success($data['data'], 'Payment verified successfully');
            } else {
                Response::error('Payment verification failed', 400);
            }
            
        } catch (\Exception $e) {
            Response::error('Payment verification error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get list of banks
     */
    public function getBanks()
    {
        try {
            $url = "https://api.flutterwave.com/v3/banks/NG";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->flutterwaveSecretKey}",
                "Content-Type: application/json"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                Response::error('Failed to fetch banks list', 400);
            }
            
            $data = json_decode($response, true);
            
            if ($data['status'] === 'success') {
                Response::success($data['data'], 'Banks retrieved successfully');
            } else {
                Response::error('Failed to fetch banks list', 400);
            }
            
        } catch (\Exception $e) {
            Response::error('Error fetching banks: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Verify bank account
     */
    public function verifyAccount()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['account_number']) || !isset($input['account_bank'])) {
            Response::error('Account number and bank code are required', 400);
        }
        
        try {
            $url = "https://api.flutterwave.com/v3/accounts/resolve";
            
            $payload = json_encode([
                'account_number' => $input['account_number'],
                'account_bank' => $input['account_bank']
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->flutterwaveSecretKey}",
                "Content-Type: application/json"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                Response::error('Account verification failed', 400);
            }
            
            $data = json_decode($response, true);
            
            if ($data['status'] === 'success') {
                Response::success($data['data'], 'Account verified successfully');
            } else {
                Response::error($data['message'] ?? 'Account verification failed', 400);
            }
            
        } catch (\Exception $e) {
            Response::error('Account verification error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Process withdrawal/payout
     */
    public function processTransfer()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['withdrawal_id'])) {
            Response::error('Withdrawal ID is required', 400);
        }
        
        // Get withdrawal details
        $withdrawal = $this->withdrawalModel->find($input['withdrawal_id']);
        
        if (!$withdrawal) {
            Response::notFound('Withdrawal not found');
        }
        
        if ($withdrawal['user_id'] !== $authUser['user_id']) {
            Response::forbidden('Access denied');
        }
        
        if ($withdrawal['status'] !== 'Pending') {
            Response::error('Withdrawal already processed', 400);
        }
        
        try {
            $url = "https://api.flutterwave.com/v3/transfers";
            
            $reference = 'TN-WD-' . time() . '-' . rand(1000, 9999);
            
            $payload = json_encode([
                'account_bank' => $withdrawal['bank_name'],
                'account_number' => $withdrawal['account_number'],
                'amount' => $withdrawal['amount'],
                'narration' => 'TicketNinja Withdrawal',
                'currency' => 'NGN',
                'reference' => $reference,
                'beneficiary_name' => $withdrawal['account_name'],
                'debit_currency' => 'NGN'
            ]);
            
            $ch = curl_init();
     curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->flutterwaveSecretKey}",
                "Content-Type: application/json"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLOPT_HTTP_CODE);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if ($data['status'] === 'success') {
                // Update withdrawal status
                $this->withdrawalModel->updateStatus($withdrawal['id'], 'Processing');
                
                Response::success([
                    'reference' => $reference,
                    'transfer_data' => $data['data']
                ], 'Transfer initiated successfully');
            } else {
                $this->withdrawalModel->updateStatus($withdrawal['id'], 'Failed');
                Response::error($data['message'] ?? 'Transfer failed', 400);
            }
            
        } catch (\Exception $e) {
            $this->withdrawalModel->updateStatus($withdrawal['id'], 'Failed');
            Response::error('Transfer error: ' . $e->getMessage(), 500);
        }
    }
}
?>
