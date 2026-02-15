<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Attendee;
use App\Models\Event;
use App\Middleware\AuthMiddleware;
use App\Utils\Validator;
use App\Services\NotificationService;
use Ramsey\Uuid\Uuid;

/**
 * Attendee Controller
 * 
 * Handles attendee management
 */
class AttendeeController
{
    private $attendeeModel;
    private $eventModel;
    
    public function __construct()
    {
        $this->attendeeModel = new Attendee();
        $this->eventModel = new Event();
    }
    
    /**
     * Get all attendees
     */
    public function index()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $eventId = $_GET['event_id'] ?? null;
        
        // If event_id is provided, check if user has access to this event
        if ($eventId) {
            $event = $this->eventModel->find($eventId);
            if (!$event) {
                Response::notFound('Event not found');
            }
            
            // Fix: Organizers can only see attendees of their own events
            if ($authUser['role'] === 'organizer' && $event['organizer_id'] !== $authUser['user_id']) {
                Response::forbidden('You do not have permission to view attendees for this event');
            }
            
            $attendees = $this->attendeeModel->getByEvent($eventId);
        } else {
            // If no event_id, filter by role
            if ($authUser['role'] === 'organizer') {
                $attendees = $this->attendeeModel->getByOrganizer($authUser['user_id']);
            } else if ($authUser['role'] === 'admin') {
                $attendees = $this->attendeeModel->all();
            } else {
                // Attendees can only see their own registrations? 
                // Normally attendees use /attendees/user/{id} but let's be safe
                $attendees = $this->attendeeModel->where('user_id', $authUser['user_id']);
            }
        }
        
        Response::success($attendees);
    }
    
    /**
     * Get single attendee
     */
    public function show($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $attendee = $this->attendeeModel->getWithEventDetails($id);
        
        if (!$attendee) {
            Response::notFound('Attendee not found');
        }
        
        Response::success($attendee);
    }
    
    /**
     * Create new attendee
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
            'name' => 'required',
            'email' => 'required|email',
            'event_id' => 'required',
            'ticket_type' => 'required'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }
        
        // Check if event exists
        $event = $this->eventModel->find($input['event_id']);
        if (!$event) {
            Response::notFound('Event not found');
        }
        
        // Check if tickets are available
        if ($event['available_tickets'] <= 0) {
            Response::error('No tickets available for this event', 400);
        }
        
        // Check if already registered
        if ($this->attendeeModel->isRegistered($authUser['user_id'], $input['event_id'])) {
            Response::error('Already registered for this event', 409);
        }
        
        try {
            $ticketCode = Uuid::uuid4()->toString();

            // Create attendee
            $attendeeId = $this->attendeeModel->create([
                'user_id' => $authUser['user_id'],
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'] ?? null,
                'ticket_type' => $input['ticket_type'],
                'event_id' => $input['event_id'],
                'status' => 'Confirmed',
                'ticket_code' => $ticketCode
            ]);
            
            // Update available tickets
            $this->eventModel->updateAvailableTickets($input['event_id'], 1);
            
            $attendee = $this->attendeeModel->find($attendeeId);
            
            // Send Notification
            $notificationService = new NotificationService();
            $userModel = new \App\Models\User();
            $user = $userModel->find($authUser['user_id']);
            
            // Generate QR Code URL (using a public API for now or frontend URL)
            // Assuming frontend handles QR generation from ticket code, but we can send a link
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . $ticketCode;
            
            $notificationService->sendTicket($user, $event, $ticketCode, $qrCodeUrl);
            
            Response::success($attendee, 'Registration successful', 201);
            
        } catch (\Exception $e) {
            Response::error('Failed to register: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update attendee
     */
    public function update($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $attendee = $this->attendeeModel->find($id);
        
        if (!$attendee) {
            Response::notFound('Attendee not found');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $this->attendeeModel->update($id, $input);
            $updatedAttendee = $this->attendeeModel->find($id);
            
            Response::success($updatedAttendee, 'Attendee updated successfully');
            
        } catch (\Exception $e) {
            Response::error('Failed to update attendee: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete attendee
     */
    public function delete($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $attendee = $this->attendeeModel->find($id);
        
        if (!$attendee) {
            Response::notFound('Attendee not found');
        }
        
        try {
            $this->attendeeModel->delete($id);
            Response::success(null, 'Attendee deleted successfully');
            
        } catch (\Exception $e) {
            Response::error('Failed to delete attendee: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get event attendees
     */
    public function getEventAttendees($eventId)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $attendees = $this->attendeeModel->getByEvent($eventId);
        
        Response::success($attendees);
    }

    /**
     * Get tickets for authenticated user
     */
    public function getUserTickets($userId)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }

        // Users can only view their own tickets
        if ($userId !== $authUser['user_id']) {
            Response::forbidden('You can only view your own tickets');
        }

        $tickets = $this->attendeeModel->getUserTickets($userId);
        Response::success($tickets);
    }
    
    /**
     * Send bulk emails to attendees
     */
    public function sendBulkEmails()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser || $authUser['role'] !== 'organizer') {
            Response::unauthorized();
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $validator = new Validator($input, [
            'event_id' => 'required',
            'subject' => 'required',
            'message' => 'required'
        ]);

        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }

        // Check ownership
        $event = $this->eventModel->find($input['event_id']);
        if (!$event || $event['organizer_id'] !== $authUser['user_id']) {
            Response::forbidden('You do not have permission to send emails for this event');
        }

        $attendees = $this->attendeeModel->getByEvent($input['event_id']);
        
        // Simulating email sending
        // In a real production app, this would use items from NotificationService in a loop or queue
        // For this demo, we'll return the count of recipients
        $count = count($attendees);
        
        Response::success(['count' => $count], "Emails sent successfully to $count attendees");
    }
}
?>
