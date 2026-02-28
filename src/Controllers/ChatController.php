<?php

namespace App\Controllers;

use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Utils\Validator;
use App\Services\Logger;

/**
 * Chat Controller
 */
class ChatController
{
    private $conversationModel;
    private $messageModel;
    private $userModel;

    public function __construct()
    {
        $this->conversationModel = new ChatConversation();
        $this->messageModel = new ChatMessage();
        $this->userModel = new User();
    }

    /**
     * Create conversation (guest or auth user)
     */
    public function createConversation()
    {
        $authUser = AuthMiddleware::getAuthUser();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $guestToken = $input['guest_token'] ?? null;
        $subject = trim((string)($input['subject'] ?? ''));

        if (!$authUser && !$guestToken) {
            $guestToken = bin2hex(random_bytes(16));
        }

        $conversationId = $this->conversationModel->create([
            'user_id' => $authUser['user_id'] ?? null,
            'guest_token' => $guestToken,
            'assigned_agent_id' => null,
            'status' => 'open',
            'subject' => $subject ?: null,
            'last_message_at' => date('Y-m-d H:i:s'),
        ]);

        $conversation = $this->conversationModel->getWithMeta($conversationId);
        Logger::info('Chat conversation created', [
            'conversation_id' => $conversationId,
            'user_id' => $authUser['user_id'] ?? null,
            'guest' => $authUser ? false : true
        ]);
        Response::success($conversation, 'Conversation created', 201);
    }

    /**
     * List conversations for admin/agent
     */
    public function listConversations()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }

        $role = $authUser['role'] ?? '';
        if (!in_array($role, ['admin', 'agent'], true)) {
            Response::forbidden('Staff access required');
        }

        $status = $_GET['status'] ?? null;
        $conversations = $this->conversationModel->listForStaff($status);

        if ($role === 'agent') {
            $conversations = array_values(array_filter($conversations, function ($c) use ($authUser) {
                return empty($c['assigned_agent_id']) || $c['assigned_agent_id'] === $authUser['user_id'];
            }));
        }

        Response::success($conversations);
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages($conversationId)
    {
        $conversation = $this->conversationModel->find($conversationId);
        if (!$conversation) {
            Response::notFound('Conversation not found');
        }

        $this->assertConversationAccess($conversation);

        $messages = $this->messageModel->getByConversation($conversationId);
        Response::success($messages);
    }

    /**
     * Add user/agent/admin message
     */
    public function addMessage($conversationId)
    {
        $conversation = $this->conversationModel->find($conversationId);
        if (!$conversation) {
            Response::notFound('Conversation not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($input, ['message' => 'required']);
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }

        $authUser = AuthMiddleware::getAuthUser();
        $guestToken = $input['guest_token'] ?? ($_GET['guest_token'] ?? null);

        $senderType = 'user';
        $senderId = null;

        if ($authUser) {
            $role = $authUser['role'] ?? 'attendee';
            $senderType = $role === 'admin' ? 'admin' : ($role === 'agent' ? 'agent' : 'user');
            $senderId = $authUser['user_id'];
        } else {
            if (!$guestToken || ($conversation['guest_token'] ?? '') !== $guestToken) {
                Response::forbidden('Invalid guest token for this conversation');
            }
        }

        $this->assertConversationAccess($conversation, $guestToken);

        $messageId = $this->messageModel->create([
            'conversation_id' => $conversationId,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => trim((string)$input['message']),
            'metadata' => null
        ]);

        $this->conversationModel->update($conversationId, [
            'last_message_at' => date('Y-m-d H:i:s'),
            'status' => ($conversation['status'] === 'closed') ? 'open' : $conversation['status']
        ]);

        $message = $this->messageModel->find($messageId);
        Logger::info('Chat message sent', [
            'conversation_id' => $conversationId,
            'sender_type' => $senderType,
            'sender_id' => $senderId
        ]);
        Response::success($message, 'Message sent', 201);
    }

    /**
     * Generate bot response for a conversation
     */
    public function botReply($conversationId)
    {
        $conversation = $this->conversationModel->find($conversationId);
        if (!$conversation) {
            Response::notFound('Conversation not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $prompt = trim((string)($input['prompt'] ?? ''));

        if ($prompt === '') {
            $messages = $this->messageModel->getByConversation($conversationId);
            $last = end($messages);
            $prompt = $last['message'] ?? '';
        }

        $guestToken = $input['guest_token'] ?? ($_GET['guest_token'] ?? null);
        $this->assertConversationAccess($conversation, $guestToken);

        $reply = $this->generateBotReply($prompt);

        $messageId = $this->messageModel->create([
            'conversation_id' => $conversationId,
            'sender_type' => 'bot',
            'sender_id' => null,
            'message' => $reply,
            'metadata' => null
        ]);

        $this->conversationModel->update($conversationId, [
            'last_message_at' => date('Y-m-d H:i:s')
        ]);

        $message = $this->messageModel->find($messageId);
        Logger::info('Bot reply generated', [
            'conversation_id' => $conversationId
        ]);
        Response::success($message, 'Bot response generated', 201);
    }

    /**
     * Assign conversation to agent (admin)
     */
    public function assignConversation($conversationId)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        if (($authUser['role'] ?? '') !== 'admin') {
            Response::forbidden('Admin access required');
        }

        $conversation = $this->conversationModel->find($conversationId);
        if (!$conversation) {
            Response::notFound('Conversation not found');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $validator = new Validator($input, ['agent_id' => 'required']);
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }

        $agent = $this->userModel->find($input['agent_id']);
        if (!$agent || !in_array($agent['role'] ?? '', ['agent', 'admin'], true)) {
            Response::error('Selected user is not an assignable agent', 422, null, 'invalid_agent');
        }

        $this->conversationModel->update($conversationId, [
            'assigned_agent_id' => $agent['id'],
            'status' => 'assigned'
        ]);

        $updated = $this->conversationModel->getWithMeta($conversationId);
        Logger::info('Conversation assigned', [
            'conversation_id' => $conversationId,
            'agent_id' => $agent['id'],
            'assigned_by' => $authUser['user_id']
        ]);
        Response::success($updated, 'Conversation assigned');
    }

    /**
     * Update conversation status (staff)
     */
    public function updateStatus($conversationId)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        if (!in_array($authUser['role'] ?? '', ['admin', 'agent'], true)) {
            Response::forbidden('Staff access required');
        }

        $conversation = $this->conversationModel->find($conversationId);
        if (!$conversation) {
            Response::notFound('Conversation not found');
        }

        if (($authUser['role'] ?? '') === 'agent'
            && !empty($conversation['assigned_agent_id'])
            && $conversation['assigned_agent_id'] !== $authUser['user_id']) {
            Response::forbidden('Conversation assigned to another agent');
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $status = strtolower((string)($input['status'] ?? ''));
        if (!in_array($status, ['open', 'assigned', 'resolved', 'closed'], true)) {
            Response::error('Invalid conversation status', 422, null, 'invalid_status');
        }

        $this->conversationModel->update($conversationId, ['status' => $status]);
        $updated = $this->conversationModel->getWithMeta($conversationId);
        Logger::info('Conversation status updated', [
            'conversation_id' => $conversationId,
            'status' => $status,
            'updated_by' => $authUser['user_id']
        ]);
        Response::success($updated, 'Conversation status updated');
    }

    private function assertConversationAccess($conversation, $guestToken = null)
    {
        $authUser = AuthMiddleware::getAuthUser();

        if ($authUser) {
            $role = $authUser['role'] ?? '';
            if (in_array($role, ['admin', 'agent'], true)) {
                if ($role === 'agent'
                    && !empty($conversation['assigned_agent_id'])
                    && $conversation['assigned_agent_id'] !== $authUser['user_id']) {
                    Response::forbidden('Conversation assigned to another agent');
                }
                return;
            }

            if (!empty($conversation['user_id']) && $conversation['user_id'] === $authUser['user_id']) {
                return;
            }
        }

        if ($guestToken && !empty($conversation['guest_token']) && $conversation['guest_token'] === $guestToken) {
            return;
        }

        Response::forbidden('Access denied to this conversation');
    }

    private function generateBotReply($prompt)
    {
        $promptLower = strtolower($prompt);

        // Free fallback rules for testing environments.
        if (strpos($promptLower, 'refund') !== false) {
            return 'You can request a refund from your dashboard order history. If you share your payment reference, a human agent can assist immediately.';
        }
        if (strpos($promptLower, 'ticket') !== false) {
            return 'For ticket issues, please share your event name and purchase email. I can route this conversation to an agent for faster help.';
        }
        if (strpos($promptLower, 'payment') !== false) {
            return 'Payment verification usually completes in seconds. If it has been more than 5 minutes, share your transaction reference and we will investigate.';
        }

        $apiKey = $_ENV['MISTRAL_API_KEY'] ?? '';
        if (!$apiKey) {
            return 'Thanks for your message. I can answer quick questions, and if needed I will escalate you to a support agent.';
        }

        try {
            $payload = json_encode([
                'model' => $_ENV['MISTRAL_MODEL'] ?? 'mistral-small-latest',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a concise support assistant for a ticketing platform. Answer briefly and clearly.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.2
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.mistral.ai/v1/chat/completions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 15
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300 && $response) {
                $data = json_decode($response, true);
                $reply = $data['choices'][0]['message']['content'] ?? '';
                if ($reply !== '') {
                    return trim($reply);
                }
            }
        } catch (\Exception $e) {
            // fallback below
        }

        return 'I can help with ticketing, payments, and refunds. If you need a human, please say "agent" and we will assign one.';
    }
}
