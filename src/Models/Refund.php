<?php

namespace App\Models;

/**
 * Refund Model
 */
class Refund extends BaseModel
{
    protected $table = 'refunds';

    /**
     * Find active refund for a payment
     */
    public function findActiveByPaymentId($paymentId)
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE payment_id = :paymentId
                  AND status IN ('pending', 'approved', 'processing', 'completed')
                ORDER BY created_at DESC
                LIMIT 1";
        $result = $this->query($sql, ['paymentId' => $paymentId]);
        return $result[0] ?? null;
    }

    /**
     * Get refund with joined payment/event/user details
     */
    public function getWithDetails($refundId)
    {
        $sql = "SELECT r.*,
                       p.reference as payment_reference,
                       p.amount as payment_amount,
                       p.currency as payment_currency,
                       p.user_id as payment_user_id,
                       e.organizer_id,
                       e.title as event_title,
                       u.full_name as requested_by_name,
                       u.email as requested_by_email
                FROM refunds r
                JOIN payments p ON p.id = r.payment_id
                LEFT JOIN events e ON e.id = p.event_id
                LEFT JOIN users u ON u.id = r.requested_by
                WHERE r.id = :refundId
                LIMIT 1";
        $result = $this->query($sql, ['refundId' => $refundId]);
        return $result[0] ?? null;
    }

    /**
     * List refunds with role-aware filters
     */
    public function listForUser($authUser)
    {
        if (($authUser['role'] ?? '') === 'admin') {
            $sql = "SELECT r.*, p.reference as payment_reference, e.title as event_title
                    FROM refunds r
                    JOIN payments p ON p.id = r.payment_id
                    LEFT JOIN events e ON e.id = p.event_id
                    ORDER BY r.created_at DESC";
            return $this->query($sql);
        }

        if (($authUser['role'] ?? '') === 'organizer') {
            $sql = "SELECT r.*, p.reference as payment_reference, e.title as event_title
                    FROM refunds r
                    JOIN payments p ON p.id = r.payment_id
                    JOIN events e ON e.id = p.event_id
                    WHERE e.organizer_id = :userId OR r.requested_by = :userId
                    ORDER BY r.created_at DESC";
            return $this->query($sql, ['userId' => $authUser['user_id']]);
        }

        $sql = "SELECT r.*, p.reference as payment_reference, e.title as event_title
                FROM refunds r
                JOIN payments p ON p.id = r.payment_id
                LEFT JOIN events e ON e.id = p.event_id
                WHERE p.user_id = :userId OR r.requested_by = :userId
                ORDER BY r.created_at DESC";
        return $this->query($sql, ['userId' => $authUser['user_id']]);
    }
}

