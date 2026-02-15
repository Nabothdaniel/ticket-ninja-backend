<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Payment;
use App\Models\Event;
use App\Models\Attendee;
use App\Middleware\AuthMiddleware;
use App\Utils\Validator;

/**
 * Paystack Controller
 * 
 * Handles Paystack payment verification and ticket issuance
 */
class PaystackController
{
    private $paystackSecretKey;
    private $paymentModel;
    private $eventModel;
    private $attendeeModel;

    public function __construct()
    {
        $this->paystackSecretKey = $_ENV['PAYSTACK_SECRET_KEY'] ?? '';
        $this->paymentModel = new Payment();
        $this->eventModel = new Event();
        $this->attendeeModel = new Attendee();
    }

    /**
     * Verify Paystack payment
     */
    public function verifyPayment($reference)
    {
        if (empty($reference)) {
            Response::error('Transaction reference is required', 400);
        }

        try {
            // 1. Idempotency Check: See if we already processed this reference
            $existingPayment = $this->paymentModel->findByReference($reference);
            if ($existingPayment && $existingPayment['status'] === 'success') {
                // If already success, return the ticket info tied to this series_number
                $attendee = $this->attendeeModel->whereFirst('ticket_code', $existingPayment['series_number']);
                Response::success([
                    'payment' => $existingPayment,
                    'attendee' => $attendee,
                    'is_duplicate' => true
                ], 'Payment already verified');
                return;
            }

            // 2. Verify with Paystack API
            $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->paystackSecretKey}",
                "Cache-Control: no-cache"
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = json_decode($response, true);

            if (!$result || !$result['status']) {
                Response::error($result['message'] ?? 'Unable to verify transaction', 400);
            }

            $data = $result['data'];

            if ($data['status'] !== 'success') {
                Response::error('Transaction failed on Paystack', 400);
            }

            // 3. Extract metadata
            $metadata = $data['metadata'] ?? [];
            $eventId = $metadata['event_id'] ?? null;
            $ticketType = $metadata['ticket_type'] ?? 'Standard';
            $qty = $metadata['quantity'] ?? 1;
            $userId = $metadata['user_id'] ?? null;

            if (!$eventId) {
                Response::error('Event ID missing from transaction metadata', 400);
            }

            // 4. Generate Series Number for the ticket
            $seriesNumber = "TN-" . date('Y') . "-" . strtoupper(substr(md5($reference), 0, 8));

            // 5. Atomic Update: Decrement available tickets
            $updated = $this->eventModel->updateAvailableTickets($eventId, $qty);
            if (!$updated) {
                Response::error('Unable to finalize purchase: Tickets might have sold out', 409);
            }

            // 6. Record Payment
            if (!$existingPayment) {
                $this->paymentModel->create([
                    'reference' => $reference,
                    'event_id' => $eventId,
                    'user_id' => $userId,
                    'email' => $data['customer']['email'],
                    'amount' => $data['amount'] / 100,
                    'currency' => $data['currency'],
                    'status' => 'success',
                    'series_number' => $seriesNumber,
                    'payload' => json_encode($result)
                ]);
            } else {
                $this->paymentModel->markAsSuccess($existingPayment['id'], $seriesNumber, $result);
            }

            // 7. Create Attendee Record
            $attendeeData = [
                'event_id' => $eventId,
                'user_id' => $userId,
                'name' => $metadata['full_name'] ?? ($data['customer']['first_name'] . ' ' . $data['customer']['last_name']),
                'email' => $data['customer']['email'],
                'phone' => $metadata['phone'] ?? null,
                'ticket_type' => $ticketType,
                'ticket_code' => $seriesNumber,
                'status' => 'Confirmed'
            ];

            $attendeeId = $this->attendeeModel->create($attendeeData);
            $attendee = $this->attendeeModel->find($attendeeId);

            Response::success([
                'reference' => $reference,
                'series_number' => $seriesNumber,
                'attendee' => $attendee
            ], 'Payment verified successfully');

        } catch (\Exception $e) {
            Response::error('Verification Error: ' . $e->getMessage(), 500);
        }
    }
}
