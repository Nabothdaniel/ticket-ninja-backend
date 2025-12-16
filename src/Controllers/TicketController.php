<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Attendee;
use App\Models\Event;
use App\Middleware\AuthMiddleware;

class TicketController
{
    private $attendeeModel;
    private $eventModel;

    public function __construct()
    {
        $this->attendeeModel = new Attendee();
        $this->eventModel = new Event();
    }

    /**
     * Verify a ticket by code
     */
    public function verify()
    {
        // Ensure user is authenticated (organizer)
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['code'])) {
            Response::error('Ticket code is required', 400);
        }

        $ticket = $this->attendeeModel->findByTicketCode($input['code']);

        if (!$ticket) {
            Response::error('Invalid ticket code', 404);
        }

        // Check if the current user is the organizer of the event
        if ($ticket['organizer_id'] !== $authUser['user_id']) {
            Response::error('You are not authorized to verify tickets for this event', 403);
        }

        if ($ticket['checked_in_at']) {
            Response::error('Ticket already used', 400, [
                'checked_in_at' => $ticket['checked_in_at'],
                'attendee' => [
                    'name' => $ticket['user_name'],
                    'email' => $ticket['user_email']
                ],
                'event' => $ticket['event_title']
            ]);
        }

        $this->attendeeModel->markAsCheckedIn($ticket['id']);

        Response::success([
            'attendee' => [
                'name' => $ticket['user_name'],
                'email' => $ticket['user_email']
            ],
            'event' => $ticket['event_title'],
            'checked_in_at' => date('Y-m-d H:i:s')
        ], 'Ticket verified successfully');
    }
}
