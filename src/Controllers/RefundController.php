<?php

namespace App\Controllers;

use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Refund;
use App\Utils\Validator;

/**
 * Refund Controller
 */
class RefundController
{
    private $refundModel;
    private $paymentModel;
    private $eventModel;

    public function __construct()
    {
        $this->refundModel = new Refund();
        $this->paymentModel = new Payment();
        $this->eventModel = new Event();
    }

    /**
     * Create a refund request
     */
    public function create()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $validator = new Validator($input, [
            'payment_id' => 'required',
            'reason' => 'required',
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }

        $payment = $this->paymentModel->find($input['payment_id']);
        if (!$payment) {
            Response::notFound('Payment not found');
        }
        if (($payment['status'] ?? '') !== 'success') {
            Response::error('Only successful payments can be refunded', 409, null, 'refund_not_eligible');
        }

        $event = $this->eventModel->find($payment['event_id']);
        $isAdmin = ($authUser['role'] ?? '') === 'admin';
        $isOwner = isset($payment['user_id']) && $payment['user_id'] === $authUser['user_id'];
        $isOrganizer = $event && (($event['organizer_id'] ?? '') === $authUser['user_id']);

        if (!$isAdmin && !$isOwner && !$isOrganizer) {
            Response::forbidden('You do not have permission to request this refund');
        }

        $activeRefund = $this->refundModel->findActiveByPaymentId($payment['id']);
        if ($activeRefund) {
            Response::error('An active refund already exists for this payment', 409, null, 'refund_already_exists');
        }

        $amount = isset($input['amount']) ? (float)$input['amount'] : (float)$payment['amount'];
        if ($amount <= 0 || $amount > (float)$payment['amount']) {
            Response::error('Refund amount must be greater than zero and not exceed payment amount', 422, null, 'invalid_refund_amount');
        }

        $refundId = $this->refundModel->create([
            'payment_id' => $payment['id'],
            'requested_by' => $authUser['user_id'],
            'amount' => $amount,
            'reason' => trim((string)$input['reason']),
            'status' => 'pending',
            'provider' => $input['provider'] ?? 'paystack',
            'provider_reference' => null,
            'response_payload' => null
        ]);

        $refund = $this->refundModel->getWithDetails($refundId);
        Response::success($refund, 'Refund request created', 201);
    }

    /**
     * List refunds
     */
    public function index()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }

        $refunds = $this->refundModel->listForUser($authUser);
        Response::success($refunds);
    }

    /**
     * Get single refund
     */
    public function show($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }

        $refund = $this->refundModel->getWithDetails($id);
        if (!$refund) {
            Response::notFound('Refund not found');
        }

        $isAdmin = ($authUser['role'] ?? '') === 'admin';
        $isRequester = ($refund['requested_by'] ?? '') === $authUser['user_id'];
        $isPaymentOwner = ($refund['payment_user_id'] ?? '') === $authUser['user_id'];
        $isOrganizer = ($refund['organizer_id'] ?? '') === $authUser['user_id'];

        if (!$isAdmin && !$isRequester && !$isPaymentOwner && !$isOrganizer) {
            Response::forbidden('Access denied');
        }

        Response::success($refund);
    }

    /**
     * Approve or reject a refund request (admin)
     */
    public function updateStatus($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            Response::forbidden('Admin access required');
        }

        $refund = $this->refundModel->find($id);
        if (!$refund) {
            Response::notFound('Refund not found');
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $validator = new Validator($input, ['status' => 'required']);
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }

        $allowed = ['approved', 'rejected', 'processing', 'completed', 'failed'];
        $status = strtolower((string)$input['status']);
        if (!in_array($status, $allowed, true)) {
            Response::error('Invalid refund status', 422, null, 'invalid_refund_status');
        }

        $update = [
            'status' => $status,
            'processed_by' => $authUser['user_id']
        ];

        if (isset($input['provider_reference'])) {
            $update['provider_reference'] = $input['provider_reference'];
        }
        if (isset($input['response_payload'])) {
            $update['response_payload'] = json_encode($input['response_payload']);
        }

        $this->refundModel->update($id, $update);
        $updated = $this->refundModel->getWithDetails($id);

        Response::success($updated, 'Refund status updated');
    }
}

