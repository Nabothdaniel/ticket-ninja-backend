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
     * Get platform-wide analytics for administrators
     */
    public function systemOverview()
    {
        $authUser = AuthMiddleware::getAuthUser();
        if (!$authUser || $authUser['role'] !== 'admin') {
            Response::forbidden('Admin access required');
        }

        // Aggregate system-wide stats
        $totalUsers = $this->userModel->count();
        $totalEvents = $this->eventModel->count();
        $totalTicketsSold = $this->attendeeModel->count();
        
        // Sum gross sales and platform revenue (3%)
        $sql = "SELECT SUM((total_tickets - available_tickets) * ticket_price) as gross_sales FROM events";
        $revenueResult = $this->eventModel->query($sql);
        $grossSales = $revenueResult[0]['gross_sales'] ?? 0;
        $platformRevenue = $grossSales * 0.03;

        // Recent registrations
        $recentUsers = $this->userModel->query("SELECT id, full_name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5");

        Response::success([
            'total_users' => (int)$totalUsers,
            'total_events' => (int)$totalEvents,
            'total_tickets_sold' => (int)$totalTicketsSold,
            'total_revenue' => (float)$platformRevenue,
            'gross_sales' => (float)$grossSales,
            'recent_users' => $recentUsers,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
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
        
        $userId = $authUser['user_id'];
        $userStats = $this->userModel->getUserStats($userId);
        
        // Get recent events
        $recentEvents = $this->eventModel->getByOrganizer($userId);
        
        // Generate Month Labels
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = date('M', strtotime("-$i months"));
        }

        // 1. Monthly Users (Registrations)
        $sqlUsers = "
            SELECT DATE_FORMAT(a.created_at, '%b') as month, COUNT(*) as users
            FROM attendees a
            JOIN events e ON a.event_id = e.id
            WHERE e.organizer_id = :userId
            AND a.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month
        ";
        $monthlyUsersRaw = $this->attendeeModel->query($sqlUsers, ['userId' => $userId]);
        $monthlyUsers = array_map(function($m) use ($monthlyUsersRaw) {
            $found = array_filter($monthlyUsersRaw, fn($r) => $r['month'] === $m);
            return [
                'month' => $m,
                'users' => !empty($found) ? (int)array_values($found)[0]['users'] : 0
            ];
        }, $months);

        // 2. Revenue Growth
        $sqlRev = "
            SELECT DATE_FORMAT(a.created_at, '%b') as month, SUM(e.ticket_price) as revenue
            FROM attendees a
            JOIN events e ON a.event_id = e.id
            WHERE e.organizer_id = :userId
            AND a.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY month
        ";
        $revenueGrowthRaw = $this->attendeeModel->query($sqlRev, ['userId' => $userId]);
        $revenueGrowth = array_map(function($m) use ($revenueGrowthRaw) {
            $found = array_filter($revenueGrowthRaw, fn($r) => $r['month'] === $m);
            return [
                'month' => $m,
                'revenue' => !empty($found) ? (float)array_values($found)[0]['revenue'] : 0.0
            ];
        }, $months);

        // 3. Ticket Breakdown by Class
        $ticketBreakdown = [];
        foreach (array_slice($recentEvents, 0, 5) as $event) {
            $sqlTkt = "
                SELECT ticket_type, COUNT(*) as count 
                FROM attendees 
                WHERE event_id = :eventId 
                GROUP BY ticket_type
            ";
            $breakdownRaw = $this->attendeeModel->query($sqlTkt, ['eventId' => $event['id']]);
            $item = ['event' => substr($event['title'], 0, 15), 'VIP' => 0, 'Regular' => 0, 'Student' => 0];
            foreach ($breakdownRaw as $raw) {
                if (isset($item[$raw['ticket_type']])) {
                    $item[$raw['ticket_type']] = (int)$raw['count'];
                }
            }
            $ticketBreakdown[] = $item;
        }

        // 4. Attendance Rate (Check-ins vs Total Attendees)
        $totalRegistered = $userStats['total_tickets_sold'];
        $sqlCheckins = "
            SELECT COUNT(*) as checked_in 
            FROM attendees a 
            JOIN events e ON a.event_id = e.id 
            WHERE e.organizer_id = :userId AND a.checked_in_at IS NOT NULL
        ";
        $checkinResult = $this->attendeeModel->query($sqlCheckins, ['userId' => $userId]);
        $checkedInCount = $checkinResult[0]['checked_in'] ?? 0;
        $attendanceRate = $totalRegistered > 0 ? round(($checkedInCount / $totalRegistered) * 100, 1) : 0;

        $analytics = [
            'revenue' => (float)$userStats['gross_revenue'],
            'attendanceRate' => (float)$attendanceRate,
            'totalEvents' => (int)$userStats['total_events'],
            'monthlyUsers' => $monthlyUsers,
            'revenueGrowth' => $revenueGrowth,
            'attendanceTrend' => array_map(fn($m) => ['month' => $m['month'], 'rate' => rand(60, 95)], $monthlyUsers), // Mocked trend
            'ticketBreakdown' => $ticketBreakdown
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
        if ($userStats['gross_revenue'] > 0) {
            $insights[] = [
                'type' => 'revenue',
                'title' => 'Revenue Performance',
                'message' => "You've generated ₦" . number_format($userStats['gross_revenue'], 2) . 
                           " in total sales. Your net earnings after platform fees are ₦" . number_format($userStats['net_revenue'], 2) . ".",
                'sentiment' => 'positive'
            ];
        }
        
        // Ticket sales insight
        if ($userStats['total_tickets_sold'] > 100) {
            $insights[] = [
                'type' => 'tickets',
                'title' => 'Ticket Sales Milestone',
                'message' => "Congratulations! You've sold over " . $userStats['total_tickets_sold'] . " tickets! High volume detected.",
                'sentiment' => 'positive'
            ];
        }
        
        // Event performance insight
        if (count($events) > 0) {
            $avgSellThrough = 0;
            $eventDetails = [];
            foreach ($events as $event) {
                if ($event['total_tickets'] > 0) {
                    $sold = $event['total_tickets'] - $event['available_tickets'];
                    $rate = ($sold / $event['total_tickets']) * 100;
                    $avgSellThrough += $rate;
                    $eventDetails[] = ['title' => $event['title'], 'rate' => $rate];
                }
            }
            $avgSellThrough = round($avgSellThrough / count($events), 2);
            
            if ($avgSellThrough > 75) {
                $insights[] = [
                    'type' => 'performance',
                    'title' => 'High Demand Events',
                    'message' => "Your events have an excellent average sell-through rate of {$avgSellThrough}%. Optimization tip: Consider premium pricing for the next event.",
                    'sentiment' => 'positive'
                ];
            } elseif ($avgSellThrough < 30) {
                $insights[] = [
                    'type' => 'performance',
                    'title' => 'Marketing Opportunity',
                    'message' => "Average sell-through rate is currently {$avgSellThrough}%. Strategy: Run a 10% discount campaign to boost momentum.",
                    'sentiment' => 'warning'
                ];
            }

            // Specific event insight
            usort($eventDetails, fn($a, $b) => $b['rate'] <=> $a['rate']);
            if (!empty($eventDetails)) {
                $topEvent = $eventDetails[0];
                if ($topEvent['rate'] > 90) {
                    $insights[] = [
                        'type' => 'star',
                        'title' => 'Trending Event',
                        'message' => "'{$topEvent['title']}' is almost sold out ({$topEvent['rate']}%). High conversion rate detected.",
                        'sentiment' => 'positive'
                    ];
                }
            }
        }
        
        // Peak Activity insight (Mocked for now but with better logic)
        $insights[] = [
            'type' => 'behavior',
            'title' => 'Peak Booking Hours',
            'message' => "Most users book between 6 PM and 10 PM. Best time to post updates!",
            'sentiment' => 'info'
        ];
        
        // Growth insight
        if ($userStats['total_events'] >= 3) {
            $insights[] = [
                'type' => 'growth',
                'title' => 'Growing Portfolio',
                'message' => "You've successfully managed " . $userStats['total_events'] . " events. You are now a Top Organizer!",
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
