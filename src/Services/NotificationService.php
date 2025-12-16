<?php

namespace App\Services;

use GuzzleHttp\Client;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;
use Mailtrap\Helper\ResponseHelper;

class NotificationService
{
    private $httpClient;
    private $mailtrapApiKey;

    public function __construct()
    {
        $this->httpClient = new Client();
        $this->mailtrapApiKey = $_ENV['MAILTRAP_API_KEY'] ?? null;
    }

    /**
     * Send email via Mailtrap API
     */
    public function sendEmail($to, $subject, $body, $isHtml = true)
    {
        if (!$this->mailtrapApiKey) {
            error_log("Mailtrap API key not configured.");
            return false;
        }

        try {
            $mailtrap = MailtrapClient::initSendingEmails(
                apiKey: $this->mailtrapApiKey
            );

            $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@ticketninja.com';
            $fromName  = $_ENV['SMTP_FROM_NAME'] ?? 'TicketNinja';

            $email = (new MailtrapEmail())
                ->from(new Address($fromEmail, $fromName))
                ->to(new Address($to))
                ->subject($subject)
                ->category('Ticketing')
            ;

            if ($isHtml) {
                $email->html($body);
            } else {
                $email->text($body);
            }

            $response = $mailtrap->send($email);
            $result = ResponseHelper::toArray($response);

            return $result['status'] === 'ok';
        } catch (\Exception $e) {
            error_log("Mailtrap API email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp message (unchanged)
     */
    public function sendWhatsApp($to, $message)
    {
        $apiUrl = $_ENV['WHATSAPP_API_URL'] ?? null;
        $apiKey = $_ENV['WHATSAPP_API_KEY'] ?? null;

        if (!$apiUrl || !$apiKey) {
            error_log("WhatsApp API not configured.");
            return false;
        }

        try {
            $response = $this->httpClient->post($apiUrl, [
                'headers' => [
                    'Authorization' => "Bearer $apiKey",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $to,
                    'message' => $message,
                ]
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            error_log("WhatsApp message could not be sent. Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send ticket notification
     */
    public function sendTicket($user, $event, $ticketCode, $qrCodeUrl = null)
    {
        $preferences = json_decode($user['preferences'] ?? '{}', true);
        $notifyEmail = $preferences['notify_email'] ?? true;
        $notifyWhatsapp = $preferences['notify_whatsapp'] ?? false;
        $whatsappNumber = $preferences['whatsapp_number'] ?? null;

        $message = "Hello {$user['name']},\n\nHere is your ticket for {$event['title']}.\nTicket Code: {$ticketCode}\n\nEnjoy the event!";
        
        if ($qrCodeUrl) {
            $message .= "\nQR Code: $qrCodeUrl";
        }

        if ($notifyEmail) {
            $this->sendEmail($user['email'], "Your Ticket for {$event['title']}", nl2br($message));
        }

        if ($notifyWhatsapp && $whatsappNumber) {
            $this->sendWhatsApp($whatsappNumber, $message);
        }
    }
}
