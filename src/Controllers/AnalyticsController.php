<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Event;
use App\Models\Attendee;
use App\Models\User;
use App\Middleware\AuthMiddleware;

/**
 * Analytics Controller
 * 
 * Provides analytics and insights
 */
class AnalyticsController
{
    private $eventModel;
    private $attendeeModel;
    private $userModel;
    
    public function __construct()
    {
        $this->eventModel = new Event();
        $this->attendeeModel = new Attendee();
        $this->userModel = new User();
    }
    
    /**
     * Get analytics overview
     */
    public function overview()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $userStats = $this->userModel->getUserStats($authUser['user_id']);
        
        // Get recent events
        $recentEvents = $this->eventModel->getByOrganizer($authUser['user_id']);
        
        // Calculate additional metrics
        $totalAttendees = 0;
        foreach ($recentEvents as $event) {
            $totalAttendees += $this->attendeeModel->countByEvent($event['id']);
        }
        
        $analytics = [
            'total_events' => $userStats['total_events'],
            'total_tickets_sold' => $userStats['total_tickets_sold'],
            'total_revenue' => $userStats['total_revenue'],
            'total_attendees' => $totalAttendees,
            'average_tickets_per_event' => $userStats['total_events'] > 0 
                ? round($userStats['total_tickets_sold'] / $userStats['total_events'], 2) 
                : 0,
            'average_revenue_per_event' => $userStats['total_events'] > 0 
                ? round($userStats['total_revenue'] / $userStats['total_events'], 2) 
                : 0
        ];
        
        Response::success($analytics);
    }
    
    /**
     * Get event-specific analytics
     */
    public function eventAnalytics($eventId)
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $event = $this->eventModel->find($eventId);
        
        if (!$event) {
            Response::notFound('Event not found');
        }
        
        // Check if user owns this event
        if ($event['organizer_id'] !== $authUser['user_id']) {
            Response::forbidden('Access denied');
        }
        
        $attendees = $this->attendeeModel->getByEvent($eventId);
        $ticketsSold = $event['total_tickets'] - $event['available_tickets'];
        $revenue = $ticketsSold * $event['ticket_price'];
        
        // Calculate status breakdown
        $statusBreakdown = [
            'Confirmed' => 0,
            'Pending' => 0,
            'Cancelled' => 0
        ];
        
        foreach ($attendees as $attendee) {
            $status = $attendee['status'] ?? 'Confirmed';
            if (isset($statusBreakdown[$status])) {
                $statusBreakdown[$status]++;
            }
        }
        
        $analytics = [
            'event_id' => $eventId,
            'event_title' => $event['title'],
            'total_tickets' => $event['total_tickets'],
            'tickets_sold' => $ticketsSold,
            'tickets_available' => $event['available_tickets'],
            'total_attendees' => count($attendees),
            'revenue' => $revenue,
            'ticket_price' => $event['ticket_price'],
            'sell_through_rate' => $event['total_tickets'] > 0 
                ? round(($ticketsSold / $event['total_tickets']) * 100, 2) 
                : 0,
            'status_breakdown' => $statusBreakdown
        ];
        
        Response::success($analytics);
    }
    
    /**
     * Get AI-powered insights
     */
    public function aiInsights()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser) {
            Response::unauthorized();
        }
        
        $userStats = $this->userModel->getUserStats($authUser['user_id']);
        $events = $this->eventModel->getByOrganizer($authUser['user_id']);
        
        // Generate insights based on data
        $insights = [];
        
        // Revenue insight
        if ($userStats['total_revenue'] > 0) {
            $insights[] = [
                'type' => 'revenue',
                'title' => 'Revenue Performance',
                'message' => "You've generated ₦" . number_format($userStats['total_revenue'], 2) . 
                           " from " . $userStats['total_events'] . " events.",
                'sentiment' => 'positive'
            ];
        }
        
        // Ticket sales insight
        if ($userStats['total_tickets_sold'] > 100) {
            $insights[] = [
                'type' => 'tickets',
                'title' => 'Ticket Sales Milestone',
                'message' => "Congratulations! You've sold over " . $userStats['total_tickets_sold'] . " tickets!",
                'sentiment' => 'positive'
            ];
        }
        
        // Event performance insight
        if (count($events) > 0) {
            $avgSellThrough = 0;
            foreach ($events as $event) {
                if ($event['total_tickets'] > 0) {
                    $sold = $event['total_tickets'] - $event['available_tickets'];
                    $avgSellThrough += ($sold / $event['total_tickets']) * 100;
                }
            }
            $avgSellThrough = round($avgSellThrough / count($events), 2);
            
            if ($avgSellThrough > 75) {
                $insights[] = [
                    'type' => 'performance',
                    'title' => 'High Demand Events',
                    'message' => "Your events have an average sell-through rate of {$avgSellThrough}%. Consider increasing ticket prices!",
                    'sentiment' => 'positive'
                ];
            } elseif ($avgSellThrough < 30) {
                $insights[] = [
                    'type' => 'performance',
                    'title' => 'Marketing Opportunity',
                    'message' => "Your average sell-through rate is {$avgSellThrough}%. Consider boosting marketing efforts.",
                    'sentiment' => 'warning'
                ];
            }
        }
        
        // Growth insight
        if ($userStats['total_events'] >= 5) {
            $insights[] = [
                'type' => 'growth',
                'title' => 'Event Organizer Growth',
                'message' => "You're building momentum with " . $userStats['total_events'] . " events created!",
                'sentiment' => 'positive'
            ];
        }
        
        Response::success([
            'insights' => $insights,
            'generated_at' => date('Y-m-d H:i:s'),
            'insights_count' => count($insights)
        ]);
    }
}
?>
