<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Event;
use App\Models\Attendee;
use App\Middleware\AuthMiddleware;
use App\Utils\Validator;

/**
 * Event Controller
 * 
 * Handles event CRUD operations
 */
class EventController
{
    private $eventModel;
    private $attendeeModel;
    
    public function __construct()
    {
        $this->eventModel = new Event();
        $this->attendeeModel = new Attendee();
    }
    
    /**
     * Get all events
     */
    public function index()
    {
        $category = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        if ($search) {
            $events = $this->eventModel->search($search);
        } elseif ($category) {
            $events = $this->eventModel->getByCategory($category);
        } else {
            $events = $this->eventModel->all($limit, $offset);
        }
        
        // Add attendee count to each event
        foreach ($events as &$event) {
            $event['attendee_count'] = $this->attendeeModel->countByEvent($event['id']);
            $event['tickets_sold'] = $event['total_tickets'] - $event['available_tickets'];
        }
        
        Response::success($events);
    }
    
    /**
     * Get single event
     */
    public function show($id)
    {
        $event = $this->eventModel->getWithOrganizer($id);
        
        if (!$event) {
            Response::notFound('Event not found');
        }
        
        $event['attendee_count'] = $this->attendeeModel->countByEvent($id);
        $event['tickets_sold'] = $event['total_tickets'] - $event['available_tickets'];
        
        Response::success($event);
    }
    
    /**
     * Create new event
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
            'title' => 'required',
            'description' => 'required',
            'date' => 'required',
            'time' => 'required',
            'location' => 'required',
            'category' => 'required',
            'ticket_price' => 'required|numeric',
            'total_tickets' => 'required|numeric'
        ]);
        
        if (!$validator->validate()) {
            Response::validationError($validator->errors());
        }
        
        try {
            $eventId = $this->eventModel->create([
                'title' => $input['title'],
                'description' => $input['description'],
                'date' => $input['date'],
                'time' => $input['time'],
                'location' => $input['location'],
                'category' => $input['category'],
                'ticket_price' => $input['ticket_price'],
                'total_tickets' => $input['total_tickets'],
                'available_tickets' => $input['total_tickets'],
                'image_url' => $input['image_url'] ?? null,
                'organizer_id' => $authUser['user_id']
            ]);
            
            $event = $this->eventModel->find($eventId);
            
            Response::success($event, 'Event created successfully', 201);
            
        } catch (\Exception $e) {
            Response::error('Failed to create event: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update event
     */
    public function update($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $event = $this->eventModel->find($id);
        
        if (!$event) {
            Response::notFound('Event not found');
        }
        
        // Check if user owns this event
        if ($event['organizer_id'] !== $authUser['user_id']) {
            Response::forbidden('You do not have permission to update this event');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $this->eventModel->update($id, $input);
            $updatedEvent = $this->eventModel->find($id);
            
            Response::success($updatedEvent, 'Event updated successfully');
            
        } catch (\Exception $e) {
            Response::error('Failed to update event: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Delete event
     */
    public function delete($id)
    {
        $authUser = AuthMiddleware::getAuthUser();
        
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $event = $this->eventModel->find($id);
        
        if (!$event) {
            Response::notFound('Event not found');
        }
        
        // Check if user owns this event
        if ($event['organizer_id'] !== $authUser['user_id']) {
            Response::forbidden('You do not have permission to delete this event');
        }
        
        try {
            $this->eventModel->delete($id);
            Response::success(null, 'Event deleted successfully');
            
        } catch (\Exception $e) {
            Response::error('Failed to delete event: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get user events
     */
    public function getUserEvents($userId)
    {
        $authUser = AuthMiddleware::getAuthUser();
        
        if (!$authUser) {
            Response::unauthorized();
        }
        
        // Users can only get their own events
        if ($userId !== $authUser['user_id']) {
            Response::forbidden('You can only view your own events');
        }
        
        $events = $this->eventModel->getByOrganizer($userId);
        
        // Add attendee count to each event
        foreach ($events as &$event) {
            $event['attendee_count'] = $this->attendeeModel->countByEvent($event['id']);
            $event['tickets_sold'] = $event['total_tickets'] - $event['available_tickets'];
        }
        
        Response::success($events);
    }
}
?>
