<?php

namespace App\Models;

/**
 * Attendee Model
 */
class Attendee extends BaseModel
{
    protected $table = 'attendees';
    
    /**
     * Get attendees by event
     */
    public function getByEvent($eventId)
    {
        return $this->where('event_id', $eventId);
    }
    
    /**
     * Get attendee count by event
     */
    public function countByEvent($eventId)
    {
        return $this->count('event_id', $eventId);
    }
    
    /**
     * Get attendees by status
     */
    public function getByStatus($status)
    {
        return $this->where('status', $status);
    }
    
    /**
     * Get attendee with event details
     */
    public function getWithEventDetails($attendeeId)
    {
        $sql = "SELECT a.*, e.title as event_title, e.date as event_date, e.location as event_location
                FROM attendees a
                LEFT JOIN events e ON a.event_id = e.id
                WHERE a.id = :attendeeId";
        
        $result = $this->query($sql, ['attendeeId' => $attendeeId]);
        return $result[0] ?? null;
    }
    
    /**
     * Check if user is already registered for event
     */
    /**
     * Check if user is already registered for event
     */
    public function isRegistered($userId, $eventId)
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE user_id = :userId AND event_id = :eventId";
        
        $result = $this->query($sql, [
            'userId' => $userId,
            'eventId' => $eventId
        ]);
        
        return ($result[0]['count'] ?? 0) > 0;
    }

    /**
     * Find attendee by ticket code
     */
    public function findByTicketCode($code)
    {
        $sql = "SELECT a.*, u.name as user_name, u.email as user_email, e.title as event_title, e.organizer_id
                FROM attendees a
                JOIN users u ON a.user_id = u.id
                JOIN events e ON a.event_id = e.id
                WHERE a.ticket_code = :code";
        $result = $this->query($sql, ['code' => $code]);
        return $result[0] ?? null;
    }

    /**
     * Mark attendee as checked in
     */
    public function markAsCheckedIn($id)
    {
        $sql = "UPDATE {$this->table} SET checked_in_at = NOW() WHERE id = :id";
        // Since query might return result set or boolean depending on implementation, 
        // we assume it executes successfully. 
        // If query() returns void/bool for UPDATE, this is fine.
        return $this->query($sql, ['id' => $id]);
    }
}
?>
